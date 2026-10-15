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
 * Audience: every logged-in user (not the guest user).
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allusers extends base {
    /**
     * SQL selecting active, non-guest users.
     *
     * @param string $usertablealias
     * @return array
     */
    public function get_sql(string $usertablealias): array {
        global $CFG;
        $guest = database::generate_param_name();
        return ["", "{$usertablealias}.suspended = 0 AND {$usertablealias}.id <> :{$guest}", [$guest => (int) $CFG->siteguest]];
    }

    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string("audience_allusers", "local_custompage");
    }
}
