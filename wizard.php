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
 * Create or edit a resource hub in three steps: details, resources, look.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_custompage\form\hub_look;
use local_custompage\form\hub_resource;
use local_custompage\form\hub_settings;
use local_custompage\local\helpers\hub;
use local_custompage\local\models\page;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$resourceid = optional_param('resource', 0, PARAM_INT);

require_login();
require_capability('local/custompage:manage', \core\context\system::instance());
$pageurl = new moodle_url('/local/custompage/wizard.php', ['id' => $id, 'step' => $step]);
admin_externalpage_setup('local_custompage_manage', '', null, $pageurl);

$hub = $id ? new page($id) : null;
if ($hub && !$hub->is_container()) {
    redirect($hub->get_url());
}
if (!$hub) {
    $step = 1;
}
$step = max(1, min(3, $step));
$hubid = $hub ? (int) $hub->get('id') : 0;
$self = fn(int $s, array $extra = []) => new moodle_url('/local/custompage/wizard.php', ['id' => $hubid, 'step' => $s] + $extra);
$manageurl = new moodle_url('/local/custompage/index.php');

if ($step === 1) {
    [$choice, $roles, $users] = $hub ? hub::get_audience_choice((int) $hub->get('id')) : ['all', [], []];
    $keep = $choice === 'keep' ? \local_custompage\local\helpers\audience::describe($hub) : '';
    $form = new hub_settings($self(1), ['keep' => $keep]);
    if ($hub) {
        $form->set_data([
            'id' => $hub->get('id'),
            'name' => $hub->get('name'),
            'title' => $hub->get('title'),
            'intro' => $hub->get('cardsummary'),
            'showinprimarynav' => $hub->get('showinprimarynav'),
            'audience' => $choice,
            'roles' => $roles,
            'users' => $users,
            'ownerid' => $hub->get('ownerid'),
            'reviewby' => $hub->get('reviewby'),
        ]);
    } else {
        $form->set_data(['ownerid' => $USER->id]);
    }
    if ($form->is_cancelled()) {
        redirect($hub ? $hub->get_url() : $manageurl);
    } else if ($data = $form->get_data()) {
        $hub = hub::save_settings($data, $hub);
        redirect(new moodle_url('/local/custompage/wizard.php', ['id' => $hub->get('id'), 'step' => 2]));
    }
} else if ($step === 2) {
    $resource = $resourceid ? new page($resourceid) : null;
    if ($resource && (int) $resource->get('parent') !== (int) $hub->get('id')) {
        $resource = null;
    }
    $context = $resource ? $resource->get_context() : \core\context\system::instance();
    $form = new hub_resource(
        $self(2, ['resource' => $resource ? $resource->get('id') : 0]),
        ['context' => $context, 'editing' => (bool) $resource,
            'hasdesign' => $resource && trim((string) $resource->get('designcss')) !== '']
    );
    $defaults = (object) ['id' => $hub->get('id'), 'step' => 2, 'resource' => $resource ? $resource->get('id') : 0,
        'content' => '', 'contentformat' => FORMAT_HTML, 'ownerid' => $USER->id];
    if ($resource) {
        $defaults->name = $resource->get('name');
        $defaults->cardsummary = $resource->get('cardsummary');
        $defaults->content = (string) $resource->get('content');
        $defaults->contentformat = (int) $resource->get('contentformat');
        $defaults->ownerid = $resource->get('ownerid');
        $defaults->reviewby = $resource->get('reviewby');
    }
    $defaults = file_prepare_standard_editor(
        $defaults,
        'content',
        \local_custompage\form\page::content_editor_options($context),
        $context,
        'local_custompage',
        'content',
        0
    );
    foreach (
        ['documents' => \local_custompage\form\page::documents_options(),
            'cardimage' => \local_custompage\form\page::card_image_options()] as $area => $options
    ) {
        $draftid = file_get_submitted_draft_itemid($area);
        file_prepare_draft_area($draftid, $resource ? $context->id : null, 'local_custompage', $area, 0, $options);
        $defaults->$area = $draftid;
    }
    $form->set_data($defaults);
    if ($form->is_cancelled()) {
        redirect($self(2));
    } else if ($data = $form->get_data()) {
        $saved = hub::save_resource($hub, $data, $resource);
        $message = get_string($resource ? 'resourceupdated' : 'resourceadded', 'local_custompage', $saved->get_formatted_name());
        redirect($self(2), $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
} else {
    $form = new hub_look($self(3), ['hascustom' => !empty($hub->get('designcsscompiled'))]);
    $form->set_data(['id' => $hub->get('id'), 'step' => 3, 'look' => $hub->get('look') ?: 'classic']);
    if ($form->is_cancelled()) {
        redirect($self(2));
    } else if ($data = $form->get_data()) {
        hub::save_look($hub, $data);
        redirect($hub->get_url(), get_string('hubready', 'local_custompage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$title = $hub ? get_string('edithub', 'local_custompage', $hub->get_formatted_name()) : get_string('createhub', 'local_custompage');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('managecustompages', 'local_custompage'));
$renderer = $PAGE->get_renderer('local_custompage');

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $renderer->wizard_steps($hub, $step);
if ($step === 2) {
    echo $renderer->hub_resources($hub, $resourceid);
    echo $OUTPUT->heading(get_string($resourceid ? 'editresource' : 'addaresource', 'local_custompage'), 3, 'mt-4');
}
$form->display();
if ($step === 2) {
    echo html_writer::div(
        html_writer::link($self(1), '← ' . get_string('back'), ['class' => 'btn btn-secondary mr-2 me-2']) .
        html_writer::link($self(3), get_string('nextchooselook', 'local_custompage') . ' →', ['class' => 'btn btn-primary']),
        'local-custompage-wizard-nav d-flex justify-content-end border-top pt-3 mt-4'
    );
}
echo $OUTPUT->footer();
