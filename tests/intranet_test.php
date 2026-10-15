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
use local_custompage\local\helpers\feedback;
use local_custompage\local\helpers\hub;
use local_custompage\local\helpers\reader;
use local_custompage\local\models\page;

/**
 * Intranet navigation, freshness, ownership and "Was this page helpful?".
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\local\helpers\reader
 * @covers     \local_custompage\local\helpers\feedback
 * @covers     \local_custompage\external\feedback\submit
 * @covers     \local_custompage\privacy\provider
 */
final class intranet_test extends advanced_testcase {
    /**
     * A hub for everyone with the given pages, one of them only for managers.
     *
     * @param string[] $names
     * @return array [page $hub, page[] $pages]
     */
    private function make_hub(array $names): array {
        $hub = hub::save_settings((object) ['name' => 'Procedures', 'audience' => 'all'], null);
        $pages = [];
        foreach ($names as $name) {
            $pages[] = hub::save_resource($hub, (object) [
                'name' => $name,
                'content_editor' => ['text' => '<p>' . $name . '</p>', 'format' => FORMAT_HTML,
                    'itemid' => file_get_unused_draft_itemid()],
                'documents' => file_get_unused_draft_itemid(),
                'cardimage' => file_get_unused_draft_itemid(),
            ], null);
        }
        return [$hub, $pages];
    }

    /**
     * Breadcrumb, position and neighbours only count pages the visitor can open.
     */
    public function test_navigation_follows_what_the_visitor_can_open(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$hub, $pages] = $this->make_hub(['One', 'Two', 'Hidden', 'Four']);
        // The page called Hidden is for managers only.
        $pages[2]->set('audiencemode', constants::AUDIENCE_OWN);
        $pages[2]->update();
        audience::set_selection((int) $pages[2]->get('id'), (object) ['allusers' => false, 'guests' => false, 'roles' => [],
            'users' => []]);

        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $siblings = reader::get_visible_children(new page((int) $hub->get('id')));
        $this->assertEquals(['One', 'Two', 'Four'], array_map(fn(page $p) => $p->get('name'), $siblings));
        [$position, $previous, $next] = reader::position($pages[1], $siblings);
        $this->assertEquals(2, $position);
        $this->assertEquals('One', $previous->get('name'));
        $this->assertEquals('Four', $next->get('name'), 'The hidden page is skipped');

