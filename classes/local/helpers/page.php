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

use context_helper;
use invalid_parameter_exception;
use local_custompage\custom_context\context_custompage;
use local_custompage\constants;
use local_custompage\local\helpers\audience as audience_helper;
use local_custompage\local\models\audience;
use local_custompage\local\models\page as page_model;
use local_custompage\manager;
use stdClass;

/**
 * Helper class for manipulating custom pages and their elements
 *
 * @package     local_custompage
 * @copyright   2021 Paul Holden <paulh@moodle.com>
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    /**
     * Create or update a section or page from the page form's data, including its files and audiences.
     *
     * @param stdClass $data Form data
     * @param page_model|null $page The page being edited, or null to create one
     * @return page_model
     */
    public static function save_from_form(stdClass $data, ?page_model $page): page_model {
        global $DB;

        $issection = !empty($data->iscontainer);
        $parent = $issection ? 0 : (int) ($data->parent ?? 0);
        $fields = [
            'name' => trim((string) $data->name),
            'title' => trim((string) ($data->title ?? '')),
            'parent' => $parent ?: null,
            'showinprimarynav' => $parent ? 0 : (int) !empty($data->showinprimarynav),
            'cardsummary' => $issection ? null : trim((string) ($data->cardsummary ?? '')),
            'audiencemode' => ($parent && (int) ($data->audiencemode ?? 0) === constants::AUDIENCE_INHERIT)
                ? constants::AUDIENCE_INHERIT : constants::AUDIENCE_OWN,
        ] + \local_custompage\form\page::ownership_from_data($data);

        $transaction = $DB->start_delegated_transaction();
        if ($page) {
            $page->set_many($fields);
            $page->update();
        } else {
            $page = self::create_page((object) ($fields + ['iscontainer' => (int) $issection]));
        }
        $context = $page->get_context();

        // Body (or section intro) with its embedded files.
        $editoroptions = \local_custompage\form\page::content_editor_options($context);
        $data = file_postupdate_standard_editor($data, 'content', $editoroptions, $context, 'local_custompage', 'content', 0);
        $content = $data->content;
        $css = (string) ($data->designcss ?? '');

        // An uploaded design file replaces the page's CSS and its content.
        if (!empty($data->designfile) && ($file = \local_custompage\form\page::get_draft_file((int) $data->designfile))) {
            [$css, $content] = design::import_html($file->get_content());
            $data->contentformat = FORMAT_HTML;
        }
        $page->set_many([
            'content' => $content,
            'contentformat' => (int) $data->contentformat,
            'designcss' => trim($css) === '' ? null : $css,
            'designcsscompiled' => trim($css) === '' ? null : design::scope_css($css, (int) $page->get('id')),
        ]);
        $page->update();

        if (!$issection) {
            if (isset($data->documents)) {
                file_save_draft_area_files(
                    $data->documents,
                    $context->id,
                    'local_custompage',
                    'documents',
                    0,
                    \local_custompage\form\page::documents_options()
                );
            }
            if (isset($data->cardimage)) {
                file_save_draft_area_files(
                    $data->cardimage,
                    $context->id,
                    'local_custompage',
                    'cardimage',
                    0,
                    \local_custompage\form\page::card_image_options()
                );
                card_image::shrink((int) $context->id);
            }
        }

        // A page that inherits keeps no audiences of its own.
        $selection = (object) [
            'allusers' => !empty($data->aud_allusers),
            'guests' => !empty($data->aud_guests),
            'roles' => (array) ($data->aud_roles ?? []),
            'users' => (array) ($data->aud_users ?? []),
        ];
        if ($page->inherits_audience()) {
            $selection = (object) ['allusers' => false, 'guests' => false, 'roles' => [], 'users' => []];
        }
        audience_helper::set_selection((int) $page->get('id'), $selection);

        $transaction->allow_commit();
        return $page;
    }

    /**
     * Create a page record and its context.
     *
     * @param stdClass $data Page fields (name required)
     * @return page_model
     */
    public static function create_page(stdClass $data): page_model {
        $data->name = trim((string) $data->name);
        $data->title = trim((string) ($data->title ?? ''));
        $data->iscontainer = (int) ($data->iscontainer ?? 0);
        $data->parent = $data->iscontainer ? null : ((int) ($data->parent ?? 0) ?: null);

        $pagepersistent = manager::create_page_persistent($data);
        $context = context_custompage::instance((int) $pagepersistent->get('id'));
        $pagepersistent->set('contextid', $context->id);
        if (!$pagepersistent->get('ownerid')) {
            // Whoever creates a page looks after it until someone else is named.
            global $USER;
            $pagepersistent->set('ownerid', !empty($USER->id) ? (int) $USER->id : null);
        }
        $pagepersistent->update();
        return $pagepersistent;
    }

    /**
     * Update a page's basic fields.
     *
     * @param stdClass $data Must include id; other fields optional
     * @return page_model
     * @throws invalid_parameter_exception
     */
    public static function update_page(stdClass $data): page_model {
        $page = page_model::get_record(['id' => $data->id]);
        if ($page === false) {
            throw new invalid_parameter_exception('Invalid page');
        }
        foreach (['name', 'title', 'showinprimarynav', 'cardsummary'] as $field) {
            if (isset($data->$field)) {
                $page->set($field, is_string($data->$field) ? trim($data->$field) : $data->$field);
            }
        }
        if (property_exists($data, 'parent') && !$page->is_container()) {
            $parent = (int) $data->parent;
            if ($parent && !page_model::record_exists_select('id = ? AND iscontainer = 1', [$parent])) {
                throw new invalid_parameter_exception('Invalid section');
            }
            $page->set('parent', $parent ?: null);
        }
        $page->update();
        return $page;
    }

    /**
     * Delete a page or section with its audiences, blocks and files.
     *
     * Pages inside a deleted section move to the top level. Pages that used the section's audience keep the same
     * visibility: the section's audiences are copied to them.
     *
     * @param int $pageid
     * @return bool
     * @throws invalid_parameter_exception
     */
    public static function delete_page(int $pageid): bool {
        global $DB;

        $page = page_model::get_record(['id' => $pageid]);
        if ($page === false) {
            throw new invalid_parameter_exception('Invalid page');
        }

        $transaction = $DB->start_delegated_transaction();

        $sectionselection = audience_helper::get_selection($pageid);
        foreach ($DB->get_records('local_custompages', ['parent' => $pageid]) as $child) {
            $inherited = (int) $child->audiencemode === constants::AUDIENCE_INHERIT;
            $DB->update_record('local_custompages', (object) [
                'id' => $child->id,
                'parent' => null,
                'audiencemode' => constants::AUDIENCE_OWN,
            ]);
            $child->parent = null;
            $childpage = new page_model(0, $child);
            $childpage->update_hierarchy();
            // A fresh top-level position: the hub's position (and its children's) is reused by the next hub created.
            $childpage->refresh_sortthread();
            $childpage->update_context_parent();
            if ($inherited) {
                audience_helper::set_selection((int) $child->id, $sectionselection);
            }
        }

        foreach (audience::get_records(['pageid' => $pageid]) as $record) {
            $record->delete();
        }
        $DB->delete_records('local_custompage_feedback', ['pageid' => $pageid]);
        context_helper::delete_instance(CONTEXT_CUSTOMPAGE, $pageid);
        $result = $page->delete();

        $transaction->allow_commit();
        audience_helper::purge_caches();
        return $result;
    }
}
