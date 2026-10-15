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
 * View a custom page or section.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_custompage\event\custompage_viewed;
use local_custompage\local\models\page;
use local_custompage\manager;
use local_custompage\permission;

// Login is checked by permission::require_login_for_page(), which lets visitors see pages open to guests.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

$pageid = required_param('id', PARAM_INT);

$page = page::get_record(['id' => $pageid]);
if (!$page) {
    throw new moodle_exception('pagenotfound', 'local_custompage');
}
$context = $page->get_context();
$url = new moodle_url('/local/custompage/view.php', ['id' => $pageid]);

$PAGE->set_context($context);
$PAGE->set_url($url);
permission::require_login_for_page($page);
permission::require_can_view_page($page);

custompage_viewed::create_from_object($page->to_record(), $context)->trigger();

$issection = $page->is_container();
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('local-custompage-view');
$PAGE->set_subpage((string) $pageid);
$PAGE->set_title($page->get_formatted_name());
$PAGE->set_heading($page->get_formatted_name());
$PAGE->add_body_class($issection ? 'local-custompage-section-view' : 'local-custompage-page-view');
$editing = $PAGE->user_is_editing();
// The plugin draws its own title and breadcrumb (with the hub in it), so the theme's are hidden on these pages.
$PAGE->add_body_class('local-custompage-reader');
if (!$issection) {
    // Optional extra content blocks under the page body.
    $PAGE->blocks->add_region('content');
}
manager::setup_page_breadcrumb($page->get_breadcrumb());

$renderer = $PAGE->get_renderer('local_custompage');

echo $OUTPUT->header();
if ($editing) {
    // Editing buttons only appear while the page is in edit mode.
    echo $renderer->page_actions($page);
}
if ($issection) {
    echo $renderer->section_body($page);
} else {
    $blocks = $OUTPUT->addblockbutton('content') . $OUTPUT->custom_block_region('content');
    echo $renderer->reader($page, $blocks);
}
echo $OUTPUT->footer();
