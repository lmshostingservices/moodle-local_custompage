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
 * Read confirmations: every document that asks staff to confirm, against every person it is meant for.
 *
 * Evidence for an audit: who confirmed which version and when, with filters and CSV, Excel and PDF exports.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_custompage\local\helpers\ack_export;
use local_custompage\local\helpers\acknowledge;
use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\reader;
use local_custompage\local\helpers\standards;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/custompage:viewconfirmations', \core\context\system::instance());

$filters = [
    'page' => optional_param('doc', 0, PARAM_INT),
    'hub' => optional_param('hub', 0, PARAM_INT),
    'framework' => trim(optional_param('framework', '', PARAM_TEXT)),
    'standard' => trim(optional_param('standard', '', PARAM_TEXT)),
    'role' => optional_param('role', 0, PARAM_INT),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'search' => trim(optional_param('q', '', PARAM_TEXT)),
    'sort' => optional_param('sort', 'document', PARAM_ALPHA),
];
$view = optional_param('view', 'status', PARAM_ALPHA) === 'history' ? 'history' : 'status';
$download = optional_param('download', '', PARAM_ALPHA);
$pagenum = optional_param('pg', 0, PARAM_INT);
$perpage = 100;
if (!in_array($filters['status'], ['', acknowledge::CURRENT, acknowledge::OUTDATED, acknowledge::NONE], true)) {
    $filters['status'] = '';
}

$params = array_filter([
    'doc' => $filters['page'], 'hub' => $filters['hub'], 'framework' => $filters['framework'],
    'standard' => $filters['standard'], 'role' => $filters['role'], 'status' => $filters['status'], 'q' => $filters['search'],
    'sort' => $filters['sort'] === 'staff' ? 'staff' : '', 'view' => $view === 'history' ? 'history' : '',
]);
$baseurl = new moodle_url('/local/custompage/confirmations.php', $params);
admin_externalpage_setup('local_custompage_confirmations', '', null, $baseurl);

$rows = $view === 'history' ? acknowledge::history_rows($filters) : acknowledge::report_rows($filters);

// Choices for the filters.
$documents = acknowledge::required_pages();
$docoptions = [0 => get_string('ackr_alldocuments', 'local_custompage')];
$huboptions = [0 => get_string('ackr_allhubs', 'local_custompage')];
foreach ($documents as $doc) {
    $hub = $doc->hubname !== null ? format_string($doc->hubname) : '';
    $docoptions[$doc->id] = reader::plain_text(($hub !== '' ? $hub . ' › ' : '') . format_string($doc->name));
    if ($doc->parent && $hub !== '') {
        $huboptions[(int) $doc->parent] = reader::plain_text($hub);
    }
}
$roleoptions = [0 => get_string('ackr_allroles', 'local_custompage')] + array_map('strval', audience::selectable_roles());
$frameworkoptions = ['' => get_string('ackr_allframeworks', 'local_custompage')];
foreach (standards::frameworks() as $framework) {
    $frameworkoptions[$framework] = $framework;
}
$standardoptions = ['' => get_string('ackr_allstandards', 'local_custompage')] + standards::options($filters['framework']);
$statusoptions = [
    '' => get_string('ackr_allstatuses', 'local_custompage'),
    acknowledge::CURRENT => ack_export::state_label(acknowledge::CURRENT),
    acknowledge::OUTDATED => ack_export::state_label(acknowledge::OUTDATED),
    acknowledge::NONE => ack_export::state_label(acknowledge::NONE),
];

// Filters in use, in words (for the PDF and the page).
$labels = [];
$named = [
    'doc' => [get_string('ackr_document', 'local_custompage'), $docoptions, $filters['page']],
    'hub' => [get_string('ackr_hub', 'local_custompage'), $huboptions, $filters['hub']],
    'framework' => [get_string('standard_framework', 'local_custompage'), $frameworkoptions, $filters['framework']],
    'standard' => [get_string('standard', 'local_custompage'), $standardoptions, $filters['standard']],
    'role' => [get_string('ackr_role', 'local_custompage'), $roleoptions, $filters['role']],
    'status' => [get_string('ackr_status', 'local_custompage'), $statusoptions, $filters['status']],
];
foreach ($named as [$label, $options, $value]) {
    if ($value && isset($options[$value])) {
        $labels[] = $label . ': ' . $options[$value];
    }
}
if ($filters['search'] !== '') {
    $labels[] = get_string('ackr_search', 'local_custompage') . ': ' . $filters['search'];
}

if ($download === 'csv' || $download === 'excel') {
    ack_export::download_data($download, $rows, $view);
    die();
}
if ($download === 'pdf') {
    ack_export::download_pdf($rows, $view, $labels);
    die();
}