        $crumbs = reader::breadcrumb($pages[1]);
        $this->assertEquals(['Intranet', 'Procedures', 'Two'], array_column($crumbs, 'name'));
        $this->assertNull($crumbs[2]['url']);
        set_config('homelabel', 'Staff hub', 'local_custompage');
        $this->assertEquals('Staff hub', reader::breadcrumb($pages[1])[0]['name']);
    }

    /**
     * Freshness: relative dates, New/Updated badges, reading time and overdue reviews.
     */
    public function test_freshness(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $pages] = $this->make_hub(['One']);
        $page = $pages[0];
        $now = time();
        $this->assertEquals('today', reader::relative_date($now, $now));
        $this->assertEquals('yesterday', reader::relative_date($now - DAYSECS, $now));
        $this->assertEquals('3 days ago', reader::relative_date($now - 3 * DAYSECS, $now));
        $this->assertStringNotContainsString('ago', reader::relative_date($now - 30 * DAYSECS, $now));

        $this->assertEquals('new', reader::badge($page));
        $page->set('timecreated', $now - 60 * DAYSECS);
        $this->assertEquals('updated', reader::badge($page));
        $page->set('timemodified', $now - 30 * DAYSECS);
        $this->assertNull(reader::badge($page));

        $this->assertEquals(0, reader::reading_minutes($page));
        $page->set('content', '<p>' . str_repeat('word ', 1000) . '</p>');
        $this->assertEquals(5, reader::reading_minutes($page));

        $this->assertFalse(reader::review_overdue($page));
        $page->set('reviewby', $now - DAYSECS);
        $this->assertTrue(reader::review_overdue($page));
        $page->set('reviewby', $now + DAYSECS);
        $this->assertFalse(reader::review_overdue($page));
    }

    /**
     * The creator owns a new page; the owner can be changed and cleared.
     */
    public function test_ownership(): void {
        $this->resetAfterTest();
        $manager = $this->getDataGenerator()->create_user(['firstname' => 'Pat', 'lastname' => 'Owner']);
        $this->setAdminUser();
        [$hub, $pages] = $this->make_hub(['One']);
        $this->assertEquals(get_admin()->id, $pages[0]->get('ownerid'));

        $data = (object) ['name' => 'One', 'content_editor' => ['text' => '', 'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid()], 'documents' => file_get_unused_draft_itemid(),
            'cardimage' => file_get_unused_draft_itemid(), 'ownerid' => $manager->id, 'reviewby' => 1893456000];
        $saved = hub::save_resource($hub, $data, new page((int) $pages[0]->get('id')));
        $this->assertEquals('Pat Owner', reader::owner_name($saved));
        $this->assertEquals(1893456000, $saved->get('reviewby'));
        $data->ownerid = 0;
        $data->reviewby = 0;
        $saved = hub::save_resource($hub, $data, new page((int) $pages[0]->get('id')));
        $this->assertNull(reader::owner_name($saved));
        $this->assertEmpty($saved->get('reviewby'));
    }

    /**
     * One answer per person per page; comments are kept or replaced; totals add up; answers go with the page.
     */
    public function test_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $pages] = $this->make_hub(['One']);
        $pageid = (int) $pages[0]->get('id');
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        feedback::save($pageid, (int) $u1->id, false, 'Missing the form');
        feedback::save($pageid, (int) $u1->id, true);
        $answer = feedback::get_answer($pageid, (int) $u1->id);
        $this->assertEquals(1, $answer->helpful);
        $this->assertEquals('Missing the form', $answer->comment, 'Changing the answer keeps the comment');
        feedback::save($pageid, (int) $u1->id, true, '');
        $this->assertNull(feedback::get_answer($pageid, (int) $u1->id)->comment);
        feedback::save($pageid, (int) $u2->id, false, '<b>Unclear</b>');
        $this->assertEquals('Unclear', feedback::get_answer($pageid, (int) $u2->id)->comment);

        $summary = feedback::summaries()[$pageid];
        $this->assertEquals([1, 2, 1], [$summary->helpful, $summary->total, $summary->comments]);
        $this->assertCount(2, feedback::get_entries($pageid));

        \local_custompage\local\helpers\page::delete_page($pageid);
        $this->assertEmpty(feedback::get_entries($pageid));
    }

    /**
     * The web service saves answers for people who can see the page, and refuses everyone else.
     */
    public function test_feedback_web_service(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $pages] = $this->make_hub(['One']);
        $pageid = (int) $pages[0]->get('id');
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $result = \local_custompage\external\feedback\submit::execute($pageid, true, 'Great');
        $this->assertTrue($result['saved']);
        $this->assertEquals('Great', feedback::get_answer($pageid, (int) $student->id)->comment);

        // A page only for managers.
        $this->setAdminUser();
        $pages[0]->set('audiencemode', constants::AUDIENCE_OWN);
        $pages[0]->update();
        audience::set_selection($pageid, (object) ['allusers' => false, 'guests' => false, 'roles' => [], 'users' => []]);
        $this->setUser($student);
        try {
            \local_custompage\external\feedback\submit::execute($pageid, false);
            $this->fail('Expected an exception');
        } catch (page_access_exception $e) {
            $this->assertEquals(1, $DB->get_field(feedback::TABLE, 'helpful', ['pageid' => $pageid, 'userid' => $student->id]));
        }
        $this->setGuestUser();
        $this->expectException(\require_login_exception::class);
        \local_custompage\external\feedback\submit::execute($pageid, false);
    }

    /**
     * Privacy: answers and ownership are found, exported and deleted.
     */
    public function test_privacy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $pages] = $this->make_hub(['One', 'Two']);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $one = (int) $pages[0]->get('id');
        $two = (int) $pages[1]->get('id');
        feedback::save($one, (int) $u1->id, true, 'Thanks');
        feedback::save($one, (int) $u2->id, false);
        $DB->set_field('local_custompages', 'ownerid', $u1->id, ['id' => $two]);

        $contexts = privacy\provider::get_contexts_for_userid((int) $u1->id)->get_contextids();
        $ctxone = $pages[0]->get_context();
        $ctxtwo = $pages[1]->get_context();
        $this->assertEqualsCanonicalizing([$ctxone->id, $ctxtwo->id], array_map('intval', $contexts));

        $userlist = new \core_privacy\local\request\userlist($ctxone, 'local_custompage');
        privacy\provider::get_users_in_context($userlist);
        $this->assertContains((int) $u1->id, $userlist->get_userids());
        $this->assertContains((int) $u2->id, $userlist->get_userids());

        $approved = new \core_privacy\local\request\approved_contextlist($u1, 'local_custompage', [$ctxone->id, $ctxtwo->id]);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($ctxone);
        $this->assertTrue($writer->has_any_data());

        privacy\provider::delete_data_for_user($approved);
        $this->assertNull(feedback::get_answer($one, (int) $u1->id));
        $this->assertNotNull(feedback::get_answer($one, (int) $u2->id));
        $this->assertNull($DB->get_field('local_custompages', 'ownerid', ['id' => $two]));

        privacy\provider::delete_data_for_all_users_in_context($ctxone);
        $this->assertEmpty(feedback::get_entries($one));
        $this->assertTrue($DB->record_exists('local_custompages', ['id' => $one]), 'Pages themselves are kept');
    }

    /**
     * Every site role can be chosen, including course roles and custom roles, and holding it in any course counts.
     */
    public function test_roles_in_any_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $custom = create_role('Assessor', 'assessor', 'A custom role used only in courses');
        set_role_contextlevels($custom, [CONTEXT_COURSE]);
        $choices = audience::selectable_roles();
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $noneditor = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $this->assertArrayHasKey($teacher, $choices);
        $this->assertArrayHasKey($noneditor, $choices);
        $this->assertArrayHasKey($custom, $choices);
        $this->assertArrayNotHasKey((int) $DB->get_field('role', 'id', ['shortname' => 'guest']), $choices);
        $this->assertArrayNotHasKey((int) $DB->get_field('role', 'id', ['shortname' => 'user']), $choices);

        $hub = hub::save_settings((object) ['name' => 'Staff', 'audience' => 'roles', 'roles' => [$noneditor, $custom]], null);
        $course = $this->getDataGenerator()->create_course();
        $tutor = $this->getDataGenerator()->create_user();
        $assessor = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($tutor->id, $course->id, 'teacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        role_assign($custom, $assessor->id, \context_course::instance($course->id)->id);

        $this->assertTrue(permission::can_view_page($hub, (int) $tutor->id), 'Non-editing teacher in a course');
        $this->assertTrue(permission::can_view_page($hub, (int) $assessor->id), 'Custom course role');
        $this->assertFalse(permission::can_view_page($hub, (int) $student->id));
        $this->assertTrue(audience::user_has_any_role((int) $tutor->id, [$noneditor]));
        $this->assertFalse(audience::user_has_any_role((int) $student->id, [$noneditor, $custom]));
    }

    /**
     * The intranet link in the main menu can be limited to roles; admins and page managers always see it.
     */
    public function test_home_link_roles(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $this->assertTrue(local\services\navigation::show_home_link(), 'No roles chosen: everyone');

        set_config('homemenuroles', (string) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']), 'local_custompage');
        $this->assertFalse(local\services\navigation::show_home_link());
        $this->setUser($teacher);
        $this->assertTrue(local\services\navigation::show_home_link());
        $this->setAdminUser();
        $this->assertTrue(local\services\navigation::show_home_link());
        $this->setGuestUser();
        $this->assertFalse(local\services\navigation::show_home_link());
    }
}
