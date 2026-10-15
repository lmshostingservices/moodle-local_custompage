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

namespace local_custompage\external\feedback;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_custompage\local\helpers\feedback;
use local_custompage\local\models\page;
use local_custompage\permission;

/**
 * Save a person's "Was this page helpful?" answer.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
            'helpful' => new external_value(PARAM_BOOL, 'Whether the page helped'),
            'comment' => new external_value(PARAM_TEXT, 'Optional comment; omit to keep an earlier one', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Save the answer.
     *
     * @param int $pageid
     * @param bool $helpful
     * @param string|null $comment
     * @return array
     */
    public static function execute(int $pageid, bool $helpful, ?string $comment = null): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['pageid' => $pageid, 'helpful' => $helpful, 'comment' => $comment]
        );
        $page = page::get_record(['id' => $params['pageid']]);
        if (!$page) {
            throw new \moodle_exception('pagenotfound', 'local_custompage');
        }
        self::validate_context($page->get_context());
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('feedback');
        }
        permission::require_can_view_page($page);
        feedback::save((int) $page->get('id'), (int) $USER->id, (bool) $params['helpful'], $params['comment']);
        return ['saved' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Whether the answer was saved'),
        ]);
    }
}
