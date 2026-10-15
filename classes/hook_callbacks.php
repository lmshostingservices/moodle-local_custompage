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

namespace local_custompage;

/**
 * Hook callbacks for local_custompage.
 *
 * Adds custom pages to the primary navigation and registers the custom page context level.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add the current user's custom pages to the primary navigation.
     *
     * Top-level pages flagged "Show in primary navigation" are added; a container becomes a dropdown
     * listing an overview link plus the child pages the user can view.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (during_initial_install() || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }

        try {
            $pageids = permission::visible_page_ids();
            if (empty($pageids)) {
                return;
            }
            $hierarchy = \local_custompage\local\services\navigation::build_page_hierarchy($pageids);
            \local_custompage\local\services\navigation::add_to_primary_navigation(
                $hook->get_primaryview(),
                $hierarchy
            );
        } catch (\Throwable $e) {
            debugging('local_custompage: error adding navigation items: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Register custom context class after Moodle configuration is loaded (Moodle 4.5+ hook).
     *
     * On Moodle 4.4 the same registration is done by the legacy local_custompage_after_config() callback in lib.php.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config($hook): void {
        self::register_context_level();
    }

    /**
     * Register CONTEXT_CUSTOMPAGE with core.
     *
     * Core builds its context level list (context_helper) early in lib/setup.php, before after_config runs,
     * so the cached list must be reset after adding our level or it will not be recognised.
     */
    public static function register_context_level(): void {
        global $CFG;

        if (!defined('CONTEXT_CUSTOMPAGE')) {
            define('CONTEXT_CUSTOMPAGE', 75);
        }

        $levels = [];
        if (!empty($CFG->custom_context_classes)) {
            $levels = is_array($CFG->custom_context_classes)
                ? $CFG->custom_context_classes
                : (unserialize_array((string) $CFG->custom_context_classes) ?: []);
        }

        if (($levels[\CONTEXT_CUSTOMPAGE] ?? null) === custom_context\context_custompage::class) {
            return;
        }

        $levels[\CONTEXT_CUSTOMPAGE] = custom_context\context_custompage::class;
        $CFG->custom_context_classes = $levels;
        \core\context_helper::reset_levels();
    }
}
