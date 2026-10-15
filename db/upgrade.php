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

/**
 * Upgrade steps for local_custompage.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_custompage upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_custompage_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062300) {
        upgrade_plugin_savepoint(true, 2026062300, 'local', 'custompage');
    }

    if ($oldversion < 2026101402) {
        // Add card summary used when a container page lists its child pages as cards.
        $table = new xmldb_table('local_custompages');
        $field = new xmldb_field('cardsummary', XMLDB_TYPE_TEXT, null, null, null, null, null, 'showinprimarynav');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101402, 'local', 'custompage');
    }

    if ($oldversion < 2026101500) {
        // 2.0: page body, audience inheritance, plain-text names.
        $table = new xmldb_table('local_custompages');
        $fields = [
            new xmldb_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null, 'cardsummary'),
            new xmldb_field('contentformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'content'),
            new xmldb_field('audiencemode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'contentformat'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Names and menu labels were stored as HTML from rich-text editors; keep them as plain text.
        // Sections and top-level pages keep their own audiences; pages in a section keep theirs only if they had any.
        $pages = $DB->get_recordset('local_custompages', null, '', 'id, name, title, parent, iscontainer');
        foreach ($pages as $page) {
            $update = (object) ['id' => $page->id];
            foreach (['name', 'title'] as $field) {
                $value = (string) $page->$field;
                if (strpos($value, '<') !== false || strpos($value, '&') !== false) {
                    $update->$field = trim(html_to_text($value, 0, false));
                }
            }
            $hasaudiences = $DB->record_exists('local_custompage_audience', ['pageid' => $page->id]);
            $update->audiencemode = ($page->iscontainer || empty($page->parent) || $hasaudiences) ? 1 : 0;
            $DB->update_record('local_custompages', $update);
        }
        $pages->close();

        // The site administrators audience is redundant: page managers always see every page.
        $DB->delete_records(
            'local_custompage_audience',
            ['classname' => 'local_custompage\\custompage\\audience\\admins']
        );

        upgrade_plugin_savepoint(true, 2026101500, 'local', 'custompage');
    }

    if ($oldversion < 2026101600) {
        // 2.1: per-page design CSS.
        $table = new xmldb_table('local_custompages');
        foreach (
            [
            new xmldb_field('designcss', XMLDB_TYPE_TEXT, null, null, null, null, null, 'audiencemode'),
            new xmldb_field('designcsscompiled', XMLDB_TYPE_TEXT, null, null, null, null, null, 'designcss'),
            ] as $field
        ) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026101600, 'local', 'custompage');
    }

    if ($oldversion < 2026101601) {
        // 2.1: built-in looks for resource hubs.
        $table = new xmldb_table('local_custompages');
        $field = new xmldb_field('look', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'designcsscompiled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101601, 'local', 'custompage');
    }

    if ($oldversion < 2026101700) {
        // 2.2: page owner and review date, and "Was this helpful?" feedback.
        $table = new xmldb_table('local_custompages');
        foreach (
            [
            new xmldb_field('ownerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'look'),
            new xmldb_field('reviewby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'ownerid'),
            ] as $field
        ) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('cuspage_own_ix', XMLDB_INDEX_NOTUNIQUE, ['ownerid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Existing pages are owned by whoever created them.
        $DB->execute('UPDATE {local_custompages} SET ownerid = usercreated WHERE ownerid IS NULL AND usercreated > 0');

        $table = new xmldb_table('local_custompage_feedback');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('helpful', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_custompages', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('pageuser', XMLDB_INDEX_UNIQUE, ['pageid', 'userid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101700, 'local', 'custompage');
    }

    return true;
}
