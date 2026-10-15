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
 * Intranet home: every hub and page the visitor can open, and what changed recently.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\reader;

// Login is required below, unless the visitor is not logged in and some pages are open to guests.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

$PAGE->set_context(\core\context\system::instance());
$PAGE->set_url(new moodle_url('/local/custompage/home.php'));

// Visitors who are not logged in may see the home page when some pages are open to guests (unless login is forced).
if (isloggedin() || !empty($CFG->forcelogin) || !audience::get_allowed_pages((int) guest_user()->id)) {
    require_login(null, false);
}

$label = reader::home_label();
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('local-custompage-home');
$PAGE->set_title($label);
$PAGE->set_heading($label);
$PAGE->add_body_class('local-custompage-reader');
$PAGE->navbar->add($label);

$renderer = $PAGE->get_renderer('local_custompage');
echo $OUTPUT->header();
echo $renderer->home();
echo $OUTPUT->footer();
