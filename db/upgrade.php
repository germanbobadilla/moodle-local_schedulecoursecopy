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
 * Upgrade steps for the Schedule Course Copy plugin.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run Schedule Course Copy upgrade steps between versions.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool Always true.
 */
function xmldb_local_schedulecoursecopy_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026092101) {
        // Drop the email tracking and student notification fields: this version
        // sends no automated emails at all.
        $table = new xmldb_table('local_schedulecoursecopy_job');

        $fields = [
            'emailscheduledsent',
            'emailcopyingsent',
            'emailinstructorsent',
            'emailreadysent',
            'studentemailsubject',
            'studentemailbody',
            'studentemailsent',
        ];

        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Also drop the settings the removed student email feature used.
        unset_config('defaultstudentemailsubject', 'local_schedulecoursecopy');
        unset_config('defaultstudentemailbody', 'local_schedulecoursecopy');

        upgrade_plugin_savepoint(true, 2026092101, 'local', 'schedulecoursecopy');
    }

    if ($oldversion < 2026092210) {
        // Program director is no longer collected on the bulk row form: it
        // is optional, enrolled at reveal time only when one is present.
        $table = new xmldb_table('local_schedulecoursecopy_job');
        $key = new xmldb_key('programdirectorid', XMLDB_KEY_FOREIGN, ['programdirectorid'], 'user', ['id']);
        $field = new xmldb_field('programdirectorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'scheduledate');

        if ($dbman->field_exists($table, $field)) {
            // The foreign key's index blocks changing the column directly.
            $dbman->drop_key($table, $key);
            $dbman->change_field_notnull($table, $field);
            $dbman->add_key($table, $key);
        }

        upgrade_plugin_savepoint(true, 2026092210, 'local', 'schedulecoursecopy');
    }

    if ($oldversion < 2026092212) {
        // There is no more "nominate an instructor" step: the template's own
        // enrolled users, including any teacher, are copied over as they are.
        $table = new xmldb_table('local_schedulecoursecopy_job');
        $key = new xmldb_key('instructorid', XMLDB_KEY_FOREIGN, ['instructorid'], 'user', ['id']);
        $field = new xmldb_field('instructorid');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_key($table, $key);
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026092212, 'local', 'schedulecoursecopy');
    }

    if ($oldversion < 2026102200) {
        // Category ID number; job visibility mode, failure attempts and log.
        $table = new xmldb_table('local_schedulecoursecopy_category');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'moodlecategoryid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_schedulecoursecopy_job');
        $fields = [
            new xmldb_field('visibility', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'start', 'status'),
            new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'visibility'),
            new xmldb_field('log', XMLDB_TYPE_TEXT, null, null, null, null, null, 'failurereason'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Every job created before this version was revealed by hand.
        $DB->set_field('local_schedulecoursecopy_job', 'visibility', 'manual');

        // Failed jobs already failed at least once.
        $DB->set_field('local_schedulecoursecopy_job', 'attempts', 1, ['status' => 'failed']);

        // Seed the new failure email settings so the upgrade does not stop on them.
        foreach (['failemailsubject', 'failemailbody'] as $name) {
            if (get_config('local_schedulecoursecopy', $name) === false) {
                set_config($name, get_string('settings_' . $name . '_default', 'local_schedulecoursecopy'), 'local_schedulecoursecopy');
            }
        }
        if (get_config('local_schedulecoursecopy', 'failnotify') === false) {
            set_config('failnotify', 1, 'local_schedulecoursecopy');
        }

        upgrade_plugin_savepoint(true, 2026102200, 'local', 'schedulecoursecopy');
    }

    if ($oldversion < 2026102201) {
        // New copies are revealed as soon as they are copied unless an admin chooses otherwise.
        if (get_config('local_schedulecoursecopy', 'defaultvisibility') === false) {
            set_config('defaultvisibility', 'copy', 'local_schedulecoursecopy');
        }
        upgrade_plugin_savepoint(true, 2026102201, 'local', 'schedulecoursecopy');
    }

    return true;
}