// Page data.
$select = static function (string $name, array $options, $value): array {
    $list = [];
    foreach ($options as $key => $label) {
        $list[] = ['value' => $key, 'label' => $label, 'selected' => (string) $key === (string) $value];
    }
    return ['name' => $name, 'options' => $list];
};
$summary = [];
$totals = ['total' => 0, acknowledge::CURRENT => 0, acknowledge::OUTDATED => 0, acknowledge::NONE => 0];
if ($view === 'status') {
    foreach (acknowledge::summary($rows) as $s) {
        foreach (array_keys($totals) as $key) {
            $totals[$key] += $s[$key];
        }
        $summary[] = $s + [
            'name' => reader::plain_text($s['name']),
            'hub' => reader::plain_text($s['hub']),
            'url' => (new moodle_url($baseurl, ['doc' => $s['pageid'], 'pg' => 0]))->out(false),
            'notconfirmedurl' => (new moodle_url($baseurl, ['doc' => $s['pageid'], 'status' => acknowledge::NONE, 'pg' => 0]))
                ->out(false),
            'current' => $s[acknowledge::CURRENT],
            'outdated' => $s[acknowledge::OUTDATED],
            'none' => $s[acknowledge::NONE],
        ];
    }
}
$datefmt = get_string('strftimedatetimeshort', 'langconfig');
$detail = [];
foreach (array_slice($rows, $pagenum * $perpage, $perpage) as $row) {
    $detail[] = [
        'name' => fullname($row->user),
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $row->user->id]))->out(false),
        'email' => $row->user->email,
        'department' => (string) $row->user->department,
        'roles' => $row->roles,
        'document' => reader::plain_text($row->pagename),
        'docurl' => (new moodle_url('/local/custompage/view.php', ['id' => $row->pageid]))->out(false),
        'hub' => reader::plain_text($row->hubname),
        'standards' => $row->standards,
        'version' => $row->version,
        'state' => $row->state ?? '',
        'statelabel' => isset($row->state) ? ack_export::state_label($row->state) : '',
        'current' => !empty($row->current),
        'confirmed' => $row->timeconfirmed ? userdate($row->timeconfirmed, $datefmt) : '',
    ];
}
$percent = $totals['total'] ? (int) floor(100 * $totals[acknowledge::CURRENT] / $totals['total']) : 0;
$exports = [];
foreach (['csv' => 'ackr_csv', 'excel' => 'ackr_excel', 'pdf' => 'ackr_pdf'] as $format => $string) {
    $exports[] = ['url' => (new moodle_url($baseurl, ['download' => $format]))->out(false),
        'label' => get_string($string, 'local_custompage')];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_custompage/ack_report', [
    'actionurl' => (new moodle_url('/local/custompage/confirmations.php'))->out(false),
    'ishistory' => $view === 'history',
    'statusurl' => (new moodle_url($baseurl, ['view' => null, 'pg' => 0]))->out(false),
    'historyurl' => (new moodle_url($baseurl, ['view' => 'history', 'pg' => 0, 'status' => null]))->out(false),
    'resetaction' => (new moodle_url('/local/custompage/confirmations.php', $view === 'history' ? ['view' => 'history'] : []))
        ->out(false),
    'view' => $view === 'history' ? 'history' : '',
    'selects' => [
        ['label' => get_string('ackr_document', 'local_custompage')] + $select('doc', $docoptions, $filters['page']),
        ['label' => get_string('ackr_hub', 'local_custompage')] + $select('hub', $huboptions, $filters['hub']),
        ['label' => get_string('standard_framework', 'local_custompage')]
            + $select('framework', $frameworkoptions, $filters['framework']),
        ['label' => get_string('standard', 'local_custompage')] + $select('standard', $standardoptions, $filters['standard']),
        ['label' => get_string('ackr_role', 'local_custompage')] + $select('role', $roleoptions, $filters['role']),
    ],
    'statusselect' => $view === 'history' ? null
        : ['label' => get_string('ackr_status', 'local_custompage')] + $select('status', $statusoptions, $filters['status']),
    'sortselect' => ['label' => get_string('ackr_sort', 'local_custompage')] + $select('sort', [
        'document' => get_string('ackr_sort_document', 'local_custompage'),
        'staff' => get_string('ackr_sort_staff', 'local_custompage'),
    ], $filters['sort'] === 'staff' ? 'staff' : 'document'),
    'search' => $filters['search'],
    'filterlabels' => implode('; ', $labels),
    'exports' => $exports,
    'hassummary' => !empty($summary),
    'summary' => $summary,
    'total' => $totals['total'],
    'current' => $totals[acknowledge::CURRENT],
    'outdated' => $totals[acknowledge::OUTDATED],
    'none' => $totals[acknowledge::NONE],
    'percent' => $percent,
    'count' => count($rows),
    'hasrows' => !empty($detail),
    'rows' => $detail,
    'nodocuments' => !$documents,
    'manageurl' => (new moodle_url('/local/custompage/index.php'))->out(false),
    'canmanage' => \local_custompage\permission::can_manage(),
]);
echo $OUTPUT->paging_bar(count($rows), $pagenum, $perpage, $baseurl, 'pg');
echo $OUTPUT->footer();
