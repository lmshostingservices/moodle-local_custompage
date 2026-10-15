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

use local_custompage\constants;
use core_reportbuilder\local\helpers\database;
use local_custompage\local\models\audience as audience_model;
use local_custompage\local\models\page;
use stdClass;

/**
 * "I have read this" confirmations: who must confirm which pages, who has, and the evidence for an audit.
 *
 * A page needs confirming when it asks for it, or when its hub asks for every resource. Confirmations are kept per
 * version: asking everyone to confirm again raises the page's version, and earlier confirmations stay as history.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class acknowledge {
    /** @var string Table of confirmations. */
    public const TABLE = 'local_custompage_ack';

    /** @var string Confirmed the current version. */
    public const CURRENT = 'current';

    /** @var string Confirmed an earlier version only. */
    public const OUTDATED = 'outdated';

    /** @var string Not confirmed. */
    public const NONE = 'none';

    /**
     * Whether readers must confirm they have read this page.
     *
     * @param page $page
     * @return bool
     */
    public static function is_required(page $page): bool {
        global $DB;
        if ($page->is_container()) {
            return false;
        }
        if ($page->get('requireack')) {
            return true;
        }
        $parent = (int) $page->get('parent');
        return $parent && (bool) $DB->get_field('local_custompages', 'requireack', ['id' => $parent]);
    }

    /**
     * Records of every page that needs confirming, in menu order, with their hub's name.
     *
     * @param int[]|null $pageids Only these pages
     * @return stdClass[] id => record with hubname
     */
    public static function required_pages(?array $pageids = null): array {
        global $DB;
        $where = 'p.iscontainer = 0 AND (p.requireack = 1 OR h.requireack = 1)';
        $params = [];
        if ($pageids !== null) {
            if (!$pageids) {
                return [];
            }
            [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
            $where .= " AND p.id {$insql}";
        }
        return $DB->get_records_sql(
            "SELECT p.id, p.name, p.parent, p.audiencemode, p.ackversion, p.timemodified, p.timecreated, p.contextid,
                    p.sortthread, h.name AS hubname
               FROM {local_custompages} p
          LEFT JOIN {local_custompages} h ON h.id = p.parent
              WHERE {$where}
           ORDER BY p.sortthread ASC, p.id ASC",
            $params
        );
    }

    /**
     * The person's latest confirmation of each page.
     *
     * @param int[] $pageids
     * @param int $userid
     * @return stdClass[] page id => confirmation record
     */
    public static function latest_for_user(array $pageids, int $userid): array {
        global $DB;
        if (!$pageids || $userid <= 0) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $latest = [];
        foreach ($DB->get_records_select(self::TABLE, "userid = :userid AND pageid {$insql}", $params, 'version ASC') as $row) {
            $latest[(int) $row->pageid] = $row;
        }
        return $latest;
    }

    /**
     * Whether a confirmation covers the page's current version.
     *
     * @param stdClass|null $confirmation
     * @param int $version The page's current version
     * @return string One of the state constants
     */
    public static function state(?stdClass $confirmation, int $version): string {
        if (!$confirmation) {
            return self::NONE;
        }
        return (int) $confirmation->version >= $version ? self::CURRENT : self::OUTDATED;
    }

    /**
     * The person's state for each page that needs confirming (pages that don't are left out).
     *
     * @param page[] $pages
     * @param int $userid
     * @return string[] page id => state
     */
    public static function states(array $pages, int $userid): array {
        $required = self::required_pages(array_map(static fn(page $p) => (int) $p->get('id'), $pages));
        $latest = self::latest_for_user(array_keys($required), $userid);
        $states = [];
        foreach ($required as $id => $record) {
            $states[(int) $id] = self::state($latest[(int) $id] ?? null, (int) $record->ackversion);
        }
        return $states;
    }

    /**
     * Record that the person has read the page's current version.
     *
     * @param page $page
     * @param int $userid
     * @return stdClass The confirmation (an existing one when already confirmed)
     */
    public static function confirm(page $page, int $userid): stdClass {
        global $DB;
        $version = (int) $page->get('ackversion');
        $conditions = ['pageid' => (int) $page->get('id'), 'userid' => $userid, 'version' => $version];
        if ($existing = $DB->get_record(self::TABLE, $conditions)) {
            return $existing;
        }
        $record = (object) ($conditions + [
            'pagename' => \core_text::substr((string) $page->get('name'), 0, 255),
            'timecreated' => time(),
        ]);
        $record->id = $DB->insert_record(self::TABLE, $record);
        \local_custompage\event\page_read_confirmed::create([
            'context' => $page->get_context(),
            'objectid' => $record->id,
            'relateduserid' => $userid,
            'other' => ['pageid' => (int) $page->get('id'), 'version' => $version],
        ])->trigger();
        return $record;
    }

    /**
     * After a page is saved: ask everyone to confirm again when the editor ticked that.
     *
     * @param page $page
     * @param stdClass $data Form data
     * @param bool $existed Whether the page existed before this save
     */
    public static function after_save(page $page, stdClass $data, bool $existed): void {
        global $DB;
        if ($existed && !empty($data->askagain)) {
            $version = (int) $page->get('ackversion') + 1;
            // Saved straight to the table: the page was saved already, and this must not change its "updated" date again.
            $DB->set_field('local_custompages', 'ackversion', $version, ['id' => $page->get('id')]);
            $page->from_record((object) ['ackversion' => $version]);
        }
    }

    /**
     * SQL matching the people a page is meant for (its own audience, or its hub's when it uses the hub's).
     *
     * Suspended and deleted accounts and the guest user are left out.
     *
     * @param stdClass $record Page record with id, parent and audiencemode
     * @return array|null [string $where, array $params] for {user} u, or null when the page is for managers only
     */
    public static function audience_sql(stdClass $record): ?array {
        global $CFG;
        $source = (!empty($record->parent) && (int) $record->audiencemode === constants::AUDIENCE_INHERIT)
            ? (int) $record->parent : (int) $record->id;
        [$wheres, $params] = audience::user_audience_sql(audience_model::get_records(['pageid' => $source]));
        if (!$wheres) {
            return null;
        }
        $guest = database::generate_param_name();
        $params[$guest] = (int) $CFG->siteguest;
        return ['(' . implode(' OR ', $wheres) . ") AND u.deleted = 0 AND u.suspended = 0 AND u.id <> :{$guest}", $params];
    }

    /**
     * Rows for the report: every page that needs confirming against every person it is meant for.
     *
     * @param array $filters page (int), hub (int), role (int), status (string), search (string), framework (string),
     *     standard (string key), sort ('staff' or document order)
     * @return stdClass[] Rows with page, hub, version, user fields, roles, state, timeconfirmed, versionconfirmed
     */
    public static function report_rows(array $filters = []): array {
        global $DB;
        $pageids = self::filtered_page_ids($filters);
        $pages = self::required_pages($pageids);
        $pagestandards = standards::get_for_pages(array_keys($pages));
        if (!empty($filters['hub'])) {
            $pages = array_filter($pages, static fn($p) => (int) $p->parent === (int) $filters['hub']);
        }

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = [];
        $userroles = [];
        foreach ($pages as $record) {
            $audience = self::audience_sql($record);
            if (!$audience) {
                continue;
            }
            [$where, $params] = $audience;
            if (!empty($filters['role'])) {
                $where .= ' AND EXISTS (SELECT 1 FROM {role_assignments} rf WHERE rf.userid = u.id AND rf.roleid = :filterrole)';
                $params['filterrole'] = (int) $filters['role'];
            }
            if (trim((string) ($filters['search'] ?? '')) !== '') {
                $term = '%' . $DB->sql_like_escape(trim((string) $filters['search'])) . '%';
                $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
                $where .= ' AND (' . $DB->sql_like($fullname, ':s1', false, false) . ' OR '
                    . $DB->sql_like('u.email', ':s2', false, false) . ' OR '
                    . $DB->sql_like('u.department', ':s3', false, false) . ')';
                $params += ['s1' => $term, 's2' => $term, 's3' => $term];
            }
            $users = $DB->get_records_sql(
                "SELECT u.id, u.email, u.department, u.idnumber, {$namefields} FROM {user} u WHERE {$where}",
                $params
            );
            if (!$users) {
                continue;
            }
            $latest = [];
            foreach ($DB->get_records(self::TABLE, ['pageid' => $record->id], 'version ASC') as $ack) {
                $latest[(int) $ack->userid] = $ack;
            }
            foreach ($users as $user) {
                $ack = $latest[(int) $user->id] ?? null;
                $userroles[(int) $user->id] = true;
                $rows[] = (object) [
                    'pageid' => (int) $record->id,
                    'pagename' => format_string($record->name),
                    'hubname' => $record->hubname !== null ? format_string($record->hubname) : '',
                    'version' => (int) $record->ackversion,
                    'user' => $user,
                    'state' => self::state($ack, (int) $record->ackversion),
                    'timeconfirmed' => $ack ? (int) $ack->timecreated : 0,
                    'versionconfirmed' => $ack ? (int) $ack->version : 0,
                    'sortthread' => (string) $record->sortthread,
                    'standards' => standards::labels($pagestandards[(int) $record->id] ?? []),
                ];
            }
        }

        if (!empty($filters['status'])) {
            $rows = array_values(array_filter($rows, static fn($r) => $r->state === $filters['status']));
        }
        $roles = self::role_names(array_keys($userroles));
        foreach ($rows as $row) {
            $row->roles = $roles[(int) $row->user->id] ?? '';
        }
        $bystaff = ($filters['sort'] ?? '') === 'staff';
        usort($rows, static function ($a, $b) use ($bystaff): int {
            $doc = [$a->sortthread, $a->pageid] <=> [$b->sortthread, $b->pageid];
            $person = [\core_text::strtolower($a->user->lastname), \core_text::strtolower($a->user->firstname), $a->user->id]
                <=> [\core_text::strtolower($b->user->lastname), \core_text::strtolower($b->user->firstname), $b->user->id];
            return $bystaff ? ($person ?: $doc) : ($doc ?: $person);
        });
        return $rows;
    }

    /**
     * Pages chosen by the document and standards filters.
     *
     * @param array $filters page (int), framework (string), standard (string key)
     * @return int[]|null Page ids, or null when these filters are not used
     */
    private static function filtered_page_ids(array $filters): ?array {
        $ids = standards::page_filter((string) ($filters['framework'] ?? ''), (string) ($filters['standard'] ?? ''));
        if (!empty($filters['page'])) {
            $ids = $ids === null ? [(int) $filters['page']] : array_values(array_intersect($ids, [(int) $filters['page']]));
        }
        return $ids;
    }

    /**
     * Totals per page from report rows.
     *
     * @param stdClass[] $rows
     * @return array[] page id => [name, hub, version, total, current, outdated, none, percent]
     */
    public static function summary(array $rows): array {
        $summary = [];
        foreach ($rows as $row) {
            $s = &$summary[$row->pageid];
            $s = $s ?? ['pageid' => $row->pageid, 'name' => $row->pagename, 'hub' => $row->hubname, 'version' => $row->version,
                'standards' => $row->standards,
                'total' => 0, self::CURRENT => 0, self::OUTDATED => 0, self::NONE => 0];
            $s['total']++;
            $s[$row->state]++;
            unset($s);
        }
        foreach ($summary as &$s) {
            $s['percent'] = $s['total'] ? (int) floor(100 * $s[self::CURRENT] / $s['total']) : 0;
        }
        return $summary;
    }

    /**
     * Every confirmation ever made (all versions), newest first, for the history view and export.
     *
     * @param array $filters page, hub, role, search, framework, standard
     * @return stdClass[]
     */
    public static function history_rows(array $filters = []): array {
        global $DB;
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $where = ['u.deleted = 0'];
        $params = [];
        $pageids = self::filtered_page_ids($filters);
        if ($pageids !== null) {
            if (!$pageids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED, 'pg');
            $where[] = "a.pageid {$insql}";
            $params += $inparams;
        }
        if (!empty($filters['hub'])) {
            $where[] = 'p.parent = :hub';
            $params['hub'] = (int) $filters['hub'];
        }
        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {role_assignments} rf WHERE rf.userid = u.id AND rf.roleid = :filterrole)';
            $params['filterrole'] = (int) $filters['role'];
        }
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $term = '%' . $DB->sql_like_escape(trim((string) $filters['search'])) . '%';
            $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $where[] = '(' . $DB->sql_like($fullname, ':s1', false, false) . ' OR ' . $DB->sql_like('u.email', ':s2', false, false)
                . ' OR ' . $DB->sql_like('u.department', ':s3', false, false) . ')';
            $params += ['s1' => $term, 's2' => $term, 's3' => $term];
        }
        $records = $DB->get_records_sql(
            "SELECT a.id, a.pageid, a.version, a.timecreated, a.pagename AS namethen, p.name AS pagename, p.ackversion,
                    h.name AS hubname, u.id AS userid, u.email, u.department, u.idnumber, {$namefields}
               FROM {" . self::TABLE . "} a
               JOIN {user} u ON u.id = a.userid
          LEFT JOIN {local_custompages} p ON p.id = a.pageid
          LEFT JOIN {local_custompages} h ON h.id = p.parent
              WHERE " . implode(' AND ', $where) . "
           ORDER BY a.timecreated DESC, a.id DESC",
            $params
        );
        $roles = self::role_names(array_map(static fn($r) => (int) $r->userid, $records));
        $pagestandards = standards::get_for_pages(array_map(static fn($r) => (int) $r->pageid, $records));
        $rows = [];
        foreach ($records as $r) {
            $user = (object) ['id' => (int) $r->userid, 'email' => $r->email, 'department' => $r->department,
                'idnumber' => $r->idnumber];
            foreach (\core_user\fields::get_name_fields() as $field) {
                $user->$field = $r->$field ?? '';
            }
            $rows[] = (object) [
                'pageid' => (int) $r->pageid,
                'pagename' => format_string($r->pagename ?? $r->namethen ?? ''),
                'hubname' => $r->hubname !== null ? format_string($r->hubname) : '',
                'version' => (int) $r->version,
                'current' => $r->ackversion !== null && (int) $r->version >= (int) $r->ackversion,
                'user' => $user,
                'roles' => $roles[(int) $r->userid] ?? '',
                'standards' => standards::labels($pagestandards[(int) $r->pageid] ?? []),
                'timeconfirmed' => (int) $r->timecreated,
            ];
        }
        return $rows;
    }

    /**
     * Names of the roles each person holds anywhere on the site.
     *
     * @param int[] $userids
     * @return string[] user id => comma-separated role names
     */
    public static function role_names(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids) {
            return [];
        }
        $names = audience::selectable_roles();
        $held = [];
        foreach (array_chunk($userids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $rs = $DB->get_recordset_sql(
                "SELECT DISTINCT ra.userid, ra.roleid FROM {role_assignments} ra WHERE ra.userid {$insql}",
                $params
            );
            foreach ($rs as $ra) {
                if (isset($names[$ra->roleid])) {
                    $held[(int) $ra->userid][(int) $ra->roleid] = $names[$ra->roleid];
                }
            }
            $rs->close();
        }
        $result = [];
        foreach ($held as $userid => $roles) {
            ksort($roles);
            $result[$userid] = implode(', ', array_unique($roles));
        }
        return $result;
    }

    /**
     * Pages the person still has to confirm, among the pages meant for them.
     *
     * @param int $userid
     * @return stdClass[] Required page records not confirmed (or confirmed for an earlier version only)
     */
    public static function to_confirm(int $userid): array {
        $allowed = audience::get_allowed_pages($userid);
        $required = self::required_pages($allowed);
        $latest = self::latest_for_user(array_keys($required), $userid);
        return array_filter($required, fn($r) => self::state($latest[(int) $r->id] ?? null, (int) $r->ackversion)
            !== self::CURRENT);
    }

    /**
     * Remove a page's confirmations (when the page is deleted).
     *
     * @param int $pageid
     */
    public static function delete_for_page(int $pageid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['pageid' => $pageid]);
    }
}
