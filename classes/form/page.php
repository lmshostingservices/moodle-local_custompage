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

use core_form\dynamic_form;
use local_custompage\constants;
use local_custompage\local\helpers\audience;
use local_custompage\local\helpers\page as pagehelper;
use local_custompage\local\models\page as page_model;
use local_custompage\permission;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Create or edit a section or a page (opened in a modal).
 *
 * A section only has the fields a section uses; a page only the fields a page uses. The type is chosen when the
 * item is created and does not change.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page extends dynamic_form {
    /** @var page_model|null|false Cached page being edited (false = not loaded yet). */
    private $custompage = false;

    /**
     * The page being edited, or null when creating.
     *
     * @return page_model|null
     */
    protected function get_custom_page(): ?page_model {
        if ($this->custompage === false) {
            $id = $this->optional_param('id', 0, PARAM_INT);
            $this->custompage = $id ? new page_model($id) : null;
        }
        return $this->custompage;
    }

    /**
     * Whether the form is for a section.
     *
     * @return bool
     */
    protected function is_section(): bool {
        if ($page = $this->get_custom_page()) {
            return $page->is_container();
        }
        return $this->optional_param('type', 'page', PARAM_ALPHA) === 'section';
    }

    /**
     * Context: the page's own context, or system when creating.
     *
     * @return \core\context
     */
    protected function get_context_for_dynamic_submission(): \core\context {
        $page = $this->get_custom_page();
        return $page ? $page->get_context() : \core\context\system::instance();
    }

    /**
     * Only page managers can use this form.
     */
    protected function check_access_for_dynamic_submission(): void {
        permission::require_can_manage();
    }

    /**
     * Editor options for the page body.
     *
     * @param \core\context $context
     * @return array
     */
    public static function content_editor_options(\core\context $context): array {
        global $CFG;
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'subdirs' => 0,
            'context' => $context,
            'trusttext' => false,
            // Page content is sanitised by the plugin (see design::sanitise_html()) so designs keep their structure.
            'noclean' => true,
        ];
    }

    /**
     * File manager options for page documents.
     *
     * @return array
     */
    public static function documents_options(): array {
        global $CFG;
        return [
            'subdirs' => 0,
            'maxfiles' => 20,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => ['document', 'presentation', 'spreadsheet', '.pdf', 'web_image', 'web_video', 'web_audio'],
        ];
    }

    /**
     * Add "Owner" and "Review by" to a form.
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_ownership_fields(\MoodleQuickForm $mform): void {
        $mform->addElement('autocomplete', 'ownerid', get_string('owner', 'local_custompage'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'noselectionstring' => get_string('noowner', 'local_custompage'),
            // A named callable rather than a closure: forms with repeating rows are cloned, and closures can't be.
            'valuehtmlcallback' => [self::class, 'owner_label'],
        ]);
        $mform->setType('ownerid', PARAM_INT);
        $mform->addHelpButton('ownerid', 'owner', 'local_custompage');
        $mform->addElement('date_selector', 'reviewby', get_string('reviewby', 'local_custompage'), ['optional' => true]);
        $mform->addHelpButton('reviewby', 'reviewby', 'local_custompage');
    }

    /**
     * Name shown for the chosen owner.
     *
     * @param int|string $userid
     * @return string|false
     */
    public static function owner_label($userid) {
        $user = $userid ? \core_user::get_user((int) $userid) : null;
        return $user ? fullname($user) : false;
    }

    /**
     * "I have read this" settings.
     *
     * @param \MoodleQuickForm $mform
     * @param string $kind 'hub' (asks for every resource) or 'page'
     * @param bool $existing Whether the page already exists (then it can ask everyone to confirm again)
     * @param bool $hubrequires Whether the page's hub already asks for every resource
     */
    public static function add_confirmation_fields(
        \MoodleQuickForm $mform,
        string $kind,
        bool $existing,
        bool $hubrequires = false
    ): void {
        $mform->addElement('header', 'ackheader', get_string('ack_header', 'local_custompage'));
        if ($kind === 'hub') {
            $mform->addElement(
                'advcheckbox',
                'requireack',
                get_string('ack_require', 'local_custompage'),
                get_string('ack_require_hub', 'local_custompage')
            );
            $mform->addHelpButton('requireack', 'ack_require', 'local_custompage');
            return;
        }
        if ($hubrequires) {
            $mform->addElement(
                'static',
                'ackbyhub',
                get_string('ack_require', 'local_custompage'),
                get_string('ack_require_byhub', 'local_custompage')
            );
        } else {
            $mform->addElement(
                'advcheckbox',
                'requireack',
                get_string('ack_require', 'local_custompage'),
                get_string('ack_require_page', 'local_custompage')
            );
            $mform->addHelpButton('requireack', 'ack_require', 'local_custompage');
        }
        if ($existing) {
            $mform->addElement('advcheckbox', 'askagain', '', get_string('ack_askagain', 'local_custompage'));
            $mform->addHelpButton('askagain', 'ack_askagain', 'local_custompage');
            if (!$hubrequires) {
                $mform->hideIf('askagain', 'requireack', 'notchecked');
            }
        }
    }

    /**
     * Owner and review date from submitted form data, ready to save.
     *
     * @param \stdClass $data
     * @return array
     */
    public static function ownership_from_data(\stdClass $data): array {
        $fields = [];
        if (property_exists($data, 'ownerid')) {
            $fields['ownerid'] = (int) $data->ownerid ?: null;
        }
        if (property_exists($data, 'reviewby')) {
            $fields['reviewby'] = (int) $data->reviewby ?: null;
        }
        if (property_exists($data, 'requireack')) {
            $fields['requireack'] = (int) !empty($data->requireack);
        }
        return $fields;
    }

    /**
     * File picker options for a design file: an HTML page, or a text file holding one (as saved from ChatGPT).
     *
     * @return array
     */
    public static function design_file_options(): array {
        return ['accepted_types' => ['.html', '.htm', '.txt'], 'maxbytes' => 2 * 1024 * 1024];
    }

    /**
     * File manager options for the card image.
     *
     * @return array
     */
    public static function card_image_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => 10 * 1024 * 1024,
            'accepted_types' => self::card_image_types(),
        ];
    }

    /**
     * Image types a card can use: web images, plus WebP and AVIF where the site knows them.
     *
     * Moodle 5.0 added WebP to its web images; on 4.x a site admin can add it under Server > File types.
     *
     * @return string[]
     */
    public static function card_image_types(): array {
        $types = ['web_image'];
        $known = \core_filetypes::get_types();
        foreach (['webp', 'avif'] as $extension) {
            if (isset($known[$extension]) && !in_array('web_image', $known[$extension]['groups'] ?? [], true)) {
                $types[] = '.' . $extension;
            }
        }
        return $types;
    }

    /**
     * Sections a page can be placed in.
     *
     * @return array id => name
     */
    protected function section_options(): array {
        global $DB;
        $options = [0 => get_string('nosection', 'local_custompage')];
        foreach ($DB->get_records('local_custompages', ['iscontainer' => 1], 'sortthread') as $record) {
            $options[$record->id] = (new page_model(0, $record))->get_formatted_name();
        }
        return $options;
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $issection = $this->is_section();
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'type', $issection ? 'section' : 'page');
        $mform->setType('type', PARAM_ALPHA);

        // Design: start from an HTML file (for example one made with ChatGPT) and/or edit the page's own CSS.
        $mform->addElement('header', 'designheader', get_string('design', 'local_custompage'));
        $mform->setExpanded('designheader', !$this->get_custom_page());
        $prompturl = new moodle_url('/local/custompage/prompt.php');
        $mform->addElement('static', 'designintro', '', get_string('design_desc', 'local_custompage', $prompturl->out(false)));
        $mform->addElement(
            'filepicker',
            'designfile',
            get_string('designfile', 'local_custompage'),
            null,
            self::design_file_options()
        );
        $mform->addHelpButton('designfile', 'designfile', 'local_custompage');
        $mform->addElement(
            'textarea',
            'designcss',
            get_string('designcss', 'local_custompage'),
            ['rows' => 8, 'cols' => 60, 'class' => 'text-monospace', 'spellcheck' => 'false']
        );
        $mform->setType('designcss', PARAM_RAW); // pipeline-ignore: PARAM_RAW — CSS, only used after scoping by the SCSS compiler.
        $mform->addHelpButton('designcss', 'designcss', 'local_custompage');

        // Basics.
        $mform->addElement('header', 'basicsheader', get_string('pagesettings', 'local_custompage'));
        $mform->setExpanded('basicsheader', true);
        $mform->addElement('text', 'name', get_string('pagename', 'local_custompage'), ['size' => 50, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if (!$issection) {
            $mform->addElement('select', 'parent', get_string('section', 'local_custompage'), $this->section_options());
            $mform->setType('parent', PARAM_INT);
            $mform->addHelpButton('parent', 'section', 'local_custompage');
            $mform->setDefault('parent', $this->optional_param('parent', 0, PARAM_INT));
        }

        $mform->addElement(
            'advcheckbox',
            'showinprimarynav',
            get_string('showinprimarynav', 'local_custompage'),
            get_string('showinprimarynav_desc', 'local_custompage')
        );
        $mform->addHelpButton('showinprimarynav', 'showinprimarynav', 'local_custompage');
        $mform->addElement('text', 'title', get_string('menulabel', 'local_custompage'), ['size' => 30, 'maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'menulabel', 'local_custompage');
        $mform->hideIf('title', 'showinprimarynav', 'notchecked');
        if (!$issection) {
            // Pages inside a section appear in the section's dropdown, not directly in the menu.
            $mform->hideIf('showinprimarynav', 'parent', 'neq', 0);
            $mform->hideIf('title', 'parent', 'neq', 0);
        }

        // Content.
        $mform->addElement('header', 'contentheader', get_string($issection ? 'sectionintro' : 'pagecontent', 'local_custompage'));
        $mform->setExpanded('contentheader', true);
        $mform->addElement(
            'editor',
            'content_editor',
            get_string($issection ? 'sectionintro' : 'pagecontent', 'local_custompage'),
            ['rows' => 12],
            self::content_editor_options($context)
        );
        $mform->setType('content_editor', PARAM_RAW); // pipeline-ignore: PARAM_RAW — editor HTML, cleaned on save and output.
        $mform->addHelpButton('content_editor', $issection ? 'sectionintro' : 'pagecontent', 'local_custompage');

        if (!$issection) {
            $mform->addElement(
                'filemanager',
                'documents',
                get_string('documents', 'local_custompage'),
                null,
                self::documents_options()
            );
            $mform->addHelpButton('documents', 'documents', 'local_custompage');

            // Card shown in the section.
            $mform->addElement('header', 'cardheader', get_string('cardsettings', 'local_custompage'));
            $mform->addElement('static', 'cardnote', '', get_string('cardsettings_desc', 'local_custompage'));
            $mform->addElement(
                'textarea',
                'cardsummary',
                get_string('cardsummary', 'local_custompage'),
                ['rows' => 3, 'maxlength' => 400]
            );
            $mform->setType('cardsummary', PARAM_TEXT);
            $mform->addHelpButton('cardsummary', 'cardsummary', 'local_custompage');
            $mform->addElement(
                'filemanager',
                'cardimage',
                get_string('cardimage', 'local_custompage'),
                null,
                self::card_image_options()
            );
            $mform->addHelpButton('cardimage', 'cardimage', 'local_custompage');
        }

        // Who can see it.
        $mform->addElement('header', 'ownershipheader', get_string('ownership', 'local_custompage'));
        self::add_ownership_fields($mform);

        // Read confirmation and standards (pages only).
        if (!$issection) {
            $existing = $this->get_custom_page();
            $hubrequires = $existing && $existing->get('parent')
                && (bool) $GLOBALS['DB']->get_field('local_custompages', 'requireack', ['id' => $existing->get('parent')]);
            self::add_confirmation_fields($mform, 'page', (bool) $existing, $hubrequires);
            \local_custompage\local\helpers\standards::add_form_fields(
                $this,
                $mform,
                $existing ? count(\local_custompage\local\helpers\standards::get_for_page((int) $existing->get('id'))) : 0
            );
        }

        $mform->addElement('header', 'audienceheader', get_string('whocansee', 'local_custompage'));
        $mform->setExpanded('audienceheader', true);
        $mform->addElement('static', 'managersnote', '', get_string('whocansee_desc', 'local_custompage'));
        if (!$issection) {
            $mform->addElement('select', 'audiencemode', get_string('audiencemode', 'local_custompage'), [
                constants::AUDIENCE_INHERIT => get_string('audiencemode_inherit', 'local_custompage'),
                constants::AUDIENCE_OWN => get_string('audiencemode_own', 'local_custompage'),
            ]);
            $mform->setType('audiencemode', PARAM_INT);
            $mform->hideIf('audiencemode', 'parent', 'eq', 0);
        }
        $mform->addElement('advcheckbox', 'aud_allusers', '', get_string('audience_allusers', 'local_custompage'));
        $mform->addElement('advcheckbox', 'aud_guests', '', get_string('audience_guests_long', 'local_custompage'));
        $roles = \local_custompage\local\helpers\audience::selectable_roles();
        $mform->addElement(
            'autocomplete',
            'aud_roles',
            get_string('audience_rolesname', 'local_custompage'),
            $roles,
            ['multiple' => true, 'noselectionstring' => get_string('none')]
        );
        $mform->addHelpButton('aud_roles', 'audience_rolesname', 'local_custompage');
        $mform->addElement('autocomplete', 'aud_users', get_string('audience_usersname', 'local_custompage'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => true,
            'noselectionstring' => get_string('none'),
            'valuehtmlcallback' => [self::class, 'owner_label'],
        ]);
        if (!$issection) {
            // When the page uses its section's audience, the choices below do not apply.
            foreach (['aud_allusers', 'aud_guests', 'aud_roles', 'aud_users'] as $element) {
                $mform->hideIf($element, 'audiencemode', 'eq', constants::AUDIENCE_INHERIT);
            }
            // A page outside a section always has its own audience: keep "Choose who can see this page" selected.
            global $PAGE;
            $PAGE->requires->js_call_amd('local_custompage/pageform', 'init');
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }
        if (!empty($data['parent']) && !$DB->record_exists('local_custompages', ['id' => $data['parent'], 'iscontainer' => 1])) {
            $errors['parent'] = get_string('invalidsection', 'local_custompage');
        }
        // Check the design CSS (from the textarea, or the uploaded file which replaces it) can be used.
        $css = (string) ($data['designcss'] ?? '');
        if ($file = self::get_draft_file((int) ($data['designfile'] ?? 0))) {
            [$css] = \local_custompage\local\helpers\design::import_html($file->get_content());
        }
        try {
            \local_custompage\local\helpers\design::scope_css($css, 0);
        } catch (\moodle_exception $e) {
            $errors[!empty($file) ? 'designfile' : 'designcss'] = $e->getMessage();
        }
        return $errors;
    }

    /**
     * Load the page (or defaults for a new one).
     */
    public function set_data_for_dynamic_submission(): void {
        $page = $this->get_custom_page();
        $context = $this->get_context_for_dynamic_submission();

        $data = (object) [
            'type' => $this->is_section() ? 'section' : 'page',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'parent' => $this->optional_param('parent', 0, PARAM_INT),
            'audiencemode' => $this->optional_param('parent', 0, PARAM_INT) ? constants::AUDIENCE_INHERIT
                : constants::AUDIENCE_OWN,
            'showinprimarynav' => $this->is_section() ? 1 : 0,
            'ownerid' => (int) $GLOBALS['USER']->id,
        ];
        if ($page) {
            $data = (object) array_merge((array) $data, (array) $page->to_record());
            $data->parent = (int) $page->get('parent');
            if (!$data->parent) {
                $data->audiencemode = constants::AUDIENCE_OWN;
            }
            $selection = audience::get_selection((int) $page->get('id'));
            $data->aud_allusers = (int) $selection->allusers;
            $data->aud_guests = (int) $selection->guests;
            $data->aud_roles = $selection->roles;
            $data->aud_users = $selection->users;
            $data = (object) array_merge(
                (array) $data,
                \local_custompage\local\helpers\standards::form_data((int) $page->get('id'))
            );
        }

        $data = file_prepare_standard_editor(
            $data,
            'content',
            self::content_editor_options($context),
            $context,
            'local_custompage',
            'content',
            0
        );

        if (!$this->is_section()) {
            foreach (['documents' => self::documents_options(), 'cardimage' => self::card_image_options()] as $area => $options) {
                $draftid = file_get_submitted_draft_itemid($area);
                file_prepare_draft_area($draftid, $page ? $context->id : null, 'local_custompage', $area, 0, $options);
                $data->$area = $draftid;
            }
        }

        $this->set_data($data);
    }

    /**
     * Save the section or page.
     *
     * @return string URL of the page
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $data->iscontainer = $this->is_section() ? 1 : 0;
        $page = pagehelper::save_from_form($data, $this->get_custom_page());
        return $page->get_url()->out(false);
    }

    /**
     * URL of the page that hosts this form.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $page = $this->get_custom_page();
        return $page ? $page->get_url() : new moodle_url('/local/custompage/index.php');
    }

    /**
     * The file uploaded into a filepicker draft area, if any.
     *
     * @param int $draftitemid
     * @return \stored_file|null
     */
    public static function get_draft_file(int $draftitemid): ?\stored_file {
        global $USER;
        if (!$draftitemid) {
            return null;
        }
        $files = get_file_storage()->get_area_files(
            \core\context\user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id DESC',
            false
        );
        return $files ? reset($files) : null;
    }
}
