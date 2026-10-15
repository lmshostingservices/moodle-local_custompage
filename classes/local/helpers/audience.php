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

use cache;
use core_reportbuilder\local\helpers\database;
use local_custompage\constants;
use local_custompage\custompage\audience\allusers;
use local_custompage\custompage\audience\guests;
use local_custompage\custompage\audience\manual;
use local_custompage\custompage\audience\systemrole;
use local_custompage\local\audiences\base;
use local_custompage\local\models\audience as audience_model;
use stdClass;

/**
 * Audience rules: who can see which page.
 *
 * - A section, a page outside any section, or a page set to "choose who can see this page" is visible to the people
 *   matched by its own audiences (all logged-in users, guests and visitors, roles, chosen people).
 * - A page in a section that is set to "same as its section" is visible to the section's audience.
 * - A section is also visible to anyone who can see at least one of its pages (they only see those pages in it).
 * - With no audiences, only page managers can see it.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience {
    /**
     * Audience instances stored for a page.
     *
     * @param int $pageid
     * @return base[]
     */
    public static function get_base_records(int $pageid): array {
        $records = audience_model::get_records(['pageid' => $pageid], 'id');
        $instances = array_map(static function (audience_model $audience): ?base {
            return base::instance(0, $audience->to_record());
        }, $records);
        return array_values(array_filter($instances));
    }

    /**
     * IDs of the pages the user can see through audiences (page managers are handled in {@see \local_custompage\permission}).
     *
     * Cached per user; the cache is purged whenever a page or an audience changes.
     *
     * @param int $userid
     * @return int[]
     */
    public static function get_allowed_pages(int $userid): array {
        global $DB;

        $cache = cache::make('local_custompage', 'custompage_allowed_pages');
        $cached = $cache->get($userid);
        if ($cached !== false) {
            return $cached;
        }

        // Pages whose own audiences match the user.
        $ownmatch = [];
        $pageaudiences = [];
        foreach (audience_model::get_records() as $audience) {
            $pageaudiences[(int) $audience->get('pageid')][] = $audience;
        }
        foreach ($pageaudiences as $pageid => $audiences) {
            [$wheres, $params] = self::user_audience_sql($audiences);
            if (!$wheres) {
                continue;
            }
            $paramuserid = database::generate_param_name();
            $params[$paramuserid] = $userid;
            $sql = "SELECT 1
                      FROM {user} u
                     WHERE (" . implode(' OR ', $wheres) . ")
                       AND u.deleted = 0
                       AND u.id = :{$paramuserid}";
            if ($DB->record_exists_sql($sql, $params)) {
                $ownmatch[$pageid] = true;
            }
        }

        // Apply inheritance, then make sections visible through their visible pages.
        $pages = $DB->get_records('local_custompages', null, '', 'id, parent, iscontainer, audiencemode');
        $allowed = [];
        foreach ($pages as $page) {
            $inherits = !$page->iscontainer && !empty($page->parent)
                && (int) $page->audiencemode === constants::AUDIENCE_INHERIT;
            $source = $inherits ? (int) $page->parent : (int) $page->id;
            if (!empty($ownmatch[$source])) {
                $allowed[(int) $page->id] = true;
            }
        }
        foreach ($pages as $page) {
            if (!empty($page->parent) && !empty($allowed[(int) $page->id]) && isset($pages[$page->parent])) {
                $allowed[(int) $page->parent] = true;
            }
        }

        $result = array_map('intval', array_keys($allowed));
        sort($result);
        $cache->set($userid, $result);
        return $result;
    }

    /**
     * Purge the cache of pages each user can see.
     */
    public static function purge_caches(): void {
        cache::make('local_custompage', 'custompage_allowed_pages')->purge();
    }

    /**
     * SQL matching users in any of the given audiences.
     *
     * @param audience_model[] $audiences
     * @param string $usertablealias
     * @return array [string[] $wheres, array $params]
     */
    public static function user_audience_sql(array $audiences, string $usertablealias = 'u'): array {
        $wheres = $params = [];
        foreach ($audiences as $audience) {
            $instance = base::instance(0, $audience->to_record());
            if (!$instance || !$instance->is_configured()) {
                continue;
            }
            $alias = database::generate_alias();
            [$join, $where, $instanceparams] = $instance->get_sql($alias);
            $wheres[] = "{$usertablealias}.id IN (SELECT {$alias}.id FROM {user} {$alias} {$join} WHERE {$where})";
            $params += $instanceparams;
        }
        return [$wheres, $params];
    }

    /**
     * The audience settings of a page, in the shape the page form uses.
     *
     * @param int $pageid
     * @return stdClass {allusers: bool, guests: bool, roles: int[], users: int[]}
     */
    public static function get_selection(int $pageid): stdClass {
        $selection = (object) ['allusers' => false, 'guests' => false, 'roles' => [], 'users' => []];
        foreach (self::get_base_records($pageid) as $instance) {
            $config = $instance->get_configdata();
            if ($instance instanceof allusers) {
                $selection->allusers = true;
            } else if ($instance instanceof guests) {
                $selection->guests = true;
            } else if ($instance instanceof systemrole) {
                $selection->roles = array_merge($selection->roles, array_map('intval', $config['roles'] ?? []));
            } else if ($instance instanceof manual) {
                $selection->users = array_merge($selection->users, array_map('intval', $config['users'] ?? []));
            }
        }
        $selection->roles = array_values(array_unique($selection->roles));
        $selection->users = array_values(array_unique($selection->users));
        return $selection;
    }

    /**
     * Replace a page's audiences with the given selection.
     *
     * @param int $pageid
     * @param stdClass $selection {allusers: bool, guests: bool, roles: int[], users: int[]}
     */
    public static function set_selection(int $pageid, stdClass $selection): void {
        foreach (self::get_base_records($pageid) as $instance) {
            $instance->get_persistent()->delete();
        }
        if (!empty($selection->allusers)) {
            allusers::create($pageid, []);
        }
        if (!empty($selection->guests)) {
            guests::create($pageid, []);
        }
        $roles = array_values(array_filter(array_map('intval', (array) ($selection->roles ?? []))));
        if ($roles) {
            systemrole::create($pageid, ['roles' => $roles]);
        }
        $users = array_values(array_filter(array_map('intval', (array) ($selection->users ?? []))));
        if ($users) {
            manual::create($pageid, ['users' => $users]);
        }
        self::purge_caches();
    }

    /**
     * Plain-English description of who can see a page, for the management list.
     *
     * @param \local_custompage\local\models\page $page
     * @return string
     */
    public static function describe(\local_custompage\local\models\page $page): string {
        if ($page->inherits_audience()) {
            return get_string('audience_sameassection', 'local_custompage');
        }
        $selection = self::get_selection((int) $page->get('id'));
        $parts = [];
        if ($selection->allusers) {
            $parts[] = get_string('audience_allusers', 'local_custompage');
        }
        if ($selection->guests) {
            $parts[] = get_string('audience_guests', 'local_custompage');
        }
        if ($selection->roles) {
            $names = role_fix_names(get_all_roles(), \core\context\system::instance(), ROLENAME_ALIAS, true);
            $rolenames = array_intersect_key($names, array_flip($selection->roles));
            if ($rolenames) {
                $parts[] = get_string('audience_roles', 'local_custompage', implode(', ', $rolenames));
            }
        }
        if ($selection->users) {
            $n = count($selection->users);
            $parts[] = get_string($n === 1 ? 'audience_user' : 'audience_users', 'local_custompage', $n);
        }
        return $parts ? implode('; ', $parts) : get_string('audience_managersonly', 'local_custompage');
    }
}
