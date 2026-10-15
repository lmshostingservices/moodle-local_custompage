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
 * Audience: individually chosen users.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manual extends base {
    /**
     * Whether any user is chosen.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->get_configdata()["users"]);
    }

    /**
     * SQL selecting the chosen users.
     *
     * @param string $usertablealias
     * @return array
     */
    public function get_sql(string $usertablealias): array {
        global $DB;
        $users = array_map("intval", $this->get_configdata()["users"] ?? []);
        [$insql, $params] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, database::generate_param_name() . "_");
        return ["", "{$usertablealias}.id {$insql}", $params];
    }

    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string("audience_usersname", "local_custompage");
    }
}
