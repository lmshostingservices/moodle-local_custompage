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

namespace local_custompage;

use advanced_testcase;
use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\page as pagehelper;
use local_custompage\local\models\page;

/**
 * Who can see which page: audiences, inheritance from the section, managers, guests.
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\permission
 * @covers     \local_custompage\local\helpers\audience
 * @covers     \local_custompage\local\helpers\page
 */
final class access_test extends advanced_testcase {
    /**
     * Create a section or page with the given audience.
     *
     * @param string $name
     * @param array $options parent, section (bool), mode, allusers, guests, roles, users
     * @return page
     */
    private function make(string $name, array $options = []): page {
        $page = pagehelper::create_page((object) [
            'name' => $name,
            'iscontainer' => (int) !empty($options['section']),
            'parent' => $options['parent'] ?? null,
            'audiencemode' => $options['mode'] ?? constants::AUDIENCE_OWN,
        ]);
        audience::set_selection((int) $page->get('id'), (object) [
            'allusers' => !empty($options['allusers']),
            'guests' => !empty($options['guests']),
            'roles' => $options['roles'] ?? [],
            'users' => $options['users'] ?? [],
        ]);
        return new page((int) $page->get('id'));
    }

    /**
     * Pages inherit their section's audience unless they choose their own.
     */
    public function test_inheritance_and_own_audiences(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();

        $section = $this->make('Help', ['section' => true, 'allusers' => true]);
        $inherit = $this->make('Start', ['parent' => $section->get('id'), 'mode' => constants::AUDIENCE_INHERIT]);
        $hidden = $this->make('Admin notes', ['parent' => $section->get('id')]);
        $mine = $this->make('Personal', ['parent' => $section->get('id'), 'users' => [$student->id]]);

        $this->assertTrue(permission::can_view_page($section, (int) $student->id));
        $this->assertTrue(permission::can_view_page($inherit, (int) $student->id));
        $this->assertFalse(permission::can_view_page($hidden, (int) $student->id));
        $this->assertTrue(permission::can_view_page($mine, (int) $student->id));
        $this->assertEquals('Same as its hub', audience::describe($inherit));
        $this->assertEquals('Only people who manage pages', audience::describe($hidden));
    }

    /**
     * A section is visible to people who can see one of its pages, even if they are not in the section's audience.
     */
    public function test_section_visible_through_its_pages(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $guestid = (int) guest_user()->id;

        $section = $this->make('Help', ['section' => true, 'allusers' => true]);
        $members = $this->make('Members', ['parent' => $section->get('id'), 'mode' => constants::AUDIENCE_INHERIT]);
        $public = $this->make('Contact', ['parent' => $section->get('id'), 'guests' => true]);

        $this->assertTrue(permission::can_view_page($section, $guestid));
        $this->assertTrue(permission::can_view_page($public, $guestid));
        $this->assertFalse(permission::can_view_page($members, $guestid));
    }

    /**
     * Visitors who are not logged in see guest pages, unless the site makes everyone log in first.
     */
    public function test_logged_out_visitors_and_forcelogin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $public = $this->make('Privacy', ['allusers' => true, 'guests' => true]);
        $members = $this->make('Members', ['allusers' => true]);
        $this->setUser(null);

        set_config('forcelogin', 0);
        $this->assertEquals([(int) $public->get('id')], permission::visible_page_ids());

