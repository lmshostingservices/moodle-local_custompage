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
use local_custompage\custom_context\context_custompage;
use local_custompage\local\helpers\ack_export;
use local_custompage\local\helpers\acknowledge;
use local_custompage\local\helpers\hub;
use local_custompage\local\helpers\page as page_helper;
use local_custompage\local\helpers\standards;
use local_custompage\local\models\page;

/**
 * Read confirmations, standards and the audit report.
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\local\helpers\acknowledge
 * @covers     \local_custompage\local\helpers\standards
 * @covers     \local_custompage\local\helpers\ack_export
 * @covers     \local_custompage\external\ack\confirm
 * @covers     \local_custompage\privacy\provider
 */
final class acknowledge_test extends advanced_testcase {
    /**
     * A hub for one role, with resources, that asks staff to confirm each resource.
     *
     * @param int $roleid
     * @param string[] $names
     * @return array [page $hub, page[] $resources]
     */
    private function make_hub(int $roleid, array $names): array {
        $hub = hub::save_settings((object) ['name' => 'Procedures', 'audience' => 'roles', 'roles' => [$roleid],
            'requireack' => 1], null);
        $pages = [];
        foreach ($names as $name) {
            $pages[] = hub::save_resource($hub, (object) [
                'name' => $name,
                'content_editor' => ['text' => '<p>x</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
                'documents' => file_get_unused_draft_itemid(),
                'cardimage' => file_get_unused_draft_itemid(),
            ], null);
        }
        return [new page((int) $hub->get('id')), $pages];
    }

    /**
     * Staff with the role, given in a course.
     *
     * @param int $roleid
     * @param int $count
     * @return \stdClass[]
     */
    private function make_staff(int $roleid, int $count): array {
        $course = $this->getDataGenerator()->create_course();
        $staff = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user(['lastname' => 'Staff' . $i]);
            role_assign($roleid, $user->id, \core\context\course::instance($course->id)->id);
            $staff[] = $user;
        }
        return $staff;
    }

