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
 * Library functions for local_custompage plugin.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

defined('MOODLE_INTERNAL') || die();

if (!defined('CONTEXT_CUSTOMPAGE')) {
    define('CONTEXT_CUSTOMPAGE', 75);
}

/**
 * Legacy after_config callback (Moodle 4.4). On 4.5+ the \core\hook\after_config hook is used instead
 * and core skips this function because a hook callback is registered.
 */
function local_custompage_after_config(): void {
    \local_custompage\hook_callbacks::register_context_level();
}

/**
 * Serve the plugin's files (page body images, documents, card images), applying the page's audience.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file is not found or not visible; otherwise the file is sent and the script ends.
 */
function local_custompage_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if (!in_array($filearea, ['content', 'documents', 'cardimage'], true) || $context->contextlevel != CONTEXT_CUSTOMPAGE) {
        return false;
    }

    $page = \local_custompage\local\models\page::get_record(['id' => $context->instanceid]);
    if (!$page) {
        return false;
    }
    \local_custompage\permission::require_login_for_page($page);
    if (!\local_custompage\permission::can_view_page($page)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_custompage', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}
