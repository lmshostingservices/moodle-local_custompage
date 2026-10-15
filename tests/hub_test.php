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
use local_custompage\local\helpers\hub;
use local_custompage\local\models\page;

/**
 * The resource hub wizard: details and audience, resources, and look.
 *
 * @package    local_custompage
 * @category   test
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_custompage\local\helpers\hub
 */
final class hub_test extends advanced_testcase {
    /**
     * A draft area holding one file, as a file picker would leave it.
     *
     * @param string $filename
     * @param string $content
     * @return int Draft item ID
     */
    private function draft_file(string $filename, string $content): int {
        global $USER;
        $itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $itemid;
    }

    /**
     * Resource form data.
     *
     * @param string $name
     * @param int $documents Draft item ID
     * @return \stdClass
     */
    private function resource_data(string $name, int $documents = 0): \stdClass {
        return (object) [
            'name' => $name,
            'cardsummary' => "About $name",
            'content_editor' => [
                'text' => "<p>$name text</p>",
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
            'documents' => $documents ?: file_get_unused_draft_itemid(),
            'cardimage' => file_get_unused_draft_itemid(),
        ];
    }

    /**
     * Step 1 creates a hub with the chosen audience; changing the choice replaces it.
     */
    public function test_save_settings_and_audience_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);

        $hub = hub::save_settings((object) ['name' => ' Trainer Resources ', 'intro' => 'All you need.', 'audience' => 'roles',
            'roles' => [$roleid], 'showinprimarynav' => 1, 'title' => 'Trainers'], null);
        $this->assertEquals(1, $hub->get('iscontainer'));
        $this->assertEquals('Trainer Resources', $hub->get('name'));
        $this->assertEquals('All you need.', $hub->get('cardsummary'));
        $this->assertEquals('classic', $hub->get('look'));
        $this->assertEquals(constants::AUDIENCE_OWN, (int) $hub->get('audiencemode'));
        $this->assertEquals(['roles', [$roleid], []], hub::get_audience_choice((int) $hub->get('id')));

