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

namespace local_custompage\local\helpers;

/**
 * "Was this page helpful?" answers: one per person per page, with an optional comment.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback {
    /** @var string Table name. */
    public const TABLE = 'local_custompage_feedback';

    /** @var int Longest comment kept. */
    public const MAX_COMMENT = 2000;

    /**
     * Record (or change) a person's answer for a page.
     *
     * @param int $pageid
     * @param int $userid
     * @param bool $helpful
     * @param string|null $comment Null leaves an earlier comment as it was; an empty string removes it
     * @return \stdClass The saved record
     */
    public static function save(int $pageid, int $userid, bool $helpful, ?string $comment = null): \stdClass {
        global $DB;
        $now = time();
        $record = $DB->get_record(self::TABLE, ['pageid' => $pageid, 'userid' => $userid]);
        if ($comment !== null) {
            $comment = \core_text::substr(trim(clean_param($comment, PARAM_TEXT)), 0, self::MAX_COMMENT);
        }
        if ($record) {
            $record->helpful = (int) $helpful;
            if ($comment !== null) {
                $record->comment = $comment === '' ? null : $comment;
            }
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
            return $record;
        }
        $record = (object) [
            'pageid' => $pageid,
            'userid' => $userid,
            'helpful' => (int) $helpful,
            'comment' => ($comment === null || $comment === '') ? null : $comment,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $record;
    }

    /**
     * A person's answer for a page, if they have given one.
     *
     * @param int $pageid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_answer(int $pageid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['pageid' => $pageid, 'userid' => $userid]) ?: null;
    }

    /**
     * Totals for every page that has answers.
     *
     * @return array pageid => (object) [helpful, total, comments]
     */
    public static function summaries(): array {
        global $DB;
        $sql = "SELECT pageid, SUM(helpful) AS helpful, COUNT(1) AS total,
                       SUM(CASE WHEN comment IS NULL THEN 0 ELSE 1 END) AS comments
                  FROM {" . self::TABLE . "}
              GROUP BY pageid";
        $out = [];
        foreach ($DB->get_records_sql($sql) as $row) {
            $out[(int) $row->pageid] = (object) ['helpful' => (int) $row->helpful, 'total' => (int) $row->total,
                'comments' => (int) $row->comments];
        }
        return $out;
    }

    /**
     * All answers for a page, newest first, with the person's name fields.
     *
     * @param int $pageid
     * @return \stdClass[]
     */
    public static function get_entries(int $pageid): array {
        global $DB;
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT f.*, {$fields}
                  FROM {" . self::TABLE . "} f
                  JOIN {user} u ON u.id = f.userid
                 WHERE f.pageid = :pageid
              ORDER BY f.timemodified DESC, f.id DESC";
        return array_values($DB->get_records_sql($sql, ['pageid' => $pageid]));
    }
}
