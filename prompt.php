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
 * Download the AI prompt for designing a page that can be uploaded as a design.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_capability('local/custompage:manage', context_system::instance());

$prompt = file_get_contents(__DIR__ . '/chatgpt-prompt.md');

// List this site's pages so an index page can link to them.
$lines = [];
foreach ($DB->get_records('local_custompages', null, 'sortthread') as $record) {
    $page = new \local_custompage\local\models\page(0, $record);
    $lines[] = '- ' . html_to_text($page->get_formatted_name(), 0, false) . ': ' . $page->get_url()->out(false)
        . ' (' . \local_custompage\local\helpers\audience::describe($page) . ')';
}
$pagelist = $lines ? implode("\n", $lines) : '- (no pages yet)';
$prompt = str_replace('[[PAGELIST]]', $pagelist, $prompt);

send_file($prompt, 'custom-page-design-prompt.md', 0, 0, true, true, 'text/markdown');
