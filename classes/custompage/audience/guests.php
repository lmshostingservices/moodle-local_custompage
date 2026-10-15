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

namespace local_custompage\custompage\audience;

use core_reportbuilder\local\helpers\database;
use local_custompage\local\audiences\base;

/**
 * Audience: the guest user, which is also used for visitors who are not logged in.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guests extends base {
    /**
     * SQL selecting the guest user.
     *
     * @param string $usertablealias
     * @return array
     */
    public function get_sql(string $usertablealias): array {
        global $CFG;
        $guest = database::generate_param_name();
        return ["", "{$usertablealias}.id = :{$guest}", [$guest => (int) $CFG->siteguest]];
    }

    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string("audience_guests", "local_custompage");
    }
}
