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

namespace local_custompage;

use local_custompage\local\helpers\audience;
use local_custompage\local\models\page;

/**
 * Who can manage and who can see custom pages.
 *
 * Two rules only:
 * - People with local/custompage:manage (site admins, and the Manager role by default) can manage and see every page.
 * - Everyone else sees a page when they are in its audience (see {@see audience::get_allowed_pages()}).
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {
    /**
     * Whether the user can create, edit, reorder and delete pages.
     *
     * @param int|null $userid User ID, or the current user if omitted
     * @return bool
     */
    public static function can_manage(?int $userid = null): bool {
        return has_capability('local/custompage:manage', \core\context\system::instance(), $userid);
    }

    /**
     * Require that the current user can manage pages.
     *
     * @throws page_access_exception
     */
    public static function require_can_manage(): void {
        if (!self::can_manage()) {
            throw new page_access_exception('errormanage');
        }
    }

    /**
     * The user whose audience membership decides what a visitor sees: the user, or the guest user when not logged in.
     *
     * @param int|null $userid
     * @return int
     */
    public static function audience_user_id(?int $userid = null): int {
        global $USER;
        if ($userid) {
            return $userid;
        }
        return isloggedin() ? (int) $USER->id : (int) guest_user()->id;
    }

    /**
     * Whether the user can see the page.
     *
     * @param page $page
     * @param int|null $userid User ID, or the current visitor if omitted
     * @return bool
     */
    public static function can_view_page(page $page, ?int $userid = null): bool {
        if (self::can_manage($userid)) {
            return true;
        }
        return in_array((int) $page->get('id'), audience::get_allowed_pages(self::audience_user_id($userid)), true);
    }

    /**
     * Require that the current user can see the page.
     *
     * @param page $page
     * @throws page_access_exception
     */
    public static function require_can_view_page(page $page): void {
        if (!self::can_view_page($page)) {
            throw new page_access_exception('errorpageview');
        }
    }

    /**
     * Log the visitor in when the page needs it.
     *
     * Pages open to guests are shown to visitors who are not logged in (unless the site forces login);
     * any other page sends them to the login page.
     *
     * @param page $page
     */
    public static function require_login_for_page(page $page): void {
        global $CFG;
        if (
            !isloggedin() && empty($CFG->forcelogin)
                && in_array((int) $page->get('id'), audience::get_allowed_pages((int) guest_user()->id), true)
        ) {
            return;
        }
        require_login(null, false);
    }

    /**
     * IDs of every page the user can see.
     *
     * @param int|null $userid User ID, or the current visitor if omitted
     * @return int[]
     */
    public static function visible_page_ids(?int $userid = null): array {
        global $DB, $CFG;
        if (self::can_manage($userid)) {
            return array_map('intval', $DB->get_fieldset_select('local_custompages', 'id', '1 = 1'));
        }
        if (!$userid && !isloggedin() && !empty($CFG->forcelogin)) {
            // The site makes visitors log in first, so there is nothing to offer them yet.
            return [];
        }
        return audience::get_allowed_pages(self::audience_user_id($userid));
    }
}
