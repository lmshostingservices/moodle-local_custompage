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

namespace local_custompage\local\audiences;

use local_custompage\local\models\audience;
use stdClass;

/**
 * Audience type base class: decides which users belong to one audience of a page.
 *
 * @package     local_custompage
 * @copyright   2021 David Matamoros <davidmc@moodle.com>
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var audience The stored audience record. */
    protected audience $audience;

    /**
     * Load an audience type instance from a stored record.
     *
     * @param int $id
     * @param stdClass|null $record
     * @return self|null Null when the record names a class that is not a concrete audience type.
     */
    final public static function instance(int $id = 0, ?stdClass $record = null): ?self {
        $persistent = new audience($id, $record);
        if (!$classname = $persistent->get('classname')) {
            $classname = get_called_class();
            $persistent->set('classname', $classname);
        }
        if (
            !class_exists($classname) || !is_subclass_of($classname, self::class)
                || (new \ReflectionClass($classname))->isAbstract()
        ) {
            return null;
        }
        $instance = new $classname();
        $instance->audience = $persistent;
        return $instance;
    }

    /**
     * Create and save an audience of this type for a page.
     *
     * @param int $pageid
     * @param array $configdata
     * @return self
     */
    final public static function create(int $pageid, array $configdata): self {
        $record = (object) [
            'pageid' => $pageid,
            'classname' => get_called_class(),
            'configdata' => json_encode($configdata),
        ];
        $instance = self::instance(0, $record);
        $instance->audience->save();
        return $instance;
    }

    /**
     * SQL selecting the users in this audience.
     *
     * @param string $usertablealias
     * @return array [$join, $where, $params]
     */
    abstract public function get_sql(string $usertablealias): array;

    /**
     * Name of this audience type.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Whether the audience has the settings it needs to match anyone.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return true;
    }

    /**
     * Stored settings of this audience.
     *
     * @return array
     */
    final public function get_configdata(): array {
        return json_decode((string) $this->audience->get('configdata'), true) ?: [];
    }

    /**
     * The stored audience record.
     *
     * @return audience
     */
    final public function get_persistent(): audience {
        return $this->audience;
    }
}