    /**
     * A hub's setting covers its resources; the hub itself is not confirmed.
     */
    public function test_what_needs_confirming(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [$hubpage, [$one]] = $this->make_hub($roleid, ['One']);
        $this->assertTrue(acknowledge::is_required($one));
        $this->assertFalse(acknowledge::is_required($hubpage));

        $single = page_helper::save_from_form((object) ['name' => 'Policy', 'iscontainer' => 0, 'aud_allusers' => 1,
            'requireack' => 0, 'content_editor' => ['text' => 'x', 'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid()]], null);
        $this->assertFalse(acknowledge::is_required($single));
        $single->set('requireack', 1);
        $single->update();
        $this->assertTrue(acknowledge::is_required(new page((int) $single->get('id'))));
    }

    /**
     * Confirming, confirming twice, and being asked again after a significant change.
     */
    public function test_confirm_and_ask_again(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [, [$one]] = $this->make_hub($roleid, ['One']);
        [$user] = $this->make_staff($roleid, 1);

        $sink = $this->redirectEvents();
        $first = acknowledge::confirm($one, (int) $user->id);
        $again = acknowledge::confirm($one, (int) $user->id);
        $this->assertEquals($first->id, $again->id, 'Confirming twice keeps one record');
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\page_read_confirmed);
        $this->assertCount(1, $events);
        $this->assertEquals((int) $user->id, reset($events)->relateduserid);
        $this->assertEquals([(int) $one->get('id') => acknowledge::CURRENT], acknowledge::states([$one], (int) $user->id));

        acknowledge::after_save($one, (object) ['askagain' => 1], true);
        $one = new page((int) $one->get('id'));
        $this->assertEquals(2, $one->get('ackversion'));
        $this->assertEquals([(int) $one->get('id') => acknowledge::OUTDATED], acknowledge::states([$one], (int) $user->id));
        acknowledge::confirm($one, (int) $user->id);
        $this->assertEquals([(int) $one->get('id') => acknowledge::CURRENT], acknowledge::states([$one], (int) $user->id));
        $this->assertCount(2, acknowledge::history_rows(), 'Both versions are kept');

        acknowledge::after_save($one, (object) ['askagain' => 1], false);
        $this->assertEquals(2, (new page((int) $one->get('id')))->get('ackversion'), 'A new page is not asked again');
    }

    /**
     * The report lists every document against everyone in its audience, and nobody else.
     */
    public function test_report_rows_and_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        $otherrole = $this->getDataGenerator()->create_role();
        [$hubpage, [$one, $two]] = $this->make_hub($roleid, ['One', 'Two']);
        [$a, $b, $c] = $this->make_staff($roleid, 3);
        $outsider = $this->getDataGenerator()->create_user();
        $suspended = $this->make_staff($roleid, 1)[0];
        $DB = $GLOBALS['DB'];
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        standards::set_for_page((int) $one->get('id'), [
            ['framework' => 'RTO Standards 2025', 'code' => '1.11', 'name' => 'Student management'],
            ['framework' => 'ISO 9001', 'code' => '7.5', 'name' => 'Documented information'],
        ]);

        acknowledge::confirm($one, (int) $a->id);
        acknowledge::confirm($two, (int) $a->id);
        acknowledge::confirm($one, (int) $b->id);

        $rows = acknowledge::report_rows();
        $this->assertCount(6, $rows, '2 documents x 3 staff; outsiders and suspended people left out');
        $this->assertNotContains((int) $outsider->id, array_map(fn($r) => (int) $r->user->id, $rows));
        $summary = acknowledge::summary($rows);
        $this->assertEquals(2, $summary[(int) $one->get('id')][acknowledge::CURRENT]);
        $this->assertEquals(66, $summary[(int) $one->get('id')]['percent']);
        $this->assertEquals(
            'RTO Standards 2025 1.11 Student management; ISO 9001 7.5 Documented information',
            $summary[(int) $one->get('id')]['standards']
        );

        $this->assertCount(3, acknowledge::report_rows(['status' => acknowledge::NONE]));
        $this->assertCount(3, acknowledge::report_rows(['page' => (int) $one->get('id')]));
        $this->assertCount(3, acknowledge::report_rows(['framework' => 'ISO 9001']));
        $this->assertCount(3, acknowledge::report_rows(['standard' => 'RTO Standards 2025|1.11']));
        $this->assertCount(0, acknowledge::report_rows(['standard' => 'RTO Standards 2025|9.99']));
        $this->assertCount(6, acknowledge::report_rows(['hub' => (int) $hubpage->get('id')]));
        $this->assertCount(2, acknowledge::report_rows(['search' => 'Staff1']));
        $this->assertCount(0, acknowledge::report_rows(['role' => $otherrole]));
        $this->assertCount(6, acknowledge::report_rows(['role' => $roleid]));
        $bystaff = acknowledge::report_rows(['sort' => 'staff']);
        $this->assertEquals((int) $bystaff[0]->user->id, (int) $bystaff[1]->user->id, 'By staff: one person\'s documents together');

        $history = acknowledge::history_rows(['framework' => 'RTO Standards 2025']);
        $this->assertCount(2, $history);

        // Export values line up with the columns.
        $values = ack_export::values($rows[0], 'status');
        $this->assertCount(count(ack_export::columns('status')), $values);
        $this->assertCount(count(ack_export::columns('history')), ack_export::values($history[0], 'history'));

        // Confirmations stay as history when the page is deleted; its standards go.
        page_helper::delete_page((int) $one->get('id'));
        $this->assertCount(3, acknowledge::history_rows());
        $this->assertSame([], standards::get_for_page((int) $one->get('id')));
    }

    /**
     * Standards: saved from form rows, empty rows dropped, filters list each one once.
     */
    public function test_standards(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [, [$one, $two]] = $this->make_hub($roleid, ['One', 'Two']);
        standards::save_from_form((int) $one->get('id'), (object) [
            'std_framework' => ['RTO Standards 2025', '', 'RTO Standards 2025'],
            'std_code' => ['1.11', '', '2.3'],
            'std_name' => ['Student management', '', '<b>Trainer</b> competence'],
        ]);
        standards::set_for_page((int) $two->get('id'), [['framework' => 'RTO Standards 2025', 'code' => '1.11',
            'name' => 'Student management']]);
        $saved = standards::get_for_page((int) $one->get('id'));
        $this->assertCount(2, $saved);
        $this->assertEquals('Trainer competence', $saved[1]->name);
        $this->assertEquals(['RTO Standards 2025'], standards::frameworks());
        $this->assertEquals(['RTO Standards 2025|1.11' => 'RTO Standards 2025 1.11 Student management',
            'RTO Standards 2025|2.3' => 'RTO Standards 2025 2.3 Trainer competence'], standards::options());
        $this->assertEqualsCanonicalizing(
            [(int) $one->get('id'), (int) $two->get('id')],
            standards::page_filter('', 'RTO Standards 2025|1.11')
        );
        $form = standards::form_data((int) $one->get('id'));
        $this->assertEquals(2, $form['std_repeats']);
        // A form without the standards fields leaves them alone.
        standards::save_from_form((int) $one->get('id'), (object) ['name' => 'x']);
        $this->assertCount(2, standards::get_for_page((int) $one->get('id')));
    }

    /**
     * The web service confirms for people who can see a page that asks for it.
     */
    public function test_web_service(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [, [$one]] = $this->make_hub($roleid, ['One']);
        [$user] = $this->make_staff($roleid, 1);
        $outsider = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $result = external\ack\confirm::execute((int) $one->get('id'));
        $this->assertGreaterThan(0, $result['timeconfirmed']);

        $this->setUser($outsider);
        $this->expectException(page_access_exception::class);
        external\ack\confirm::execute((int) $one->get('id'));
    }

    /**
     * People see their own pages to confirm.
     */
    public function test_to_confirm(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [, [$one, $two]] = $this->make_hub($roleid, ['One', 'Two']);
        [$user] = $this->make_staff($roleid, 1);
        $this->assertCount(2, acknowledge::to_confirm((int) $user->id));
        acknowledge::confirm($one, (int) $user->id);
        $this->assertEquals([(int) $two->get('id')], array_map('intval', array_keys(acknowledge::to_confirm((int) $user->id))));
    }

    /**
     * Privacy: confirmations are found, exported and deleted, including those of deleted pages.
     */
    public function test_privacy(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        [, [$one, $two]] = $this->make_hub($roleid, ['One', 'Two']);
        [$user, $other] = $this->make_staff($roleid, 2);
        acknowledge::confirm($one, (int) $user->id);
        acknowledge::confirm($two, (int) $user->id);
        acknowledge::confirm($one, (int) $other->id);
        $onecontext = context_custompage::instance((int) $one->get('id'));
        page_helper::delete_page((int) $two->get('id'));

        $contexts = array_map('intval', privacy\provider::get_contexts_for_userid((int) $user->id)->get_contextids());
        $this->assertContains((int) $onecontext->id, $contexts);
        $this->assertContains((int) \core\context\system::instance()->id, $contexts, 'Deleted page: site context');

        $userlist = new \core_privacy\local\request\userlist($onecontext, 'local_custompage');
        privacy\provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([(int) $user->id, (int) $other->id], array_map('intval', array_intersect(
            $userlist->get_userids(),
            [$user->id, $other->id]
        )));

        $approved = new \core_privacy\local\request\approved_contextlist($user, 'local_custompage', $contexts);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($onecontext);
        $this->assertTrue($writer->has_any_data());

        privacy\provider::delete_data_for_user($approved);
        $DB = $GLOBALS['DB'];
        $this->assertEquals(0, $DB->count_records(acknowledge::TABLE, ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records(acknowledge::TABLE, ['userid' => $other->id]));
    }
}
