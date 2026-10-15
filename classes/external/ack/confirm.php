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

namespace local_custompage\external\ack;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_custompage\local\helpers\acknowledge;
use local_custompage\local\models\page;
use local_custompage\permission;

/**
 * Record that the current user has read a page ("I have read and understood this").
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
        ]);
    }

    /**
     * Record the confirmation.
     *
     * @param int $pageid
     * @return array
     */
    public static function execute(int $pageid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['pageid' => $pageid]);
        $page = page::get_record(['id' => $params['pageid']]);
        if (!$page) {
            throw new \moodle_exception('pagenotfound', 'local_custompage');
        }
        self::validate_context($page->get_context());
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('confirm');
        }
        permission::require_can_view_page($page);
        if (!acknowledge::is_required($page)) {
            throw new \moodle_exception('acknotrequired', 'local_custompage');
        }
        $record = acknowledge::confirm($page, (int) $USER->id);
        return [
            'timeconfirmed' => (int) $record->timecreated,
            'message' => get_string(
                'ack_done',
                'local_custompage',
                userdate((int) $record->timecreated, get_string('strftimedatetime', 'langconfig'))
            ),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timeconfirmed' => new external_value(PARAM_INT, 'When the page was confirmed'),
            'message' => new external_value(PARAM_TEXT, 'Message to show'),
        ]);
    }
}
