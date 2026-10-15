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
use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\card_image;
use local_custompage\local\helpers\design;
use local_custompage\local\helpers\hub;
use local_custompage\local\helpers\page as page_helper;
use local_custompage\local\helpers\reader;
use local_custompage\local\models\page;

/**
 * Fixes from the 2.2.4 review: ordering and permissions after hub changes, access after role changes, the hub wizard's
 * audience, design cleaning, privacy of chosen people and card image size.
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\local\helpers\page
 * @covers     \local_custompage\local\models\page
 * @covers     \local_custompage\observer
 * @covers     \local_custompage\local\helpers\hub
 * @covers     \local_custompage\local\helpers\design
 * @covers     \local_custompage\local\helpers\card_image
 * @covers     \local_custompage\privacy\provider
 */
final class review_test extends advanced_testcase {
    /**
     * A hub for everyone with resources.
     *
     * @param string $name
     * @param string[] $resources
     * @return array [page $hub, page[] $resources]
     */
    private function make_hub(string $name, array $resources): array {
        $hub = hub::save_settings((object) ['name' => $name, 'audience' => 'all'], null);
        $pages = [];
        foreach ($resources as $resource) {
            $pages[] = hub::save_resource($hub, (object) [
                'name' => $resource,
                'content_editor' => ['text' => '<p>x</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
                'documents' => file_get_unused_draft_itemid(),
                'cardimage' => file_get_unused_draft_itemid(),
            ], null);
        }
        return [$hub, $pages];
    }

    /**
     * Pages left by a deleted hub get their own top-level position and context.
     */
    public function test_deleting_a_hub_gives_its_pages_their_own_place(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$old, [$a, $b]] = $this->make_hub('Old', ['A', 'B']);
        page_helper::delete_page((int) $old->get('id'));
        [$new, [$c]] = $this->make_hub('New', ['C']);

        $codes = $DB->get_records_menu('local_custompages', null, '', 'name, sortthread');
        $this->assertStringNotContainsString('.', $codes['A']);
        $this->assertStringNotContainsString('.', $codes['B']);
        $this->assertCount(4, array_unique($codes), 'Every page has its own sort code');
        $this->assertStringStartsNotWith($codes['New'], $codes['A']);

        $system = \core\context\system::instance();
        foreach ([$a, $b] as $page) {
            $context = context_custompage::instance((int) $page->get('id'));
            $this->assertEquals($system->id, $context->get_parent_context()->id);
            $this->assertEquals($system->path . '/' . $context->id, $context->path);
        }
    }

