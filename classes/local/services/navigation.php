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

namespace local_custompage\local\services;

use coding_exception;
use local_custompage\local\models\page as page_model;
use stdClass;

/**
 * Navigation-related helpers for local_custompage.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Build page hierarchy from a flat list of page IDs.
     *
     * @param array $pageids
     * @return array
     */
    public static function build_page_hierarchy(array $pageids): array {
        global $DB;

        if (empty($pageids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($pageids);
        $pages = $DB->get_records_select('local_custompages', "id $insql", $params, 'parent ASC, sortthread ASC');

        $hierarchy = [];
        $pagesbyparent = [];

        foreach ($pages as $page) {
            $parentid = $page->parent ?? 0;
            if (!isset($pagesbyparent[$parentid])) {
                $pagesbyparent[$parentid] = [];
            }
            $pagesbyparent[$parentid][] = $page;
        }

        if (isset($pagesbyparent[0])) {
            foreach ($pagesbyparent[0] as $rootpage) {
                $hierarchy[] = self::build_page_tree_recursive($rootpage, $pagesbyparent);
            }
        }

        return $hierarchy;
    }

    /**
     * Recursively build page tree from grouped pages by parent ID.
     *
     * @param stdClass $page
     * @param array $pagesByParent
     * @return array
     */
    public static function build_page_tree_recursive(stdClass $page, array $pagesbyparent): array {
        $node = [
            'page' => $page,
            'children' => [],
        ];

        if (isset($pagesbyparent[$page->id])) {
            foreach ($pagesbyparent[$page->id] as $childpage) {
                $node['children'][] = self::build_page_tree_recursive($childpage, $pagesbyparent);
            }
        }

        return $node;
    }

    /**
     * Whether the visitor should see the intranet home link in the main menu.
     *
     * With no roles chosen in the Intranet settings, everyone who can open a page sees it. Otherwise only people who
     * hold one of the chosen roles anywhere on the site, plus site administrators and people who manage pages.
     *
     * @return bool
     */
    public static function show_home_link(): bool {
        global $USER;
        $roles = array_filter(array_map('intval', explode(',', (string) get_config('local_custompage', 'homemenuroles'))));
        if (!$roles || is_siteadmin() || \local_custompage\permission::can_manage()) {
            return true;
        }
        return isloggedin() && !isguestuser()
            && \local_custompage\local\helpers\audience::user_has_any_role((int) $USER->id, $roles);
    }

    /**
     * Add custom pages to the primary navigation.
     *
     * Only top-level pages flagged "Show in primary navigation" are added. A container with visible
     * children becomes a dropdown (overview link first, then each child); an empty container is skipped.
     *
     * @param \core\navigation\views\primary $primary
     * @param array $hierarchy As returned by build_page_hierarchy()
     */
    public static function add_to_primary_navigation(\core\navigation\views\primary $primary, array $hierarchy): void {
        if ($hierarchy && get_config('local_custompage', 'homeinmenu') && self::show_home_link()) {
            // The intranet home, for anyone who can open at least one page.
            $primary->add(
                \local_custompage\local\helpers\reader::home_label(),
                new \moodle_url('/local/custompage/home.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_custompage_home'
            );
        }
        foreach ($hierarchy as $node) {
            $record = $node['page'];
            if (empty($record->showinprimarynav)) {
                continue;
            }

            $page = new page_model(0, $record);
            $url = new \moodle_url('/local/custompage/view.php', ['id' => $record->id]);
            $text = self::nav_text($page);

            if (!$page->is_container()) {
                $primary->add($text, $url, \navigation_node::TYPE_CUSTOM, null, 'local_custompage_' . $record->id);
                continue;
            }

            if (empty($node['children'])) {
                continue;
            }

            $parentnode = $primary->add($text, $url, \navigation_node::TYPE_CUSTOM, null, 'local_custompage_' . $record->id);
            $parentnode->add(
                get_string('overview', 'local_custompage'),
                $url,
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_custompage_' . $record->id . '_overview'
            );
            foreach ($node['children'] as $childnode) {
                $child = new page_model(0, $childnode['page']);
                $parentnode->add(
                    self::nav_text($child),
                    new \moodle_url('/local/custompage/view.php', ['id' => $childnode['page']->id]),
                    \navigation_node::TYPE_CUSTOM,
                    null,
                    'local_custompage_' . $childnode['page']->id
                );
            }
        }
    }

    /**
     * Plain text used for a page in navigation: its menu label, falling back to its name.
     *
     * @param page_model $page
     * @return string
     */
    private static function nav_text(page_model $page): string {
        return trim(strip_tags($page->get_menu_label()));
    }
}
