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
                   )';

        return $contextlist->add_from_sql($sql, array_fill(0, 6, $userid));
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
        ))';
        $params = array_fill(0, 6, $user->id);

        foreach (page::get_records_select($select, $params) as $page) {
            $subcontext = static::get_export_subcontext($page);

            self::export_page($subcontext, $page);

            $select = 'pageid = ? AND (usercreated = ? OR usermodified = ?)';
            $params = [$page->get('id'), $user->id, $user->id];

            // Audiences.
            if ($audiences = audience::get_records_select($select, $params)) {
                static::export_audiences($page->get_context(), $subcontext, $audiences);
            }

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
    }

    /**
     * Delete data for all users in context
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        // Pages are site content and stay; people's own answers and ownership are removed.
        if ($context->contextlevel != CONTEXT_CUSTOMPAGE) {
            return;
        }
        $DB->delete_records(feedback::TABLE, ['pageid' => $context->instanceid]);
        $DB->set_field(page::TABLE, 'ownerid', null, ['id' => $context->instanceid]);
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
            if ($context->contextlevel != CONTEXT_CUSTOMPAGE) {
                continue;
            }
            $DB->delete_records(feedback::TABLE, ['pageid' => $context->instanceid, 'userid' => $userid]);
            $DB->set_field(page::TABLE, 'ownerid', null, ['id' => $context->instanceid, 'ownerid' => $userid]);
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
        if ($context->contextlevel != CONTEXT_CUSTOMPAGE || !$userids = $userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['pageid'] = $context->instanceid;
        $DB->delete_records_select(feedback::TABLE, "pageid = :pageid AND userid {$insql}", $params);
        $DB->set_field_select(page::TABLE, 'ownerid', null, "id = :pageid AND ownerid {$insql}", $params);
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