    /**
     * A page moved to another hub takes its permissions from the new hub.
     */
    public function test_moving_a_page_moves_its_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$one, [$page]] = $this->make_hub('One', ['Moved']);
        [$two] = $this->make_hub('Two', []);
        $page = new page((int) $page->get('id'));
        $page->set('parent', $two->get('id'));
        $page->update();

        $context = context_custompage::instance((int) $page->get('id'));
        $twocontext = context_custompage::instance((int) $two->get('id'));
        $this->assertEquals($twocontext->id, $context->get_parent_context()->id);
        $this->assertStringStartsWith($twocontext->path . '/', $context->path);
    }

    /**
     * Taking a role away removes access straight away, not after the cache expires.
     */
    public function test_role_changes_update_access_straight_away(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        $hub = hub::save_settings((object) ['name' => 'Staff', 'audience' => 'roles', 'roles' => [$roleid]], null);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);

        $this->assertNotContains((int) $hub->get('id'), audience::get_allowed_pages((int) $user->id));
        role_assign($roleid, $user->id, $context->id);
        $this->assertContains((int) $hub->get('id'), audience::get_allowed_pages((int) $user->id));
        role_unassign($roleid, $user->id, $context->id);
        $this->assertNotContains((int) $hub->get('id'), audience::get_allowed_pages((int) $user->id));
    }

    /**
     * A mixed audience set elsewhere is offered as "keep", and keeping it changes nothing.
     */
    public function test_wizard_keeps_an_audience_it_cannot_show(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        $user = $this->getDataGenerator()->create_user();
        $hub = hub::save_settings((object) ['name' => 'Mixed', 'audience' => 'all'], null);
        $id = (int) $hub->get('id');
        audience::set_selection($id, (object) ['allusers' => false, 'guests' => false, 'roles' => [$roleid],
            'users' => [$user->id]]);

        [$choice] = hub::get_audience_choice($id);
        $this->assertEquals('keep', $choice);
        hub::save_settings((object) ['id' => $id, 'name' => 'Mixed', 'audience' => 'keep'], new page($id));
        $selection = audience::get_selection($id);
        $this->assertEquals([$roleid], array_map('intval', $selection->roles));
        $this->assertEquals([(int) $user->id], array_map('intval', $selection->users));

        audience::set_selection($id, (object) ['allusers' => false, 'guests' => true, 'roles' => [], 'users' => []]);
        $this->assertEquals('keep', hub::get_audience_choice($id)[0], 'Visitors only is not shown as managers only');
        audience::set_selection($id, (object) ['allusers' => true, 'guests' => true, 'roles' => [], 'users' => []]);
        $this->assertEquals('public', hub::get_audience_choice($id)[0]);
    }

    /**
     * Design HTML loses SVG animation tricks, and frames showing this site can't lift their sandbox.
     */
    public function test_design_cleaning(): void {
        global $CFG;
        $this->resetAfterTest();
        $html = design::sanitise_html('<svg><a><set attributeName="href" to="javascript:alert(1)"/><text>x</text></a>'
            . '<animate attributeName="href" values="javascript:alert(1)"/></svg>'
            . '<iframe src="' . $CFG->wwwroot . '/user/profile.php"></iframe>'
            . '<iframe src="https://www.youtube.com/embed/abc"></iframe>');
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('<set', $html);
        preg_match_all('~sandbox="([^"]*)"~', $html, $sandboxes);
        $this->assertStringNotContainsString('allow-same-origin', $sandboxes[1][0], 'Same-site frame');
        $this->assertStringContainsString('allow-same-origin', $sandboxes[1][1], 'Video from another site');
    }

    /**
     * Design CSS can't write rules outside its page or read files from the server.
     */
    public function test_design_css_stays_in_its_page(): void {
        $this->resetAfterTest();
        foreach (['@at-root body { display: none; }', '@use "sass:math";', '@forward "x";'] as $css) {
            try {
                design::scope_css($css, 5);
                $this->fail('Accepted: ' . $css);
            } catch (\moodle_exception $e) {
                $this->assertEquals('designcsserror', $e->errorcode);
            }
        }
        $compiled = design::scope_css('@import"/etc/passwd"; @import url("https://fonts.googleapis.com/css2?family=Inter");'
            . ' .a { color: red; }', 5);
        $this->assertStringNotContainsString('passwd', $compiled);
        $this->assertStringContainsString('fonts.googleapis.com', $compiled);
        try {
            design::scope_css('.a { color: red; ', 5);
            $this->fail('Broken CSS accepted');
        } catch (\moodle_exception $e) {
            $this->assertStringNotContainsString('/', (string) $e->a, 'No server paths in the message');
        }
    }

    /**
     * Someone chosen by name in an audience is found, exported and removed by the privacy API.
     */
    public function test_privacy_finds_people_chosen_in_an_audience(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $chosen = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $hub = hub::save_settings(
            (object) ['name' => 'Named', 'audience' => 'people', 'users' => [$chosen->id, $other->id]],
            null
        );
        $context = context_custompage::instance((int) $hub->get('id'));

        $contexts = privacy\provider::get_contexts_for_userid((int) $chosen->id);
        $this->assertContains((int) $context->id, array_map('intval', $contexts->get_contextids()));
        $userlist = new \core_privacy\local\request\userlist($context, 'local_custompage');
        privacy\provider::get_users_in_context($userlist);
        $this->assertContains((int) $chosen->id, array_map('intval', $userlist->get_userids()));

        privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $chosen,
            'local_custompage',
            [$context->id]
        ));
        $this->assertEquals([(int) $other->id], array_map('intval', audience::get_selection((int) $hub->get('id'))->users));
    }

    /**
     * A large photo used as a card image is saved smaller.
     */
    public function test_large_card_images_are_shrunk(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, [$page]] = $this->make_hub('Images', ['Photo']);
        $contextid = (int) $page->get('contextid');
        // A noisy 2400 x 1600 PNG, as image tools save them.
        $image = imagecreatetruecolor(2400, 1600);
        for ($i = 0; $i < 60000; $i++) {
            imagesetpixel($image, random_int(0, 2399), random_int(0, 1599), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($image, null, 0);
        $png = ob_get_clean();
        $fs = get_file_storage();
        $fs->create_file_from_string(['contextid' => $contextid, 'component' => 'local_custompage', 'filearea' => 'cardimage',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'hero.png'], $png);

        card_image::shrink($contextid);
        $files = $fs->get_area_files($contextid, 'local_custompage', 'cardimage', 0, 'id', false);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertEquals('hero.jpg', $file->get_filename());
        $this->assertEquals(card_image::MAX_WIDTH, $file->get_imageinfo()['width']);
        $this->assertLessThan(strlen($png), $file->get_filesize());
        $this->assertNotNull((new page((int) $page->get('id')))->get_card_image_url());
    }

    /**
     * Names keep their "&" in attributes and filters.
     */
    public function test_plain_text(): void {
        $this->assertEquals('Health & Safety "plan"', reader::plain_text('Health &amp; <b>Safety</b>  &quot;plan&quot;'));
    }
}
