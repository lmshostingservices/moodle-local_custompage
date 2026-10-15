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

use stdClass;

/**
 * Standards a page addresses, for example "RTO Standards 2025, 1.11, Student management".
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class standards {
    /** @var string Table of standards. */
    public const TABLE = 'local_custompage_standard';

    /** @var int Empty rows shown in the form when a page has no standards yet. */
    public const FORM_ROWS = 1;

    /**
     * Add the repeating Framework / Number / Name rows to a form.
     *
     * @param \moodleform $form
     * @param \MoodleQuickForm $mform
     * @param int $existing How many standards the page has
     */
    public static function add_form_fields(\moodleform $form, \MoodleQuickForm $mform, int $existing): void {
        $mform->addElement('header', 'standardshdr', get_string('standards', 'local_custompage'));
        $mform->addElement('static', 'standardsintro', '', get_string('standards_intro', 'local_custompage'));
        $group = [
            $mform->createElement(
                'text',
                'std_framework',
                get_string('standard_framework', 'local_custompage'),
                ['size' => 22, 'maxlength' => 255, 'placeholder' => get_string('standard_framework_eg', 'local_custompage'),
                'list' => 'lcp-frameworks']
            ),
            $mform->createElement(
                'text',
                'std_code',
                get_string('standard_code', 'local_custompage'),
                ['size' => 6, 'maxlength' => 100, 'placeholder' => get_string('standard_code_eg', 'local_custompage')]
            ),
            $mform->createElement(
                'text',
                'std_name',
                get_string('standard_name', 'local_custompage'),
                ['size' => 26, 'maxlength' => 255, 'placeholder' => get_string('standard_name_eg', 'local_custompage')]
            ),
        ];
        $repeat = [
            $mform->createElement('group', 'std_group', get_string('standard', 'local_custompage'), $group, ' ', false),
        ];
        $form->repeat_elements(
            $repeat,
            max($existing, self::FORM_ROWS),
            [
                'std_framework' => ['type' => PARAM_TEXT],
                'std_code' => ['type' => PARAM_TEXT],
                'std_name' => ['type' => PARAM_TEXT],
            ],
            'std_repeats',
            'std_add',
            1,
            get_string('standard_add', 'local_custompage'),
            true
        );
        // Frameworks already used on the site, offered as suggestions while typing.
        $options = '';
        foreach (self::frameworks() as $framework) {
            $options .= \html_writer::tag('option', '', ['value' => $framework]);
        }
        $mform->addElement('html', \html_writer::tag('datalist', $options, ['id' => 'lcp-frameworks']));
    }

    /**
     * Form values for a page's standards.
     *
     * @param int $pageid
     * @return array
     */
    public static function form_data(int $pageid): array {
        $data = ['std_framework' => [], 'std_code' => [], 'std_name' => []];
        foreach (self::get_for_page($pageid) as $standard) {
            $data['std_framework'][] = $standard->framework;
            $data['std_code'][] = $standard->code;
            $data['std_name'][] = $standard->name;
        }
        $data['std_repeats'] = max(count($data['std_name']), self::FORM_ROWS);
        return $data;
    }

    /**
     * Save the standards entered in a form (rows left empty are dropped).
     *
     * @param int $pageid
     * @param stdClass $data
     */
    public static function save_from_form(int $pageid, stdClass $data): void {
        if (!property_exists($data, 'std_name') && !property_exists($data, 'std_framework')) {
            return;
        }
        $rows = [];
        $count = max(
            count((array) ($data->std_framework ?? [])),
            count((array) ($data->std_name ?? [])),
            count((array) ($data->std_code ?? []))
        );
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'framework' => (string) ($data->std_framework[$i] ?? ''),
                'code' => (string) ($data->std_code[$i] ?? ''),
                'name' => (string) ($data->std_name[$i] ?? ''),
            ];
        }
        self::set_for_page($pageid, $rows);
    }

    /**
     * Replace a page's standards.
     *
     * @param int $pageid
     * @param array[] $rows Each with framework, code, name
     */
    public static function set_for_page(int $pageid, array $rows): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['pageid' => $pageid]);
        $order = 0;
        foreach ($rows as $row) {
            $clean = [];
            foreach (['framework' => 255, 'code' => 100, 'name' => 255] as $field => $length) {
                $clean[$field] = \core_text::substr(trim(clean_param((string) ($row[$field] ?? ''), PARAM_TEXT)), 0, $length);
            }
            if ($clean['framework'] === '' && $clean['code'] === '' && $clean['name'] === '') {
                continue;
            }
            $DB->insert_record(self::TABLE, (object) ($clean + ['pageid' => $pageid, 'sortorder' => $order++]));
        }
    }

    /**
     * A page's standards in order.
     *
     * @param int $pageid
     * @return stdClass[]
     */
    public static function get_for_page(int $pageid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['pageid' => $pageid], 'sortorder ASC, id ASC'));
    }

    /**
     * Standards of many pages.
     *
     * @param int[] $pageids
     * @return stdClass[][] page id => standards in order
     */
    public static function get_for_pages(array $pageids): array {
        global $DB;
        if (!$pageids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($pageids)), SQL_PARAMS_NAMED);
        $result = [];
        foreach ($DB->get_records_select(self::TABLE, "pageid {$insql}", $params, 'pageid, sortorder ASC, id ASC') as $row) {
            $result[(int) $row->pageid][] = $row;
        }
        return $result;
    }

    /**
     * One standard as text: "RTO Standards 2025 1.11 Student management".
     *
     * @param stdClass $standard
     * @return string
     */
    public static function label(stdClass $standard): string {
        return trim(implode(' ', array_filter([trim((string) $standard->framework), trim((string) $standard->code),
            trim((string) $standard->name)], 'strlen')));
    }

    /**
     * A page's standards as one line of text, separated by semicolons.
     *
     * @param stdClass[] $standards
     * @return string
     */
    public static function labels(array $standards): string {
        return implode('; ', array_map([self::class, 'label'], $standards));
    }

    /**
     * Frameworks used on the site.
     *
     * @return string[]
     */
    public static function frameworks(): array {
        global $DB;
        $frameworks = $DB->get_fieldset_sql('SELECT DISTINCT framework FROM {' . self::TABLE . '} WHERE framework IS NOT NULL');
        $frameworks = array_values(array_filter(array_map('trim', $frameworks), 'strlen'));
        sort($frameworks, SORT_NATURAL | SORT_FLAG_CASE);
        return $frameworks;
    }

    /**
     * Every distinct standard on the site, for a filter: key "framework|code" => label.
     *
     * @param string $framework Only this framework
     * @return string[]
     */
    public static function options(string $framework = ''): array {
        global $DB;
        $options = [];
        foreach ($DB->get_records(self::TABLE, null, 'framework, code, name') as $row) {
            if ($framework !== '' && trim((string) $row->framework) !== $framework) {
                continue;
            }
            $key = self::key($row);
            if (!isset($options[$key])) {
                $options[$key] = self::label($row);
            }
        }
        uksort($options, static fn($a, $b) => strnatcasecmp($a, $b));
        return $options;
    }

    /**
     * Key identifying a standard across pages (framework and number, or name when there is no number).
     *
     * @param stdClass $standard
     * @return string
     */
    public static function key(stdClass $standard): string {
        $code = trim((string) $standard->code);
        return trim((string) $standard->framework) . '|' . ($code !== '' ? $code : trim((string) $standard->name));
    }

    /**
     * Pages that address a framework, or one standard.
     *
     * @param string $framework
     * @param string $standardkey As returned by key()
     * @return int[]|null Page ids, or null when not filtering
     */
    public static function page_filter(string $framework, string $standardkey): ?array {
        global $DB;
        if ($framework === '' && $standardkey === '') {
            return null;
        }
        $ids = [];
        foreach ($DB->get_records(self::TABLE) as $row) {
            if ($framework !== '' && trim((string) $row->framework) !== $framework) {
                continue;
            }
            if ($standardkey !== '' && self::key($row) !== $standardkey) {
                continue;
            }
            $ids[(int) $row->pageid] = true;
        }
        return array_keys($ids);
    }

    /**
     * Remove a page's standards (when the page is deleted).
     *
     * @param int $pageid
     */
    public static function delete_for_page(int $pageid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['pageid' => $pageid]);
    }
}
