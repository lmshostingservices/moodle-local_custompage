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

namespace local_custompage\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Resource hub wizard, step 2: add or edit one resource.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_resource extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $context = $this->_customdata['context'] ?? \core\context\system::instance();
        foreach (['id' => 0, 'step' => 2, 'resource' => 0] as $name => $default) {
            $mform->addElement('hidden', $name, $default);
            $mform->setType($name, PARAM_INT);
        }

        $mform->addElement('text', 'name', get_string('resourcename', 'local_custompage'), ['size' => 50, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'cardsummary',
            get_string('cardsummary', 'local_custompage'),
            ['rows' => 2, 'cols' => 60, 'maxlength' => 400]
        );
        $mform->setType('cardsummary', PARAM_TEXT);
        $mform->addHelpButton('cardsummary', 'cardsummary', 'local_custompage');

        $prompturl = (new \moodle_url('/local/custompage/prompt.php'))->out(false);
        $mform->addElement(
            'static',
            'resourcedesign_desc',
            '',
            get_string('resourcedesign_desc', 'local_custompage', $prompturl)
        );
        $mform->addElement(
            'filepicker',
            'designfile',
            get_string('resourcedesign', 'local_custompage'),
            null,
            page::design_file_options()
        );
        $mform->addHelpButton('designfile', 'resourcedesign', 'local_custompage');
        if (!empty($this->_customdata['hasdesign'])) {
            $mform->addElement('advcheckbox', 'removedesign', '', get_string('removedesign', 'local_custompage'));
        }

        $mform->addElement(
            'filemanager',
            'documents',
            get_string('resourcefiles', 'local_custompage'),
            null,
            page::documents_options()
        );
        $mform->addHelpButton('documents', 'documents', 'local_custompage');

        $mform->addElement(
            'editor',
            'content_editor',
            get_string('resourcetext', 'local_custompage'),
            ['rows' => 8],
            page::content_editor_options($context)
        );
        $mform->setType('content_editor', PARAM_RAW); // pipeline-ignore: PARAM_RAW — editor HTML, cleaned on save and output.
        $mform->addHelpButton('content_editor', 'resourcetext', 'local_custompage');

        $mform->addElement(
            'filemanager',
            'cardimage',
            get_string('cardimage', 'local_custompage'),
            null,
            page::card_image_options()
        );
        $mform->addHelpButton('cardimage', 'cardimage', 'local_custompage');

        page::add_ownership_fields($mform);

        $editing = !empty($this->_customdata['editing']);
        $this->add_action_buttons(
            true,
            $editing ? get_string('savechanges') : get_string('addresource', 'local_custompage')
        );
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) $data['name']) === '') {
            $errors['name'] = get_string('required');
        }
        if ($file = page::get_draft_file((int) ($data['designfile'] ?? 0))) {
            try {
                [$css] = \local_custompage\local\helpers\design::import_html($file->get_content());
                \local_custompage\local\helpers\design::scope_css($css, 0);
            } catch (\moodle_exception $e) {
                $errors['designfile'] = $e->getMessage();
            }
        }
        return $errors;
    }
}
