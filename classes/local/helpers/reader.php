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

namespace local_custompage\local\helpers;

use local_custompage\local\models\page;
use local_custompage\permission;
use moodle_url;

/**
 * Where a page sits in the intranet and what to tell readers about it: its hub, its neighbours, how fresh it is.
 *
 * Everything here is filtered to what the visitor may open.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader {
    /** @var int Pages created or changed within this many days are marked New or Updated. */
    public const RECENT_DAYS = 14;

    /** @var int Reading speed used for "n min read". */
    public const WORDS_PER_MINUTE = 200;

    /**
     * Name of the intranet home page.
     *
     * @return string
     */
    public static function home_label(): string {
        $label = trim((string) get_config('local_custompage', 'homelabel'));
        return $label !== '' ? format_string($label) : get_string('home_defaultlabel', 'local_custompage');
    }

    /**
     * The hub a page belongs to, if the visitor can open it.
     *
     * @param page $page
     * @return page|null
     */
    public static function get_hub(page $page): ?page {
        $parentid = (int) $page->get('parent');
        if (!$parentid || !in_array($parentid, permission::visible_page_ids(), true)) {
            return null;
        }
        $hub = page::get_record(['id' => $parentid]);
        return $hub ?: null;
    }

    /**
     * Pages in a hub the visitor can open, in order.
     *
     * @param page $hub
     * @return page[]
     */
    public static function get_visible_children(page $hub): array {
        $visible = array_flip(permission::visible_page_ids());
        $isvisible = static fn(page $child): bool => isset($visible[(int) $child->get('id')]);
        return array_values(array_filter($hub->get_children(), $isvisible));
    }

    /**
     * Breadcrumb trail: intranet home, the hub, then the page itself (not linked).
     *
     * @param page $page
     * @return array[] Each with name, url (null for the current page)
     */
    public static function breadcrumb(page $page): array {
        $trail = [['name' => self::home_label(), 'url' => (new moodle_url('/local/custompage/home.php'))->out(false)]];
        if ($hub = self::get_hub($page)) {
            $trail[] = ['name' => $hub->get_formatted_name(), 'url' => $hub->get_url()->out(false)];
        }
        $trail[] = ['name' => $page->get_formatted_name(), 'url' => null];
        return $trail;
    }

    /**
     * Position of a page in its hub, and its previous and next neighbours.
     *
     * @param page $page
     * @param page[] $siblings Visible pages of the hub, in order
     * @return array [int $position (1-based, 0 if not found), ?page $previous, ?page $next]
     */
    public static function position(page $page, array $siblings): array {
        foreach ($siblings as $i => $sibling) {
            if ((int) $sibling->get('id') === (int) $page->get('id')) {
                return [$i + 1, $siblings[$i - 1] ?? null, $siblings[$i + 1] ?? null];
            }
        }
        return [0, null, null];
    }

    /**
     * Minutes it takes to read a page's text (0 when it is too short to be worth saying).
     *
     * @param page $page
     * @return int
     */
    public static function reading_minutes(page $page): int {
        $text = html_to_text((string) $page->get('content'), 0, false);
        $words = str_word_count($text);
        return $words < self::WORDS_PER_MINUTE ? 0 : (int) round($words / self::WORDS_PER_MINUTE);
    }

    /**
     * When the page last changed, in words: "today", "yesterday", "3 days ago", or a date.
     *
     * @param int $time
     * @param int|null $now
     * @return string
     */
    public static function relative_date(int $time, ?int $now = null): string {
        $now = $now ?? time();
        $days = (int) floor((usergetmidnight($now) - usergetmidnight($time)) / DAYSECS);
        if ($days <= 0) {
            return get_string('when_today', 'local_custompage');
        }
        if ($days === 1) {
            return get_string('when_yesterday', 'local_custompage');
        }
        if ($days < 7) {
            return get_string('when_daysago', 'local_custompage', $days);
        }
        return userdate($time, get_string('strftimedate', 'langconfig'));
    }

    /**
     * New or Updated, for pages that changed recently.
     *
     * @param page $page
     * @param int|null $now
     * @return string|null 'new', 'updated' or null
     */
    public static function badge(page $page, ?int $now = null): ?string {
        $now = $now ?? time();
        $recent = $now - self::RECENT_DAYS * DAYSECS;
        if ((int) $page->get('timecreated') >= $recent) {
            return 'new';
        }
        if ((int) $page->get('timemodified') >= $recent) {
            return 'updated';
        }
        return null;
    }

    /**
     * Whether a page is past its review date.
     *
     * @param page $page
     * @param int|null $now
     * @return bool
     */
    public static function review_overdue(page $page, ?int $now = null): bool {
        $reviewby = (int) $page->get('reviewby');
        return $reviewby > 0 && $reviewby < ($now ?? time());
    }

    /**
     * Full name of the page owner, if the owner still exists.
     *
     * @param page $page
     * @return string|null
     */
    public static function owner_name(page $page): ?string {
        $ownerid = (int) $page->get('ownerid');
        if (!$ownerid) {
            return null;
        }
        $owner = \core_user::get_user($ownerid);
        return ($owner && empty($owner->deleted)) ? fullname($owner) : null;
    }

    /**
     * What a card shows about a page's files: "PDF", "3 files", or nothing.
     *
     * @param page $page
     * @return string
     */
    public static function files_label(page $page): string {
        $files = $page->get_documents();
        if (!$files) {
            return '';
        }
        if (count($files) === 1) {
            $file = reset($files);
            $ext = strtoupper(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            return $ext !== '' ? $ext : get_string('nfiles', 'local_custompage', 1);
        }
        return get_string('nfiles', 'local_custompage', count($files));
    }
}
