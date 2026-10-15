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

namespace local_custompage\event;

use moodle_url;

/**
 * Someone confirmed they have read a custom page.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *      - int pageid:  The page confirmed
 *      - int version: The page version confirmed
 * }
 */
class page_read_confirmed extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_custompage_ack';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpagereadconfirmed', 'local_custompage');
    }

    /**
     * What happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' confirmed reading version '{$this->other['version']}' of the custom page "
            . "with id '{$this->other['pageid']}'.";
    }

    /**
     * Relevant URL.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/local/custompage/view.php', ['id' => $this->other['pageid']]);
    }

    /**
     * Custom validations.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['pageid'], $this->other['version'])) {
            throw new \coding_exception('pageid and version must be set in other.');
        }
    }

    /**
     * Mapping for backup and restore (not backed up).
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_custompage_ack', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Mapping of other fields.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return false;
    }
}
