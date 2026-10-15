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

namespace local_custompage\output;

use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\feedback;
use local_custompage\local\helpers\reader;
use local_custompage\local\models\page;
use local_custompage\permission;
use moodle_url;
use plugin_renderer_base;

/**
 * Renderer for custom pages: the page itself, a section's cards, and the management list.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Buttons shown to page managers at the top of a page or section.
     *
     * @param page $page
     * @return string
     */
    public function page_actions(page $page): string {
        if (!permission::can_manage()) {
            return '';
        }
        $hubid = $page->is_container() ? (int) $page->get('id') : 0;
        return $this->render_from_template('local_custompage/page_actions', [
            'id' => $page->get('id'),
            'issection' => (bool) $hubid,
            'edithuburl' => $hubid ? (new moodle_url('/local/custompage/wizard.php', ['id' => $hubid, 'step' => 1]))->out(false)
                : null,
            'addresourceurl' => $hubid ? (new moodle_url('/local/custompage/wizard.php', ['id' => $hubid, 'step' => 2]))->out(false)
                : null,
            'manageurl' => (new moodle_url('/local/custompage/index.php'))->out(false),
        ]);
    }

    /**
     * A page: its body and documents (blocks are printed by the caller).
     *
     * @param page $page
     * @return string
     */
    public function page_body(page $page): string {
        $documents = [];
        $embedurl = null;
        foreach ($page->get_documents() as $file) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            if ($embedurl === null && $file->get_mimetype() === 'application/pdf') {
                $embedurl = $url->out(false);
            }
            $documents[] = [
                'name' => $file->get_filename(),
                'url' => $url->out(false),
                'downloadurl' => moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename(),
                    true
                )->out(false),
                'size' => display_size($file->get_filesize()),
                'icon' => $this->output->pix_icon(file_file_icon($file), '', 'moodle', ['class' => 'icon']),
            ];
        }
        $content = $page->get_formatted_content();
        return $this->render_from_template('local_custompage/page_body', $this->design_context($page) + [
            'content' => $content,
            'hascontent' => $content !== '',
            'documents' => $documents,
            'hasdocuments' => !empty($documents),
            'embedurl' => $embedurl,
            'isempty' => $content === '' && empty($documents),
            'canmanage' => permission::can_manage(),
        ]);
    }

    /**
     * Intranet home: hubs, single pages and recent changes the visitor can open.
     *
     * @return string
     */
    public function home(): string {
        global $DB;
        $visible = permission::visible_page_ids();
        $records = $visible ? $DB->get_records_list('local_custompages', 'id', $visible, 'sortthread') : [];
        $children = [];
        foreach ($records as $record) {
            if (!empty($record->parent)) {
                $children[(int) $record->parent][] = $record;
            }
        }
        $hubs = [];
        $pages = [];
        foreach ($records as $record) {
            if (!empty($record->parent)) {
                continue;
            }
            $page = new page(0, $record);
            $summary = $page->get_formatted_card_summary();
            $names = array_map(static fn($c) => format_string($c->name), $children[(int) $record->id] ?? []);
            $item = [
                'name' => $page->get_formatted_name(),
                'url' => $page->get_url()->out(false),
                'summary' => $summary,
                'filtertext' => trim(strip_tags($page->get_formatted_name() . ' ' . $summary . ' ' . implode(' ', $names))),
            ];
            if ($page->is_container()) {
                if (!$names) {
                    continue;
                }
                $hubs[] = $item + [
                    'look' => (string) $record->look ?: 'classic',
                    'count' => count($names),
                    'preview' => array_map(static fn($n) => ['name' => $n], array_slice($names, 0, 3)),
                    'more' => max(0, count($names) - 3),
                ];
            } else {
                $pages[] = $item;
            }
        }

        $recent = [];
        $leaves = array_filter($records, static fn($r) => empty($r->iscontainer));
        $changed = static fn($r): int => (int) ($r->timemodified ?: $r->timecreated);
        usort($leaves, static fn($a, $b) => $changed($b) <=> $changed($a));
        foreach (array_slice($leaves, 0, 6) as $record) {
            $page = new page(0, $record);
            $hub = !empty($record->parent) && isset($records[$record->parent]) ? $records[$record->parent] : null;
            $recent[] = [
                'name' => $page->get_formatted_name(),
                'url' => $page->get_url()->out(false),
                'hub' => $hub ? format_string($hub->name) : '',
                'updated' => reader::relative_date((int) ($record->timemodified ?: $record->timecreated)),
                'badge' => reader::badge($page) === 'new',
            ];
        }

        $intro = (string) get_config('local_custompage', 'homeintro');
        return $this->render_from_template('local_custompage/home', [
            'uniqid' => \html_writer::random_id('lcp'),
            'title' => reader::home_label(),
            'intro' => trim(strip_tags($intro)) !== ''
                ? format_text($intro, FORMAT_HTML, ['context' => \core\context\system::instance()]) : '',
            'hubs' => $hubs,
            'hashubs' => !empty($hubs),
            'pages' => $pages,
            'haspages' => !empty($pages),
            'recent' => $recent,
            'hasrecent' => count($leaves) > 1,
            'isempty' => !$hubs && !$pages,
            'canmanage' => permission::can_manage(),
            'manageurl' => (new moodle_url('/local/custompage/index.php'))->out(false),
            'showfilter' => count($hubs) + count($pages) >= 4,
        ]);
    }

    /**
     * A page as a reader sees it: breadcrumb, its hub's pages, freshness, the page, feedback and previous/next.
     *
     * @param page $page
     * @param string $blocks HTML of the page's block region (printed under the page)
     * @return string
     */
    public function reader(page $page, string $blocks = ''): string {
        global $USER;
        $hub = reader::get_hub($page);
        $siblings = $hub ? reader::get_visible_children($hub) : [];
        [$position, $previous, $next] = reader::position($page, $siblings);
        $link = static fn(page $p): array => ['name' => $p->get_formatted_name(), 'url' => $p->get_url()->out(false)];

        $nextlink = $next ? $link($next) : null;
        if ($hub && !$next) {
            // The last page leads back to the hub.
            $nextlink = ['name' => $hub->get_formatted_name(), 'url' => $hub->get_url()->out(false), 'isoverview' => true];
        }
        $updated = (int) ($page->get('timemodified') ?: $page->get('timecreated'));
        $canfeedback = isloggedin() && !isguestuser();
        $answer = $canfeedback ? feedback::get_answer((int) $page->get('id'), (int) $USER->id) : null;
        $overdue = permission::can_manage() && reader::review_overdue($page);

        $items = [];
        foreach ($siblings as $sibling) {
            $items[] = $link($sibling) + ['current' => (int) $sibling->get('id') === (int) $page->get('id')];
        }

        return $this->render_from_template('local_custompage/reader', [
            'uniqid' => \html_writer::random_id('lcp'),
            'look' => $hub ? ((string) $hub->get('look') ?: 'classic') : 'classic',
            'name' => $page->get_formatted_name(),
            'ownbanner' => trim((string) $page->get('designcsscompiled')) !== '',
            'crumbs' => reader::breadcrumb($page),
            'hassidebar' => (bool) $hub,
            'hubname' => $hub ? $hub->get_formatted_name() : '',
            'huburl' => $hub ? $hub->get_url()->out(false) : '',
            'position' => $position,
            'count' => count($siblings),
            'showfilter' => count($siblings) >= 8,
            'items' => $items,
            'updated' => reader::relative_date($updated),
            'updatediso' => date('Y-m-d', $updated),
            'updatedfull' => userdate($updated, get_string('strftimedatefullshort', 'langconfig')),
            'minutes' => reader::reading_minutes($page),
            'owner' => reader::owner_name($page),
            'overdue' => $overdue,
            'reviewdate' => $overdue ? userdate((int) $page->get('reviewby'), get_string('strftimedate', 'langconfig')) : '',
            'body' => $this->page_body($page),
            'blocks' => $blocks,
            'feedback' => $canfeedback ? [
                'pageid' => $page->get('id'),
                'answered' => (bool) $answer,
                'helpful' => $answer && $answer->helpful,
                'comment' => $answer ? (string) $answer->comment : '',
            ] : false,
            'haspager' => $previous || $nextlink,
            'prev' => $previous ? $link($previous) : null,
            'next' => $nextlink,
        ]);
    }

    /**
     * A section: its intro, then a card for each page in it that the visitor can see.
     *
     * @param page $section
     * @return string
     */
    public function section_body(page $section): string {
        $visible = array_flip(permission::visible_page_ids());
        $cards = [];
        $index = 0;
        foreach ($section->get_children() as $child) {
            if (!isset($visible[(int) $child->get('id')])) {
                continue;
            }
            $name = $child->get_formatted_name();
            $image = $child->get_card_image_url();
            $badge = reader::badge($child);
            $updated = (int) ($child->get('timemodified') ?: $child->get('timecreated'));
            $cards[] = [
                'id' => $child->get('id'),
                'name' => $name,
                'summary' => $child->get_formatted_card_summary(),
                'filtertext' => trim(strip_tags($name . ' ' . $child->get_formatted_card_summary())),
                'url' => $child->get_url()->out(false),
                'imageurl' => $image ? $image->out(false) : null,
                'initial' => \core_text::strtoupper(\core_text::substr(trim(strip_tags($name)), 0, 1)),
                'accent' => $index++ % 6,
                'isnew' => $badge === 'new',
                'isupdated' => $badge === 'updated',
                'files' => reader::files_label($child),
                'updated' => reader::relative_date($updated),
            ];
        }
        $intro = $section->get_formatted_content();
        $look = (string) ($section->get('look') ?: 'classic');
        $summary = $section->get_formatted_card_summary();
        return $this->render_from_template('local_custompage/section_body', $this->design_context($section) + [
            'intro' => $intro,
            'hasintro' => $intro !== '',
            'look' => $look,
            'hashero' => in_array($look, ['trainerspace', 'atelier', 'studio'], true),
            'uniqid' => \html_writer::random_id('lcp'),
            'crumbs' => reader::breadcrumb($section),
            'name' => $section->get_formatted_name(),
            'ownbanner' => $look === 'custom',
            'showfilter' => count($cards) >= 6,
            'heroeyebrow' => $section->get_formatted_name(),
            'herotitle' => $summary !== '' ? $summary : $section->get_formatted_name(),
            'summary' => $look === 'classic' ? $summary : '',
            'resourcecount' => count($cards),
            'cards' => $cards,
            'hascards' => !empty($cards),
            'canmanage' => permission::can_manage(),
            'id' => $section->get('id'),
        ]);
    }

    /**
     * The management list: sections with their pages, and pages outside any section.
     *
     * @return string
     */
    public function manage_list(): string {
        global $DB;
        $records = $DB->get_records('local_custompages', null, 'sortthread');
        $byparent = [];
        foreach ($records as $record) {
            $byparent[(int) $record->parent][] = $record;
        }

        $feedback = feedback::summaries();
        $item = function (\stdClass $record, bool $first, bool $last) use ($feedback): array {
            $page = new page(0, $record);
            $answers = $feedback[(int) $record->id] ?? null;
            $children = [];
            $hubid = $page->is_container() ? (int) $record->id : (int) $record->parent;
            $wizardparams = [
                'id' => $hubid,
                'step' => $page->is_container() ? 1 : 2,
                'resource' => $page->is_container() ? 0 : $record->id,
            ];
            $wizard = $hubid ? new moodle_url('/local/custompage/wizard.php', $wizardparams) : null;
            return [
                'id' => $record->id,
                'editurl' => $wizard ? $wizard->out(false) : null,
                'addurl' => $page->is_container() ? (new moodle_url('/local/custompage/wizard.php', ['id' => $record->id,
                    'step' => 2]))->out(false) : null,
                'name' => $page->get_formatted_name(),
                'url' => $page->get_url()->out(false),
                'issection' => $page->is_container(),
                'inmenu' => !empty($record->showinprimarynav) && empty($record->parent),
                'audience' => audience::describe($page),
                'owner' => reader::owner_name($page),
                'overdue' => reader::review_overdue($page),
                'reviewdate' => $record->reviewby
                    ? userdate((int) $record->reviewby, get_string('strftimedate', 'langconfig')) : '',
                'feedback' => $answers ? get_string('feedback_summary', 'local_custompage', $answers) : '',
                'feedbackurl' => $answers ? (new moodle_url('/local/custompage/feedback.php', ['id' => $record->id]))->out(false)
                    : null,
                'canmoveup' => !$first,
                'canmovedown' => !$last,
                'children' => $children,
                'haschildren' => false,
                'childcount' => 0,
            ];
        };

        $items = [];
        $roots = $byparent[0] ?? [];
        foreach (array_values($roots) as $i => $record) {
            $row = $item($record, $i === 0, $i === count($roots) - 1);
            $kids = $byparent[(int) $record->id] ?? [];
            foreach (array_values($kids) as $j => $kid) {
                $row['children'][] = $item($kid, $j === 0, $j === count($kids) - 1);
            }
            $row['haschildren'] = !empty($row['children']);
            $row['childcount'] = count($row['children']);
            $items[] = $row;
        }

        return $this->render_from_template('local_custompage/manage_list', [
            'createhuburl' => (new moodle_url('/local/custompage/wizard.php'))->out(false),
            'prompturl' => (new moodle_url('/local/custompage/prompt.php'))->out(false),
            'settingsurl' => has_capability('moodle/site:config', \core\context\system::instance())
                ? (new moodle_url('/admin/settings.php', ['section' => 'local_custompage']))->out(false) : null,
            'homeurl' => (new moodle_url('/local/custompage/home.php'))->out(false),
            'items' => $items,
            'hasitems' => !empty($items),
        ]);
    }

    /**
     * Template data for a page's design: its scoped CSS and wrapper class.
     *
     * @param page $page
     * @return array
     */
    private function design_context(page $page): array {
        return [
            'scopeclass' => \local_custompage\local\helpers\design::scope_class((int) $page->get('id')),
            'designcss' => (string) $page->get('designcsscompiled'),
        ];
    }

    /**
     * Step indicator for the hub wizard.
     *
     * @param page|null $hub
     * @param int $current
     * @return string
     */
    public function wizard_steps(?page $hub, int $current): string {
        $steps = [];
        foreach ([1 => 'step_details', 2 => 'step_resources', 3 => 'step_look'] as $number => $key) {
            $steps[] = [
                'number' => $number,
                'label' => get_string($key, 'local_custompage'),
                'current' => $number === $current,
                'done' => $number < $current,
                'url' => $hub && $number !== $current
                    ? (new moodle_url('/local/custompage/wizard.php', ['id' => $hub->get('id'), 'step' => $number]))->out(false)
                    : null,
            ];
        }
        return $this->render_from_template('local_custompage/wizard_steps', ['steps' => $steps]);
    }

    /**
     * Resources already in a hub (wizard step 2).
     *
     * @param page $hub
     * @param int $editingid Resource being edited
     * @return string
     */
    public function hub_resources(page $hub, int $editingid = 0): string {
        $children = array_values($hub->get_children());
        $rows = [];
        foreach ($children as $i => $child) {
            $files = count($child->get_documents());
            $rows[] = [
                'id' => $child->get('id'),
                'name' => $child->get_formatted_name(),
                'summary' => $child->get_formatted_card_summary(),
                'files' => $files ? get_string('nfiles', 'local_custompage', $files) : '',
                'viewurl' => $child->get_url()->out(false),
                'editurl' => (new moodle_url('/local/custompage/wizard.php', ['id' => $hub->get('id'), 'step' => 2,
                    'resource' => $child->get('id')]))->out(false),
                'editing' => (int) $child->get('id') === $editingid,
                'canmoveup' => $i > 0,
                'canmovedown' => $i < count($children) - 1,
                'audience' => $child->inherits_audience() ? '' : \local_custompage\local\helpers\audience::describe($child),
            ];
        }
        return $this->render_from_template('local_custompage/hub_resources', [
            'rows' => $rows,
            'hasrows' => !empty($rows),
        ]);
    }
}
