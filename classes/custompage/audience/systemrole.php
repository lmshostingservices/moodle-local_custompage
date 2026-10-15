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
 * Audience: users holding any of the chosen roles at site (system) level.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class systemrole extends base {
    /**
     * Whether any role is chosen.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->get_configdata()["roles"]);
    }

    /**
     * SQL selecting users with one of the roles assigned at system level.
     *
     * @param string $usertablealias
     * @return array
     */
    public function get_sql(string $usertablealias): array {
        global $DB;
        $roles = array_map("intval", $this->get_configdata()["roles"] ?? []);
        [$insql, $params] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, database::generate_param_name() . "_");
        [$ra] = database::generate_aliases(1);
        $ctx = database::generate_param_name();
        $params[$ctx] = \core\context\system::instance()->id;
        $join = "JOIN {role_assignments} {$ra} ON {$ra}.userid = {$usertablealias}.id";
        return [$join, "{$ra}.contextid = :{$ctx} AND {$ra}.roleid {$insql}", $params];
    }

    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string("audience_rolesname", "local_custompage");
    }
}
