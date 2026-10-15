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
 * Resource hub wizard, step 1: name, who it is for, main menu.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_settings extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'step', 1);
        $mform->setType('step', PARAM_INT);

        $mform->addElement('text', 'name', get_string('hubname', 'local_custompage'), ['size' => 50, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'hubname', 'local_custompage');

        $mform->addElement('textarea', 'intro', get_string('hubintro', 'local_custompage'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('intro', PARAM_TEXT);
        $mform->addHelpButton('intro', 'hubintro', 'local_custompage');

        $options = [
            'all' => get_string('audiencechoice_all', 'local_custompage'),
            'public' => get_string('audiencechoice_public', 'local_custompage'),
            'roles' => get_string('audiencechoice_roles', 'local_custompage'),
            'people' => get_string('audiencechoice_people', 'local_custompage'),
            'managers' => get_string('audiencechoice_managers', 'local_custompage'),
        ];
        $radios = [];
        foreach ($options as $value => $label) {
            $radios[] = $mform->createElement('radio', 'audience', '', $label, $value);
        }
        $mform->addGroup($radios, 'audiencegroup', get_string('hubaudience', 'local_custompage'), '<br>', false);
        $mform->setDefault('audience', 'all');
        $mform->addHelpButton('audiencegroup', 'hubaudience', 'local_custompage');

        $roles = \local_custompage\local\helpers\audience::selectable_roles();
        $mform->addElement(
            'autocomplete',
            'roles',
            get_string('audience_rolesname', 'local_custompage'),
            $roles,
            ['multiple' => true, 'noselectionstring' => get_string('none')]
        );
        $mform->addHelpButton('roles', 'audience_rolesname', 'local_custompage');
        $mform->hideIf('roles', 'audience', 'neq', 'roles');

        $mform->addElement('autocomplete', 'users', get_string('audience_usersname', 'local_custompage'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => true,
            'noselectionstring' => get_string('none'),
            'valuehtmlcallback' => static function ($userid) {
                $user = \core_user::get_user($userid);
                return $user ? fullname($user) : false;
            },
        ]);
        $mform->hideIf('users', 'audience', 'neq', 'people');

        $mform->addElement(
            'advcheckbox',
            'showinprimarynav',
            get_string('showinprimarynav', 'local_custompage'),
            get_string('hubinmenu_desc', 'local_custompage')
        );
        $mform->setDefault('showinprimarynav', 1);
        $mform->addElement('text', 'title', get_string('menulabel', 'local_custompage'), ['size' => 30, 'maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'menulabel', 'local_custompage');
        $mform->hideIf('title', 'showinprimarynav', 'notchecked');

        page::add_ownership_fields($mform);

        $this->add_action_buttons(true, get_string('savenext', 'local_custompage'));
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
        if (($data['audience'] ?? '') === 'roles' && empty($data['roles'])) {
            $errors['roles'] = get_string('chooseroles', 'local_custompage');
        }
        if (($data['audience'] ?? '') === 'people' && empty($data['users'])) {
            $errors['users'] = get_string('choosepeople', 'local_custompage');
        }
        return $errors;
    }
}
