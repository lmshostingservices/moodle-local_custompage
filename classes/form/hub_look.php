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
 * Resource hub wizard, step 3: choose a look.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_look extends \moodleform {
    /** @var string[] Built-in looks. */
    public const LOOKS = ['classic', 'trainerspace', 'atelier', 'studio'];

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'step', 3);
        $mform->setType('step', PARAM_INT);

        $radios = [];
        foreach (array_merge(self::LOOKS, ['custom']) as $look) {
            $label = '<span class="local-custompage-swatch local-custompage-swatch-' . $look . '" aria-hidden="true"></span>'
                . '<strong>' . get_string('look_' . $look, 'local_custompage') . '</strong>'
                . '<span class="local-custompage-lookdesc text-muted">'
                . get_string('look_' . $look . '_desc', 'local_custompage') . '</span>';
            $radios[] = $mform->createElement('radio', 'look', '', $label, $look);
        }
        $mform->addGroup(
            $radios,
            'lookgroup',
            get_string('chooselook', 'local_custompage'),
            '',
            false,
            ['class' => 'local-custompage-looks']
        );
        $mform->setDefault('look', 'classic');

        $prompturl = new \moodle_url('/local/custompage/prompt.php');
        $mform->addElement('static', 'customintro', '', get_string('look_custom_help', 'local_custompage', $prompturl->out(false)));
        $mform->addElement(
            'filepicker',
            'designfile',
            get_string('designfile', 'local_custompage'),
            null,
            page::design_file_options()
        );
        foreach (['customintro', 'designfile'] as $element) {
            $mform->hideIf($element, 'look', 'neq', 'custom');
        }

        $this->add_action_buttons(true, get_string('finishhub', 'local_custompage'));
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
        if (
            ($data['look'] ?? '') === 'custom' && empty($this->_customdata['hascustom'])
                && !page::get_draft_file((int) ($data['designfile'] ?? 0))
        ) {
            $errors['designfile'] = get_string('choosedesignfile', 'local_custompage');
        }
        if (($data['look'] ?? '') === 'custom' && ($file = page::get_draft_file((int) ($data['designfile'] ?? 0)))) {
            [$css] = \local_custompage\local\helpers\design::import_html($file->get_content());
            try {
                \local_custompage\local\helpers\design::scope_css($css, 0);
            } catch (\moodle_exception $e) {
                $errors['designfile'] = $e->getMessage();
            }
        }
        return $errors;
    }
}
