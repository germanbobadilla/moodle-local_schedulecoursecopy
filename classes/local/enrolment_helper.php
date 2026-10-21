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

namespace local_schedulecoursecopy\local;

/**
 * Small wrapper around manual enrolment, used to add a program director to
 * a freshly copied course.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_helper {
    /**
     * Manually enrol a user into a course with a given role, creating a manual
     * enrolment instance first if the course does not already have one.
     *
     * @param int $courseid The course to enrol into.
     * @param int $userid The user to enrol.
     * @param int $roleid The role to assign, 0 to enrol without a role.
     * @return void
     */
    public static function enrol(int $courseid, int $userid, int $roleid): void {
        global $DB;

        $manual = enrol_get_plugin('manual');
        if (!$manual) {
            throw new \moodle_exception('errornomanualenrol', 'local_schedulecoursecopy');
        }

        $instance = null;
        foreach (enrol_get_instances($courseid, true) as $candidate) {
            if ($candidate->enrol === 'manual') {
                $instance = $candidate;
                break;
            }
        }

        if (!$instance) {
            $course = get_course($courseid);
            $instanceid = $manual->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }

        $manual->enrol_user($instance, $userid, $roleid > 0 ? $roleid : null);
    }

    /**
     * Resolve a configured role id setting, falling back to an archetype's role
     * when the setting has not been stored yet.
     *
     * @param string $settingname The local_schedulecoursecopy setting name.
     * @param string $fallbackarchetype The role archetype to fall back to.
     * @return int The resolved role id, or 0 if none could be found.
     */
    public static function resolve_role_id(string $settingname, string $fallbackarchetype): int {
        $configured = get_config('local_schedulecoursecopy', $settingname);
        if ($configured !== false && $configured !== '') {
            return (int) $configured;
        }

        $roles = get_archetype_roles($fallbackarchetype);
        $role = reset($roles);
        return $role ? (int) $role->id : 0;
    }
}
