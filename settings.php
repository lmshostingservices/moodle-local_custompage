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

/**
 * Admin settings for local_custompage.
 *
 * @package    local_custompage
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('appearance', new admin_category('local_custompage', new lang_string('pluginname', 'local_custompage')));

$managepage = new admin_externalpage(
    'local_custompage_manage',
    new lang_string('managecustompages', 'local_custompage'),
    new moodle_url('/local/custompage/index.php'),
    'local/custompage:manage'
);
$ADMIN->add('local_custompage', $managepage);

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_custompage_settings', new lang_string('intranetsettings', 'local_custompage'));
    if ($ADMIN->fulltree) {
        $setting = new admin_setting_configtext(
            'local_custompage/homelabel',
            new lang_string('homelabel', 'local_custompage'),
            new lang_string('homelabel_desc', 'local_custompage'),
            '',
            PARAM_TEXT,
            30
        );
        $settings->add($setting);
        $setting = new admin_setting_configcheckbox(
            'local_custompage/homeinmenu',
            new lang_string('homeinmenu', 'local_custompage'),
            new lang_string('homeinmenu_desc', 'local_custompage'),
            0
        );
        $settings->add($setting);
        $setting = new admin_setting_confightmleditor(
            'local_custompage/homeintro',
            new lang_string('homeintro', 'local_custompage'),
            new lang_string('homeintro_desc', 'local_custompage'),
            ''
        );
        $settings->add($setting);
    }
    $ADMIN->add('local_custompage', $settings);
}
