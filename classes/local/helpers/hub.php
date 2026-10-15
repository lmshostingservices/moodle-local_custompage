<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_custompage\local\helpers;

use local_custompage\constants;
use local_custompage\form\page as page_form;
use local_custompage\local\models\page as page_model;
use stdClass;

/**
 * Resource hubs: a page that shows a card for each of its resources (a "section" in the data model).
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub {
    /**
     * Audience choice shown in the wizard, read from a hub's audiences.
     *
     * @param int $hubid
     * @return array [string $choice, int[] $roles, int[] $users]
     */
    public static function get_audience_choice(int $hubid): array {
        $s = audience::get_selection($hubid);
        // A mix the wizard's single choice cannot show (set on the Edit page form, for example roles plus chosen people,
        // or visitors only) is kept as it is unless the person picks something else.
        $simple = ($s->roles && !$s->users && !$s->allusers && !$s->guests)
            || ($s->users && !$s->roles && !$s->allusers && !$s->guests)
            || (!$s->roles && !$s->users && ($s->allusers || !$s->guests));
        if (!$simple) {
            return ['keep', $s->roles, $s->users];
        }
        if ($s->roles) {
            return ['roles', $s->roles, []];
        }
        if ($s->users) {
            return ['people', [], $s->users];
        }
        if ($s->allusers && $s->guests) {
            return ['public', [], []];
        }
        if ($s->allusers) {
            return ['all', [], []];
        }
        return ['managers', [], []];
    }

    /**
     * Step 1: create or update a hub's name, intro, audience and menu settings.
     *
     * @param stdClass $data Form data
     * @param page_model|null $hub
     * @return page_model
     */
    public static function save_settings(stdClass $data, ?page_model $hub): page_model {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $fields = [
            'name' => trim((string) $data->name),
            'title' => trim((string) ($data->title ?? '')),
            'showinprimarynav' => (int) !empty($data->showinprimarynav),
            'cardsummary' => trim((string) ($data->intro ?? '')) ?: null,
            'audiencemode' => constants::AUDIENCE_OWN,
        ] + page_form::ownership_from_data($data);
        if ($hub) {
            $hub->set_many($fields);
            $hub->update();
        } else {
            $hub = page::create_page((object) ($fields + ['iscontainer' => 1, 'look' => 'classic']));
        }
        $choice = (string) ($data->audience ?? 'all');
        if ($choice !== 'keep') {
            audience::set_selection((int) $hub->get('id'), (object) [
                'allusers' => in_array($choice, ['all', 'public'], true),
                'guests' => $choice === 'public',
                'roles' => $choice === 'roles' ? (array) ($data->roles ?? []) : [],
                'users' => $choice === 'people' ? (array) ($data->users ?? []) : [],
            ]);
        }
        $transaction->allow_commit();
        return new page_model((int) $hub->get('id'));
    }

    /**
     * Step 2: add or update a resource in a hub. Resources use the hub's audience.
     *
     * @param page_model $hub
     * @param stdClass $data Form data
     * @param page_model|null $resource
     * @return page_model
     */
    public static function save_resource(page_model $hub, stdClass $data, ?page_model $resource): page_model {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $fields = [
            'name' => trim((string) $data->name),
            'cardsummary' => trim((string) ($data->cardsummary ?? '')) ?: null,
            'parent' => (int) $hub->get('id'),
            'showinprimarynav' => 0,
        ] + page_form::ownership_from_data($data);
        if ($resource) {
            $resource->set_many($fields);
            $resource->update();
        } else {
            $newfields = $fields + ['iscontainer' => 0, 'audiencemode' => constants::AUDIENCE_INHERIT];
            $resource = page::create_page((object) $newfields);
        }
        $context = $resource->get_context();
        $data = file_postupdate_standard_editor(
            $data,
            'content',
            page_form::content_editor_options($context),
            $context,
            'local_custompage',
            'content',
            0
        );
        $resource->set_many(['content' => $data->content, 'contentformat' => (int) $data->contentformat]);
        $file = page_form::get_draft_file((int) ($data->designfile ?? 0));
        if ($file) {
            // A design file replaces the resource's text and brings its own styles.
            [$css, $html] = design::import_html($file->get_content());
            $resource->set_many([
                'content' => $html,
                'contentformat' => FORMAT_HTML,
                'designcss' => trim($css) === '' ? null : $css,
                'designcsscompiled' => trim($css) === '' ? null : design::scope_css($css, (int) $resource->get('id')),
            ]);
        } else if (!empty($data->removedesign)) {
            $resource->set_many(['designcss' => null, 'designcsscompiled' => null]);
        }
        $resource->update();
        file_save_draft_area_files(
            $data->documents,
            $context->id,
            'local_custompage',
            'documents',
            0,
            page_form::documents_options()
        );
        file_save_draft_area_files(
            $data->cardimage,
            $context->id,
            'local_custompage',
            'cardimage',
            0,
            page_form::card_image_options()
        );
        card_image::shrink((int) $context->id);
        $transaction->allow_commit();
        return $resource;
    }

    /**
     * Step 3: choose a built-in look, or apply an uploaded design.
     *
     * A design file replaces the area above the cards and its CSS applies to the whole hub.
     * Choosing a built-in look again removes the uploaded design.
     *
     * @param page_model $hub
     * @param stdClass $data Form data
     */
    public static function save_look(page_model $hub, stdClass $data): void {
        $look = (string) ($data->look ?? 'classic');
        if ($look === 'custom') {
            $file = page_form::get_draft_file((int) ($data->designfile ?? 0));
            if ($file) {
                [$css, $content] = design::import_html($file->get_content());
                $hub->set_many([
                    'content' => $content,
                    'contentformat' => FORMAT_HTML,
                    'designcss' => trim($css) === '' ? null : $css,
                    'designcsscompiled' => trim($css) === '' ? null : design::scope_css($css, (int) $hub->get('id')),
                ]);
            }
        } else if (!in_array($look, \local_custompage\form\hub_look::LOOKS, true)) {
            $look = 'classic';
        } else if ($hub->get('look') === 'custom') {
            $hub->set_many(['content' => null, 'designcss' => null, 'designcsscompiled' => null]);
        }
        $hub->set('look', $look);
        $hub->update();
    }
}
