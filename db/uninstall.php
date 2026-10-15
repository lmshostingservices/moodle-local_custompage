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

use local_custompage\local\models\page;

/**
 * Uninstall steps for local_custompage.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute custompage uninstall.
 *
 * @return bool
 */
function xmldb_local_custompage_uninstall(): bool {

    global $DB, $CFG;
    require_once($CFG->dirroot . '/local/custompage/lib.php');
    // Each page's context goes with it: its blocks, role overrides and filter settings would otherwise stay behind
    // at a context level nothing defines any more.
    foreach (page::get_records() as $custompage) {
        try {
            \context_helper::delete_instance(CONTEXT_CUSTOMPAGE, (int) $custompage->get('id'));
        } catch (\Throwable $e) {
            $DB->delete_records('context', ['contextlevel' => CONTEXT_CUSTOMPAGE, 'instanceid' => $custompage->get('id')]);
        }
        $custompage->delete();
    }
    $DB->delete_records('local_custompage_feedback');

    return true;
}
