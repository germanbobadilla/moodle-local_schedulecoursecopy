<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Global settings for the Schedule Course Copy plugin.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_schedulecoursecopy_index',
        get_string('pluginname', 'local_schedulecoursecopy'),
        new moodle_url('/local/schedulecoursecopy/index.php'),
        'local/schedulecoursecopy:manage'
    ));

    $settings = new admin_settingpage(
        'local_schedulecoursecopy_settings',
        get_string('settingspagename', 'local_schedulecoursecopy')
    );
    $ADMIN->add('localplugins', $settings);

    $roles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINAL);
    $roleoptions = [];
    foreach ($roles as $role) {
        $roleoptions[$role->id] = $role->localname;
    }

    $defaultpdrole = get_archetype_roles('manager');
    $defaultpdrole = reset($defaultpdrole);
    $settings->add(new admin_setting_configselect(
        'local_schedulecoursecopy/programdirectorrole',
        get_string('settings_programdirectorrole', 'local_schedulecoursecopy'),
        get_string('settings_programdirectorrole_desc', 'local_schedulecoursecopy'),
        $defaultpdrole ? $defaultpdrole->id : 0,
        $roleoptions
    ));

    $settings->add(new admin_setting_configselect(
        'local_schedulecoursecopy/defaultvisibility',
        get_string('settings_defaultvisibility', 'local_schedulecoursecopy'),
        get_string('settings_defaultvisibility_desc', 'local_schedulecoursecopy'),
        'copy',
        [
            'copy' => get_string('visibility_copy', 'local_schedulecoursecopy'),
            'start' => get_string('visibility_start', 'local_schedulecoursecopy'),
            'manual' => get_string('visibility_manual', 'local_schedulecoursecopy'),
        ]
    ));

    $settings->add(new admin_setting_configmultiselect(
        'local_schedulecoursecopy/notifyroles',
        get_string('settings_notifyroles', 'local_schedulecoursecopy'),
        get_string('settings_notifyroles_desc', 'local_schedulecoursecopy'),
        [],
        $roleoptions
    ));

    $settings->add(new admin_setting_configtext(
        'local_schedulecoursecopy/scheduledemailsubject',
        get_string('settings_scheduledemailsubject', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_scheduledemailsubject_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_schedulecoursecopy/scheduledemailbody',
        get_string('settings_scheduledemailbody', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_scheduledemailbody_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_schedulecoursecopy/liveemailsubject',
        get_string('settings_liveemailsubject', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_liveemailsubject_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_schedulecoursecopy/liveemailbody',
        get_string('settings_liveemailbody', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_liveemailbody_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_schedulecoursecopy/failnotify',
        get_string('settings_failnotify', 'local_schedulecoursecopy'),
        get_string('settings_failnotify_desc', 'local_schedulecoursecopy'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_schedulecoursecopy/failemailsubject',
        get_string('settings_failemailsubject', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_failemailsubject_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_schedulecoursecopy/failemailbody',
        get_string('settings_failemailbody', 'local_schedulecoursecopy'),
        get_string('settings_emailtokens_desc', 'local_schedulecoursecopy'),
        get_string('settings_failemailbody_default', 'local_schedulecoursecopy'),
        PARAM_TEXT
    ));
}
