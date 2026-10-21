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

namespace local_schedulecoursecopy\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_schedulecoursecopy.
 *
 * A job optionally names a program director and a course manager. These are
 * staff work assignments recorded at system context; deleting a user's data
 * clears their name from those roles on every job but keeps the job itself,
 * since it is an operational record of what was copied, when, and to where.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] The job fields that reference a user in a staff role. */
    const USER_FIELDS = ['programdirectorid', 'coursemanagerid'];

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_schedulecoursecopy_job',
            [
                'programdirectorid' => 'privacy:metadata:local_schedulecoursecopy_job:programdirectorid',
                'coursemanagerid' => 'privacy:metadata:local_schedulecoursecopy_job:coursemanagerid',
                'fullname' => 'privacy:metadata:local_schedulecoursecopy_job:fullname',
                'shortname' => 'privacy:metadata:local_schedulecoursecopy_job:shortname',
                'status' => 'privacy:metadata:local_schedulecoursecopy_job:status',
                'scheduledate' => 'privacy:metadata:local_schedulecoursecopy_job:scheduledate',
                'timecreated' => 'privacy:metadata:local_schedulecoursecopy_job:timecreated',
            ],
            'privacy:metadata:local_schedulecoursecopy_job'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $sql = "SELECT 1
                  FROM {local_schedulecoursecopy_job}
                 WHERE programdirectorid = :uid1 OR coursemanagerid = :uid2";
        $params = ['uid1' => $userid, 'uid2' => $userid];

        if ($DB->record_exists_sql($sql, $params)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        foreach (self::USER_FIELDS as $field) {
            $userids = $DB->get_fieldset_select('local_schedulecoursecopy_job', "DISTINCT $field", "$field IS NOT NULL");
            foreach ($userids as $userid) {
                $userlist->add_user($userid);
            }
        }
    }

    /**
     * Export all user data for the specified user, using the Privacy Subsystem.
     *
     * @param approved_contextlist $contextlist A list of contexts approved for export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $jobs = $DB->get_records_select(
            'local_schedulecoursecopy_job',
            'programdirectorid = :uid1 OR coursemanagerid = :uid2',
            ['uid1' => $userid, 'uid2' => $userid]
        );

        $data = [];
        foreach ($jobs as $job) {
            $roles = [];
            if ((int) $job->programdirectorid === $userid) {
                $roles[] = get_string('privacy:role:programdirector', 'local_schedulecoursecopy');
            }
            if ((int) $job->coursemanagerid === $userid) {
                $roles[] = get_string('privacy:role:coursemanager', 'local_schedulecoursecopy');
            }

            $data[] = (object) [
                'fullname' => $job->fullname,
                'shortname' => $job->shortname,
                'status' => $job->status,
                'roles' => implode(', ', $roles),
                'scheduledate' => \core_privacy\local\request\transform::datetime($job->scheduledate),
                'timecreated' => \core_privacy\local\request\transform::datetime($job->timecreated),
            ];
        }

        if ($data) {
            writer::with_context(\context_system::instance())->export_data(
                [get_string('pluginname', 'local_schedulecoursecopy')],
                (object) ['jobs' => $data]
            );
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        foreach (self::USER_FIELDS as $field) {
            $DB->set_field_select('local_schedulecoursecopy_job', $field, null, '1=1');
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_contextlist $contextlist A list of contexts approved for deletion.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach (self::USER_FIELDS as $field) {
            $DB->set_field('local_schedulecoursecopy_job', $field, null, [$field => $userid]);
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            foreach (self::USER_FIELDS as $field) {
                $DB->set_field('local_schedulecoursecopy_job', $field, null, [$field => $userid]);
            }
        }
    }
}
