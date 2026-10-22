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
 * The plugin's settings, laid out inside the app: enrolment, recipients, and the three
 * notification emails. Saves the same config values as the admin settings page (settings.php).
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\navigation;
use local_schedulecoursecopy\local\ui;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$url = new moodle_url('/local/schedulecoursecopy/settings_page.php');
navigation::setup_page($url, get_string('navsettings', 'local_schedulecoursecopy'));

$component = 'local_schedulecoursecopy';
$str = fn(string $key, $a = null) => get_string($key, $component, $a);

$roles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINAL);
$defaultpdrole = get_archetype_roles('manager');
$defaultpdrole = reset($defaultpdrole);

// Name => [type, default].
$settings = [
    'defaultvisibility' => ['visibility', job_manager::VISIBILITY_COPY],
    'programdirectorrole' => ['role', $defaultpdrole ? (string) $defaultpdrole->id : '0'],
    'notifyroles' => ['roles', ''],
    'failnotify' => ['toggle', '1'],
    'scheduledemailsubject' => ['text', $str('settings_scheduledemailsubject_default')],
    'scheduledemailbody' => ['area', $str('settings_scheduledemailbody_default')],
    'liveemailsubject' => ['text', $str('settings_liveemailsubject_default')],
    'liveemailbody' => ['area', $str('settings_liveemailbody_default')],
    'failemailsubject' => ['text', $str('settings_failemailsubject_default')],
    'failemailbody' => ['area', $str('settings_failemailbody_default')],
];

