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

namespace local_custompage;

use local_custompage\local\helpers\audience;

/**
 * Keeps page access up to date when a person's roles or account change.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A role was given or taken away: work out the person's pages again.
     *
     * @param \core\event\base $event role_assigned or role_unassigned
     */
    public static function role_changed(\core\event\base $event): void {
        audience::purge_user_cache((int) $event->relateduserid);
    }

    /**
     * An account was changed (for example suspended) or deleted: work out the person's pages again.
     *
     * @param \core\event\base $event user_updated or user_deleted
     */
    public static function user_changed(\core\event\base $event): void {
        audience::purge_user_cache((int) $event->objectid);
    }
}
