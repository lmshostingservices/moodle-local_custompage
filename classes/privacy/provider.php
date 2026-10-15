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

namespace local_custompage\privacy;

use core\context;
use stdClass;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_custompage\local\models\audience;
use local_custompage\local\helpers\acknowledge;
use local_custompage\local\helpers\feedback;
use local_custompage\local\models\page;

/**
 * Privacy Subsystem for local_custompage
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {
    /**
     * Returns metadata about the component
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(page::TABLE, [
            'name' => 'privacy:metadata:page:name',
            'title' => 'privacy:metadata:page:title',
            'usercreated' => 'privacy:metadata:page:usercreated',
            'usermodified' => 'privacy:metadata:page:usermodified',
            'timecreated' => 'privacy:metadata:page:timecreated',
            'timemodified' => 'privacy:metadata:page:timemodified',
            'ownerid' => 'privacy:metadata:page:ownerid',
        ], 'privacy:metadata:page');

        $collection->add_database_table(feedback::TABLE, [
            'userid' => 'privacy:metadata:feedback:userid',
            'helpful' => 'privacy:metadata:feedback:helpful',
            'comment' => 'privacy:metadata:feedback:comment',
            'timecreated' => 'privacy:metadata:feedback:timecreated',
            'timemodified' => 'privacy:metadata:feedback:timemodified',
        ], 'privacy:metadata:feedback');

        $collection->add_database_table(acknowledge::TABLE, [
            'userid' => 'privacy:metadata:ack:userid',
            'version' => 'privacy:metadata:ack:version',
            'pagename' => 'privacy:metadata:ack:pagename',
            'timecreated' => 'privacy:metadata:ack:timecreated',
        ], 'privacy:metadata:ack');

        $collection->add_database_table(audience::TABLE, [
            'classname' => 'privacy:metadata:audience:classname',
            'configdata' => 'privacy:metadata:audience:configdata',
            'heading' => 'privacy:metadata:audience:heading',
            'usercreated' => 'privacy:metadata:audience:usercreated',
            'usermodified' => 'privacy:metadata:audience:usermodified',
            'timecreated' => 'privacy:metadata:audience:timecreated',
            'timemodified' => 'privacy:metadata:audience:timemodified',
        ], 'privacy:metadata:audience');

        return $collection;
    }

    /**
     * Get export sub context for a page
     *
     * @param page $page
     * @return array
     */
    public static function get_export_subcontext(page $page): array {
        $pagenode = implode('-', [
            $page->get('id'),
            clean_filename($page->get_formatted_name()),
        ]);

        return [get_string('custompage', 'local_custompage'), $pagenode];
    }

    /**
     * Get the list of contexts that contain user information for the specified user
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Locate all contexts for page the user has created, or pages they have created audience for.
        $sql = '
            SELECT p.contextid
              FROM {' . page::TABLE . '} p
             WHERE (p.usercreated = ?
                 OR p.usermodified = ?
                 OR p.ownerid = ?
                 OR p.id IN (
                    SELECT a.pageid
                      FROM {' . audience::TABLE . '} a
                     WHERE a.usercreated = ? OR a.usermodified = ?
                    )
                 OR p.id IN (
                    SELECT f.pageid
                      FROM {' . feedback::TABLE . '} f
                     WHERE f.userid = ?
                    )
                 OR p.id IN (
                    SELECT k.pageid
                      FROM {' . acknowledge::TABLE . '} k
                     WHERE k.userid = ?
                    )
                   )';

        $contextlist->add_from_sql($sql, array_fill(0, 7, $userid));

        // Confirmations of pages deleted since (kept as audit history) belong to the site.
        global $DB;
        if (
            $DB->record_exists_sql('SELECT 1 FROM {' . acknowledge::TABLE . '} k
                LEFT JOIN {' . page::TABLE . '} p ON p.id = k.pageid WHERE k.userid = ? AND p.id IS NULL', [$userid])
        ) {
            $contextlist->add_system_context();
        }

        // Pages whose audience names the user.
        if ($pageids = array_keys(self::chosen_in_audiences($userid))) {
            global $DB;
            [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
            $contextlist->add_from_sql('SELECT p.contextid FROM {' . page::TABLE . '} p WHERE p.id ' . $insql, $params);
        }
        return $contextlist;
    }

    /**
     * Audiences that name people one by one, for the pages where they name the user (or anyone, without a user).
     *
     * @param int|null $userid
     * @param int|null $pageid
     * @return audience[][] page id => audiences
     */
    protected static function chosen_in_audiences(?int $userid, ?int $pageid = null): array {
        $conditions = ['classname' => \local_custompage\custompage\audience\manual::class];
        if ($pageid) {
            $conditions['pageid'] = $pageid;
        }
        $found = [];
        foreach (audience::get_records($conditions) as $record) {
            $users = array_map('intval', (array) (json_decode((string) $record->get('configdata'), true)['users'] ?? []));
            if ($userid === null ? $users : in_array($userid, $users, true)) {
                $found[(int) $record->get('pageid')][] = $record;
            }
        }
        return $found;
    }

    /**
     * Take people out of the audiences that name them.
     *
     * @param int[] $userids
     * @param int|null $pageid Only this page's audiences
     */
    protected static function remove_from_audiences(array $userids, ?int $pageid = null): void {
        $userids = array_map('intval', $userids);
        foreach (self::chosen_in_audiences(null, $pageid) as $records) {
            foreach ($records as $record) {
                $config = json_decode((string) $record->get('configdata'), true) ?: [];
                $users = array_map('intval', (array) ($config['users'] ?? []));
                $left = array_values(array_diff($users, $userids));
                if ($left !== $users) {
                    $config['users'] = $left;
                    $record->set('configdata', json_encode($config));
                    $record->update();
                }
            }
        }
        \local_custompage\local\helpers\audience::purge_caches();
    }

    /**
     * Get users in context
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $select = 'p.contextid = :contextid';
        $params = ['contextid' => $userlist->get_context()->id];

        // Users who have created pages.
        $sql = 'SELECT p.usercreated, p.usermodified
                 FROM {' . page::TABLE . '} p
                WHERE ' . $select;
        $userlist->add_from_sql('usercreated', $sql, $params);
        $userlist->add_from_sql('usermodified', $sql, $params);

        // Page owners.
        $sql = 'SELECT p.ownerid FROM {' . page::TABLE . '} p WHERE ' . $select . ' AND p.ownerid IS NOT NULL';
        $userlist->add_from_sql('ownerid', $sql, $params);

        // People who answered "Was this helpful?".
        $sql = 'SELECT f.userid
                  FROM {' . feedback::TABLE . '} f
                  JOIN {' . page::TABLE . '} p ON p.id = f.pageid
                 WHERE ' . $select;
        $userlist->add_from_sql('userid', $sql, $params);

        // Users who have created audiences.
        $sql = 'SELECT a.usercreated, a.usermodified
                  FROM {' . audience::TABLE . '} a
                  JOIN {' . page::TABLE . '} p ON p.id = a.pageid
                WHERE ' . $select;
        $userlist->add_from_sql('usercreated', $sql, $params);
        $userlist->add_from_sql('usermodified', $sql, $params);

        // People who confirmed reading the page.
        $sql = 'SELECT k.userid
                  FROM {' . acknowledge::TABLE . '} k
                  JOIN {' . page::TABLE . '} p ON p.id = k.pageid
                 WHERE ' . $select;
        $userlist->add_from_sql('userid', $sql, $params);

        // People named in the page's audience.
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT k.userid FROM {' . acknowledge::TABLE . '} k
                LEFT JOIN {' . page::TABLE . '} p ON p.id = k.pageid WHERE p.id IS NULL', []);
        }
        if ($context->contextlevel == CONTEXT_CUSTOMPAGE) {
            foreach (self::chosen_in_audiences(null, (int) $context->instanceid) as $records) {
                foreach ($records as $record) {
                    $userlist->add_users(array_map(
                        'intval',
                        (array) (json_decode((string) $record->get('configdata'), true)['users'] ?? [])
                    ));
                }
            }
        }
    }

    /**
     * Export all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        // We need to get all pages that the user has created, or pages they have created audience for.
        $select = '(usercreated = ? OR usermodified = ? OR ownerid = ? OR id IN (
            SELECT a.pageid
              FROM {' . audience::TABLE . '} a
             WHERE a.usercreated = ? OR a.usermodified = ?
        ) OR id IN (
            SELECT f.pageid
              FROM {' . feedback::TABLE . '} f
             WHERE f.userid = ?
        ) OR id IN (
            SELECT k.pageid
              FROM {' . acknowledge::TABLE . '} k
             WHERE k.userid = ?
        ))';
        $params = array_fill(0, 7, $user->id);

        $chosen = self::chosen_in_audiences((int) $user->id);
        $pages = page::get_records_select($select, $params);
        if ($chosen) {
            $pages = array_merge($pages, page::get_records_select('id IN (' . implode(',', array_map('intval', array_keys($chosen)))
                . ')'));
        }
        $contextids = array_map('intval', $contextlist->get_contextids());
        $done = [];
        foreach ($pages as $page) {
            if (isset($done[$page->get('id')]) || !in_array((int) $page->get('contextid'), $contextids, true)) {
                continue;
            }
            $done[$page->get('id')] = true;
            $subcontext = static::get_export_subcontext($page);

            self::export_page($subcontext, $page);

            $select = 'pageid = ? AND (usercreated = ? OR usermodified = ?)';
            $params = [$page->get('id'), $user->id, $user->id];

            // Audiences.
            if ($audiences = audience::get_records_select($select, $params)) {
                static::export_audiences($page->get_context(), $subcontext, $audiences);
            }

            // Named in the page's audience.
            if (!empty($chosen[(int) $page->get('id')])) {
                writer::with_context($page->get_context())->export_related_data(
                    $subcontext,
                    'audience_membership',
                    (object) ['chosen' => transform::yesno(true)]
                );
            }

            // Their read confirmations (every version).
            self::export_confirmations($page->get_context(), $subcontext, (int) $user->id, (int) $page->get('id'));

            // Their "Was this helpful?" answer.
            global $DB;
            if ($answer = $DB->get_record(feedback::TABLE, ['pageid' => $page->get('id'), 'userid' => $user->id])) {
                writer::with_context($page->get_context())->export_related_data($subcontext, 'feedback', (object) [
                    'helpful' => transform::yesno($answer->helpful),
                    'comment' => $answer->comment,
                    'timecreated' => transform::datetime($answer->timecreated),
                    'timemodified' => transform::datetime($answer->timemodified),
                ]);
            }
        }
        // Confirmations of pages deleted since.
        if (in_array(\core\context\system::instance()->id, array_map('intval', $contextlist->get_contextids()), true)) {
            global $DB;
            $orphans = $DB->get_records_sql('SELECT k.* FROM {' . acknowledge::TABLE . '} k
                LEFT JOIN {' . page::TABLE . '} p ON p.id = k.pageid WHERE k.userid = ? AND p.id IS NULL', [$user->id]);
            if ($orphans) {
                writer::with_context(\core\context\system::instance())->export_related_data(
                    [get_string('custompage', 'local_custompage')],
                    'confirmations',
                    (object) ['confirmations' => array_values(array_map([self::class, 'confirmation_data'], $orphans))]
                );
            }
        }
    }

    /**
     * Delete data for all users in context
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        // Pages are site content and stay; people's own answers and ownership are removed.
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->execute('DELETE FROM {' . acknowledge::TABLE . '} WHERE pageid NOT IN (SELECT id FROM {' . page::TABLE . '})');
            return;
        }
        if ($context->contextlevel != CONTEXT_CUSTOMPAGE) {
            return;
        }
        $DB->delete_records(feedback::TABLE, ['pageid' => $context->instanceid]);
        $DB->delete_records(acknowledge::TABLE, ['pageid' => $context->instanceid]);
        $DB->set_field(page::TABLE, 'ownerid', null, ['id' => $context->instanceid]);
        foreach (self::chosen_in_audiences(null, (int) $context->instanceid) as $records) {
            foreach ($records as $record) {
                $config = json_decode((string) $record->get('configdata'), true) ?: [];
                $config['users'] = [];
                $record->set('configdata', json_encode($config));
                $record->update();
            }
        }
        \local_custompage\local\helpers\audience::purge_caches();
    }

    /**
     * Delete data for user
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->execute('DELETE FROM {' . acknowledge::TABLE . '} WHERE userid = ? AND pageid NOT IN (SELECT id FROM {'
                    . page::TABLE . '})', [$userid]);
                continue;
            }
            if ($context->contextlevel != CONTEXT_CUSTOMPAGE) {
                continue;
            }
            $DB->delete_records(feedback::TABLE, ['pageid' => $context->instanceid, 'userid' => $userid]);
            $DB->delete_records(acknowledge::TABLE, ['pageid' => $context->instanceid, 'userid' => $userid]);
            $DB->set_field(page::TABLE, 'ownerid', null, ['id' => $context->instanceid, 'ownerid' => $userid]);
            self::remove_from_audiences([$userid], (int) $context->instanceid);
        }
    }

    /**
     * Delete data for users
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$userids = $userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->execute('DELETE FROM {' . acknowledge::TABLE . "} WHERE userid {$insql} AND pageid NOT IN (SELECT id FROM {"
                . page::TABLE . '})', $params);
            return;
        }
        if ($context->contextlevel != CONTEXT_CUSTOMPAGE) {
            return;
        }
        $params['pageid'] = $context->instanceid;
        $DB->delete_records_select(feedback::TABLE, "pageid = :pageid AND userid {$insql}", $params);
        $DB->delete_records_select(acknowledge::TABLE, "pageid = :pageid AND userid {$insql}", $params);
        $DB->set_field_select(page::TABLE, 'ownerid', null, "id = :pageid AND ownerid {$insql}", $params);
        self::remove_from_audiences($userids, (int) $context->instanceid);
    }

    /**
     * A confirmation as export data.
     *
     * @param stdClass $record
     * @return stdClass
     */
    protected static function confirmation_data(stdClass $record): stdClass {
        return (object) [
            'page' => format_string((string) $record->pagename),
            'version' => (int) $record->version,
            'timeconfirmed' => transform::datetime($record->timecreated),
        ];
    }

    /**
     * Export a person's confirmations of a page.
     *
     * @param context $context
     * @param array $subcontext
     * @param int $userid
     * @param int $pageid
     */
    protected static function export_confirmations(context $context, array $subcontext, int $userid, int $pageid): void {
        global $DB;
        $records = $DB->get_records(acknowledge::TABLE, ['pageid' => $pageid, 'userid' => $userid], 'version ASC');
        if ($records) {
            writer::with_context($context)->export_related_data(
                $subcontext,
                'confirmations',
                (object) ['confirmations' => array_values(array_map([self::class, 'confirmation_data'], $records))]
            );
        }
    }

    /**
     * Export given page in context
     *
     * @param array $subcontext
     * @param page $page
     */
    protected static function export_page(array $subcontext, page $page): void {

        $pagedata = (object) [
            'name' => $page->get_formatted_name(),
            'usercreated' => transform::user($page->get('usercreated')),
            'usermodified' => transform::user($page->get('usermodified')),
            'timecreated' => transform::datetime($page->get('timecreated')),
            'timemodified' => transform::datetime($page->get('timemodified')),
        ];

        writer::with_context($page->get_context())->export_data($subcontext, $pagedata);
    }

    /**
     * Export given audiences in context
     *
     * @param context $context
     * @param array $subcontext
     * @param audience[] $audiences
     */
    protected static function export_audiences(context $context, array $subcontext, array $audiences): void {
        $audiencedata = array_map(static function (audience $audience) use ($context): stdClass {
            // Show the audience name, if it exists.
            $classname = $audience->get('classname');
            if (class_exists($classname)) {
                $classname = $classname::instance()->get_name();
            }

            return (object) [
                'classname' => $classname,
                'heading' => $audience->get_formatted_heading($context),
                'configdata' => $audience->get('configdata'),
                'usercreated' => transform::user($audience->get('usercreated')),
                'usermodified' => transform::user($audience->get('usermodified')),
                'timecreated' => transform::datetime($audience->get('timecreated')),
                'timemodified' => transform::datetime($audience->get('timemodified')),
            ];
        }, $audiences);

        writer::with_context($context)->export_related_data($subcontext, 'audiences', (object) ['data' => $audiencedata]);
    }
}