if (data_submitted() && confirm_sesskey()) {
    foreach ($settings as $name => [$type, $default]) {
        switch ($type) {
            case 'role':
                $value = (string) optional_param($name, $default, PARAM_INT);
                break;
            case 'visibility':
                $value = optional_param($name, $default, PARAM_ALPHA);
                if (!isset(job_manager::visibility_options()[$value])) {
                    $value = $default;
                }
                break;
            case 'roles':
                $ids = array_filter(optional_param_array($name, [], PARAM_INT), fn($rid) => isset($roles[$rid]));
                $value = implode(',', $ids);
                break;
            case 'toggle':
                $value = optional_param($name, 0, PARAM_BOOL) ? '1' : '0';
                break;
            case 'area':
                $value = str_replace("\r\n", "\n", optional_param($name, '', PARAM_RAW_TRIMMED));
                break;
            default:
                $value = optional_param($name, '', PARAM_TEXT);
        }
        set_config($name, $value, $component);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = [];
foreach ($settings as $name => [$type, $default]) {
    $value = get_config($component, $name);
    $current[$name] = $value === false ? $default : (string) $value;
}

navigation::header('settings', $str('navsettings'), $str('settingsintro'));

/**
 * One label | control row, with the setting key under the label and an optional help line.
 *
 * @param string $name The setting name.
 * @param string $label The label.
 * @param string $control The control HTML.
 * @param string $help Help text, or ''.
 * @param bool $changed Whether the value differs from its default.
 * @param string $default The default, for the "Reset to default" link.
 * @param bool $islabel Whether the label can be a <label for>.
 * @return string
 */
function local_schedulecoursecopy_setting_row(string $name, string $label, string $control, string $help, bool $changed,
        string $default, bool $islabel = true): string {
    $reset = html_writer::tag('button', ui::icon('fa-solid fa-rotate-left') . ' '
        . get_string('settingsreset', 'local_schedulecoursecopy'), [
        'type' => 'button',
        'class' => 'btn btn-link btn-sm p-0 scc-reset',
        'data-scc-reset' => $name,
        'data-default' => $default,
        'hidden' => $changed ? null : 'hidden',
    ]);
    $labelhtml = $islabel
        ? html_writer::tag('label', $label, ['for' => 'scc-set-' . $name])
        : html_writer::span($label, '', ['id' => 'scc-set-' . $name . '-label']);
    return html_writer::div(
        html_writer::div($labelhtml . html_writer::div('local_schedulecoursecopy | ' . $name, 'scc-setting-key'),
            'scc-setting-label')
        . html_writer::div(
            $control . html_writer::div(html_writer::span($help, 'scc-setting-help') . $reset, 'scc-setting-meta'),
            'scc-setting-control'
        ),
        'scc-setting'
    );
}

$changed = fn(string $name) => $current[$name] !== $settings[$name][1];
$text = fn(string $name) => html_writer::empty_tag('input', [
    'type' => 'text', 'name' => $name, 'id' => 'scc-set-' . $name, 'value' => $current[$name],
    'class' => 'form-control scc-setting-text', 'data-scc-setting' => $name,
]);
$area = fn(string $name) => html_writer::tag('textarea', s($current[$name]), [
    'name' => $name, 'id' => 'scc-set-' . $name, 'rows' => 7, 'class' => 'form-control', 'data-scc-setting' => $name,
]);
$label = function (string $name) use ($str): string {
    if (str_ends_with($name, 'emailsubject')) {
        return $str('settingslabel_subject');
    }
    return str_ends_with($name, 'emailbody') ? $str('settingslabel_body') : $str('settings_' . $name);
};
$row = fn(string $name, string $control, string $help = '', bool $islabel = true) => local_schedulecoursecopy_setting_row(
    $name, $label($name), $control, $help, $changed($name), $settings[$name][1], $islabel);

$roleoptions = '';
foreach ($roles as $role) {
    $roleoptions .= html_writer::tag('option', s($role->localname), [
        'value' => $role->id,
        'selected' => (string) $role->id === $current['programdirectorrole'] ? 'selected' : null,
    ]);
}
$notify = array_filter(explode(',', $current['notifyroles']));
$rolegrid = '';
foreach ($roles as $role) {
    $rolegrid .= html_writer::tag('label', html_writer::empty_tag('input', [
        'type' => 'checkbox', 'name' => 'notifyroles[]', 'value' => $role->id, 'class' => 'form-check-input',
        'checked' => in_array((string) $role->id, $notify, true) ? 'checked' : null,
    ]) . ' ' . s($role->localname), ['class' => 'scc-rolecheck']);
}

$placeholders = '';
foreach (['coursefullname', 'courseshortname', 'templatefullname', 'courselink', 'startdate', 'recipientname',
        'errormessage'] as $token) {
    $placeholders .= html_writer::tag('code', '{' . $token . '}', [
        'class' => 'scc-token',
        'title' => $str('settingstoken_' . $token),
    ]);
}
$placeholderrow = html_writer::div(
    html_writer::div('', 'scc-setting-label')
    . html_writer::div(html_writer::span($str('settingsplaceholders')) . $placeholders, 'scc-setting-control scc-tokens'),
    'scc-setting'
);

$section = fn(string $key, string $rows) => html_writer::tag(
    'section',
    html_writer::tag('h2', $str('settingssection_' . $key)) . html_writer::tag('p', $str('settingssection_' . $key . '_desc'),
        ['class' => 'scc-muted small']) . $rows,
    ['class' => 'scc-settings-section']
);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'scc-settings']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$visibilityoptions = '';
foreach (job_manager::visibility_options() as $value => $optionlabel) {
    $visibilityoptions .= html_writer::tag('option', $optionlabel, [
        'value' => $value,
        'selected' => $value === $current['defaultvisibility'] ? 'selected' : null,
    ]);
}
echo $section('reveal', $row('defaultvisibility', html_writer::tag('select', $visibilityoptions, [
    'name' => 'defaultvisibility', 'id' => 'scc-set-defaultvisibility', 'class' => 'form-select scc-setting-select',
    'data-scc-setting' => 'defaultvisibility',
]), $str('settings_defaultvisibility_desc')));

echo $section('enrolment', $row('programdirectorrole', html_writer::tag('select', $roleoptions, [
    'name' => 'programdirectorrole', 'id' => 'scc-set-programdirectorrole', 'class' => 'form-select scc-setting-select',
    'data-scc-setting' => 'programdirectorrole',
]), $str('settings_programdirectorrole_desc')));

echo $section('recipients',
    $row('notifyroles', html_writer::div($rolegrid, 'scc-rolegrid', [
        'role' => 'group', 'aria-labelledby' => 'scc-set-notifyroles-label', 'data-scc-setting' => 'notifyroles',
    ]), $str('settings_notifyroles_desc'), false)
    . $row('failnotify', html_writer::tag('label', html_writer::empty_tag('input', [
        'type' => 'checkbox', 'name' => 'failnotify', 'value' => 1, 'id' => 'scc-set-failnotify',
        'class' => 'form-check-input', 'data-scc-setting' => 'failnotify',
        'checked' => $current['failnotify'] ? 'checked' : null,
    ]) . ' ' . $str('settings_failnotify_label'), ['class' => 'scc-rolecheck']), $str('settings_failnotify_desc'))
);

foreach (['scheduled', 'live', 'fail'] as $email) {
    echo $section($email . 'email',
        $row($email . 'emailsubject', $text($email . 'emailsubject'))
        . $row($email . 'emailbody', $area($email . 'emailbody'))
        . $placeholderrow
    );
}

echo html_writer::div(
    html_writer::div('', 'scc-setting-label')
    . html_writer::div(html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        'scc-setting-control'),
    'scc-setting scc-settings-save'
);
echo html_writer::end_tag('form');

navigation::footer();