        set_config('forcelogin', 1);
        $this->assertSame([], permission::visible_page_ids());
        // The guest account itself is still evaluated normally (for example when guest login is used).
        $this->assertContains((int) $public->get('id'), permission::visible_page_ids((int) guest_user()->id));
        $this->assertNotContains((int) $members->get('id'), permission::visible_page_ids((int) guest_user()->id));
    }

    /**
     * Role audiences match system-level role assignments only.
     */
    public function test_role_audience(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = create_role('Trainer coordinator', 'trainercoord', '');
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $trainer = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        role_assign($roleid, $trainer->id, \core\context\system::instance()->id);

        $section = $this->make('Trainer resources', ['section' => true, 'roles' => [$roleid]]);
        $guide = $this->make('Marking guide', ['parent' => $section->get('id'), 'mode' => constants::AUDIENCE_INHERIT]);

        $this->assertTrue(permission::can_view_page($guide, (int) $trainer->id));
        $this->assertFalse(permission::can_view_page($guide, (int) $other->id));
        $this->assertFalse(permission::can_view_page($section, (int) $other->id));
    }

    /**
     * Page managers (admins and the Manager role) see every page and can manage.
     */
    public function test_managers_see_everything(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = $this->getDataGenerator()->create_user();
        global $DB;
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerrole, $manager->id, \core\context\system::instance()->id);
        $hidden = $this->make('Hidden');

        $this->assertTrue(permission::can_view_page($hidden, (int) get_admin()->id));
        $this->assertTrue(permission::can_manage((int) $manager->id));
        $this->assertTrue(permission::can_view_page($hidden, (int) $manager->id));
        $this->assertContains((int) $hidden->get('id'), permission::visible_page_ids((int) $manager->id));
    }

    /**
     * Visibility follows audience changes immediately (the per-user cache is purged).
     */
    public function test_cache_purged_on_change(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $page = $this->make('News');

        $this->assertFalse(permission::can_view_page($page, (int) $student->id));
        audience::set_selection((int) $page->get('id'), (object) ['allusers' => true, 'guests' => false, 'roles' => [],
            'users' => []]);
        $this->assertTrue(permission::can_view_page($page, (int) $student->id));
    }

    /**
     * Deleting a section keeps its pages and who can see them.
     */
    public function test_delete_section_keeps_pages_and_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $section = $this->make('Help', ['section' => true, 'allusers' => true]);
        $child = $this->make('Start', ['parent' => $section->get('id'), 'mode' => constants::AUDIENCE_INHERIT]);

        pagehelper::delete_page((int) $section->get('id'));

        $record = $DB->get_record('local_custompages', ['id' => $child->get('id')]);
        $this->assertEmpty($record->parent);
        $this->assertEquals(constants::AUDIENCE_OWN, (int) $record->audiencemode);
        $this->assertTrue(permission::can_view_page(new page((int) $child->get('id')), (int) $student->id));
        $this->assertFalse($DB->record_exists('local_custompages', ['id' => $section->get('id')]));
    }

    /**
     * Saving the page form stores fields, body and audiences; an inheriting page keeps no audiences of its own.
     */
    public function test_save_from_form(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $section = $this->make('Help', ['section' => true, 'allusers' => true]);

        $data = (object) [
            'name' => 'Getting started', 'title' => '', 'parent' => $section->get('id'), 'iscontainer' => 0,
            'showinprimarynav' => 1, 'cardsummary' => 'Read this first',
            'content_editor' => ['text' => '<p>Hello</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
            'audiencemode' => constants::AUDIENCE_INHERIT, 'aud_allusers' => 1, 'aud_guests' => 0, 'aud_roles' => [],
            'aud_users' => [],
        ];
        $page = pagehelper::save_from_form($data, null);

        $this->assertEquals('Getting started', $page->get('name'));
        $this->assertEquals('<p>Hello</p>', $page->get('content'));
        $this->assertEquals(0, (int) $page->get('showinprimarynav'), 'Pages in a section are not added to the menu directly');
        $this->assertTrue($page->inherits_audience());
        $this->assertFalse($DB->record_exists('local_custompage_audience', ['pageid' => $page->get('id')]));
        $this->assertStringContainsString('Hello', $page->get_formatted_content());

        // A page outside a section always has its own audience.
        $data->parent = 0;
        $page = pagehelper::save_from_form($data, $page);
        $this->assertFalse($page->inherits_audience());
        $this->assertEquals(1, (int) $page->get('showinprimarynav'));
        $this->assertEquals('All logged-in users', audience::describe($page));
    }

    /**
     * The main menu lists top-level items flagged for the menu, with a section's visible pages in a dropdown.
     */
    public function test_primary_navigation(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $section = $this->make('Help', ['section' => true, 'allusers' => true]);
        $DB->set_field('local_custompages', 'showinprimarynav', 1, ['id' => $section->get('id')]);
        $this->make('Start', ['parent' => $section->get('id'), 'mode' => constants::AUDIENCE_INHERIT]);
        $this->make('Admin notes', ['parent' => $section->get('id')]);
        $empty = $this->make('Empty section', ['section' => true, 'allusers' => true]);
        $DB->set_field('local_custompages', 'showinprimarynav', 1, ['id' => $empty->get('id')]);

        $this->setUser($student);
        $PAGE->set_url('/');
        $primary = new \core\navigation\views\primary($PAGE);
        $ids = permission::visible_page_ids();
        local\services\navigation::add_to_primary_navigation(
            $primary,
            local\services\navigation::build_page_hierarchy($ids)
        );

        $node = $primary->get('local_custompage_' . $section->get('id'));
        $this->assertNotEmpty($node);
        $this->assertEquals(['Overview', 'Start'], array_values(array_map(fn($n) => $n->text, iterator_to_array($node->children))));
        $this->assertEmpty($primary->get('local_custompage_' . $empty->get('id')), 'Sections with no visible pages are left out');
    }
}
