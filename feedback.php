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
 * What people said about a page ("Was this page helpful?").
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_custompage\local\helpers\feedback;
use local_custompage\local\models\page;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$pageid = required_param('id', PARAM_INT);
require_login();
require_capability('local/custompage:manage', \core\context\system::instance());
$pageurl = new moodle_url('/local/custompage/feedback.php', ['id' => $pageid]);
admin_externalpage_setup('local_custompage_manage', '', null, $pageurl);

$page = page::get_record(['id' => $pageid]);
if (!$page) {
    throw new moodle_exception('pagenotfound', 'local_custompage');
}
$name = $page->get_formatted_name();
$PAGE->set_title(get_string('feedback_for', 'local_custompage', $name));
$PAGE->navbar->add($name, $page->get_url());

$entries = feedback::get_entries($pageid);
$summary = feedback::summaries()[$pageid] ?? null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('feedback_for', 'local_custompage', $name));
$links = html_writer::link($page->get_url(), get_string('viewpage', 'local_custompage')) . ' &nbsp; '
    . html_writer::link(new moodle_url('/local/custompage/index.php'), get_string('managecustompages', 'local_custompage'));
echo html_writer::tag('p', $links);

if (!$entries) {
    echo $OUTPUT->notification(get_string('feedback_none', 'local_custompage'), 'info', false);
} else {
    echo html_writer::tag('p', get_string('feedback_summary', 'local_custompage', $summary), ['class' => 'lead']);
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('feedback_answer', 'local_custompage'),
        get_string('comment', 'local_custompage'),
        get_string('date'),
    ];
    $table->attributes['class'] = 'generaltable local-custompage-feedback-table';
    foreach ($entries as $entry) {
        $table->data[] = [
            fullname($entry),
            get_string($entry->helpful ? 'feedback_helpful' : 'feedback_nothelpful', 'local_custompage'),
            $entry->comment !== null ? nl2br(s($entry->comment)) : '',
            userdate($entry->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