        foreach (['public', 'all', 'managers'] as $choice) {
            hub::save_settings((object) ['name' => 'Trainer Resources', 'audience' => $choice, 'roles' => [$roleid]], $hub);
            $this->assertEquals($choice, hub::get_audience_choice((int) $hub->get('id'))[0]);
        }
        $this->assertEquals(1, $DB->count_records('local_custompages'));
    }

    /**
     * Step 2 adds resources that follow the hub's audience, with their text and files.
     */
    public function test_save_resource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $hub = hub::save_settings((object) ['name' => 'Help', 'audience' => 'managers'], null);

        $resource = hub::save_resource($hub, $this->resource_data('Guide', $this->draft_file('guide.pdf', '%PDF-1.4')), null);
        $this->assertEquals($hub->get('id'), $resource->get('parent'));
        $this->assertTrue($resource->inherits_audience());
        $this->assertEquals('About Guide', $resource->get('cardsummary'));
        $this->assertStringContainsString('Guide text', $resource->get('content'));
        $this->assertEquals(['guide.pdf'], array_values(array_map(fn($f) => $f->get_filename(), $resource->get_documents())));
        $this->assertNull($resource->get_card_image_url());

        // The resource follows the hub: hidden while the hub is for managers only, visible once it is for everyone.
        $this->assertFalse(permission::can_view_page($resource, (int) $student->id));
        hub::save_settings((object) ['name' => 'Help', 'audience' => 'all'], $hub);
        $this->assertTrue(permission::can_view_page(new page((int) $resource->get('id')), (int) $student->id));

        // Editing keeps it in the hub and updates it.
        $data = $this->resource_data('Guide v2');
        $edited = hub::save_resource($hub, $data, new page((int) $resource->get('id')));
        $this->assertEquals($resource->get('id'), $edited->get('id'));
        $this->assertEquals('Guide v2', (new page((int) $resource->get('id')))->get('name'));
    }

    /**
     * Step 3: an uploaded design replaces the area above the cards; choosing a built-in look removes it.
     */
    public function test_save_look(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $hub = hub::save_settings((object) ['name' => 'Help', 'audience' => 'all'], null);
        $id = (int) $hub->get('id');

        hub::save_look($hub, (object) ['look' => 'studio']);
        $this->assertEquals('studio', (new page($id))->get('look'));

        hub::save_look(new page($id), (object) ['look' => 'nonsense']);
        $this->assertEquals('classic', (new page($id))->get('look'));

        $file = $this->draft_file('design.html', '<html><head><style>body{background:#eef} .x{color:red}</style></head>'
            . '<body><div class="x">Welcome</div><script>alert(1)</script></body></html>');
        hub::save_look(new page($id), (object) ['look' => 'custom', 'designfile' => $file]);
        $hub = new page($id);
        $this->assertEquals('custom', $hub->get('look'));
        $this->assertStringContainsString('Welcome', $hub->get('content'));
        $this->assertStringNotContainsString('script', $hub->get('content'));
        $this->assertStringContainsString('.local-custompage-design-' . $id . ' .x', $hub->get('designcsscompiled'));

        // Keeping the custom look without a new file keeps the design.
        hub::save_look($hub, (object) ['look' => 'custom', 'designfile' => file_get_unused_draft_itemid()]);
        $this->assertStringContainsString('Welcome', (new page($id))->get('content'));

        hub::save_look(new page($id), (object) ['look' => 'atelier']);
        $hub = new page($id);
        $this->assertEquals('atelier', $hub->get('look'));
        $this->assertEmpty($hub->get('content'));
        $this->assertEmpty($hub->get('designcss'));
        $this->assertEmpty($hub->get('designcsscompiled'));
    }

    /**
     * A resource can be a page design (HTML or text file), which replaces its text and keeps its styles to its own page.
     */
    public function test_save_resource_with_design(): void {
        $f = str_repeat(chr(96), 3); // A Markdown code fence.
        $this->resetAfterTest();
        $this->setAdminUser();
        $hub = hub::save_settings((object) ['name' => 'Trainer Resources', 'audience' => 'all'], null);

        $data = $this->resource_data('Behaviour procedure');
        $data->designfile = $this->draft_file('procedure.txt', "Here you go:\n" . $f . "html\n<!DOCTYPE html><html><head>"
            . "<style>body{margin:0}.tbp-hero{color:#182641}</style></head><body><main class=\"tbp-page\">"
            . "<section class=\"tbp-hero\"><h2>Managing behaviour</h2></section><script>alert(1)</script>"
            . "</main></body></html>\n" . $f);
        $resource = hub::save_resource($hub, $data, null);
        $id = (int) $resource->get('id');
        $resource = new page($id);
        $hero = '<section class="tbp-hero"><h2>Managing behaviour</h2></section>';
        $this->assertStringContainsString($hero, $resource->get('content'));
        $this->assertStringNotContainsString('Behaviour procedure text', $resource->get('content'));
        $this->assertStringNotContainsString('script', $resource->get('content'));
        $this->assertStringContainsString('.local-custompage-design-' . $id . ' .tbp-hero', $resource->get('designcsscompiled'));
        $this->assertStringContainsString('<section class="tbp-hero">', $resource->get_formatted_content());

        // Saving again without a new file keeps the design; removing it keeps the text but drops the styles.
        $data = $this->resource_data('Behaviour procedure');
        $data->content_editor['text'] = $resource->get('content');
        hub::save_resource($hub, $data, new page($id));
        $this->assertNotEmpty((new page($id))->get('designcsscompiled'));
        $data->removedesign = 1;
        hub::save_resource($hub, $data, new page($id));
        $this->assertEmpty((new page($id))->get('designcsscompiled'));
        $this->assertStringContainsString('tbp-hero', (new page($id))->get('content'));
    }
}
