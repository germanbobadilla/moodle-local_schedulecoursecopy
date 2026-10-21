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

use local_schedulecoursecopy\event\job_created;
use local_schedulecoursecopy\event\job_status_changed;

/**
 * Creates, schedules and tracks Schedule Course Copy jobs.
 *
 * A job takes a template course through: scheduled -> copying -> ready_to_reveal
 * -> revealed (or failed at any automated step). The actual course duplication is
 * delegated entirely to Moodle's own course copy engine (\copy_helper and the
 * core_course\task\asynchronous_copy_task adhoc task). Every user enrolled in
 * the template is enrolled in the copy with their same role, and nothing else:
 * no grades, submissions, logs or other user data comes across. There is
 * deliberately no separate "nominate an instructor" step: whoever is enrolled
 * in the template is already enrolled in the copy.
 *
 * This version sends no emails: the program director is enrolled at reveal time
 * only if one was recorded against the job (it is optional). Nobody is notified
 * automatically; progress is only visible through the status report and the
 * status endpoint.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_manager {
    /** @var string Waiting for its scheduled date. */
    const STATUS_SCHEDULED = 'scheduled';

    /** @var string The core course copy adhoc task is running. */
    const STATUS_COPYING = 'copying';

    /** @var string Copied, with all of the template's own users; waiting for an admin to reveal it. */
    const STATUS_READY_TO_REVEAL = 'ready_to_reveal';

    /** @var string Live: visible, program director enrolled if one was recorded. */
    const STATUS_REVEALED = 'revealed';

    /** @var string The copy failed. See the job's failurereason. */
    const STATUS_FAILED = 'failed';

    /**
     * Create and schedule a new job.
     *
     * @param \stdClass $data Form data: categoryid, templateshortname, fullname, shortname,
     *                        idnumber, startdate, enddate, scheduledate.
     * @return int The new job id.
     */
    public static function create(\stdClass $data): int {
        global $DB, $USER;

        $template = $DB->get_record(
            'course',
            ['shortname' => trim($data->templateshortname)],
            'id, shortname, fullname',
            MUST_EXIST
        );
        $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => (int) $data->categoryid], '*', MUST_EXIST);
        if (empty($category->moodlecategoryid)) {
            throw new \moodle_exception('categorymissingmapping', 'local_schedulecoursecopy');
        }

        $record = new \stdClass();
        $record->categoryid = (int) $data->categoryid;
        $record->templatecourseid = (int) $template->id;
        $record->fullname = trim($data->fullname);
        $record->shortname = trim($data->shortname);
        $record->idnumber = trim($data->idnumber ?? '');
        $record->startdate = (int) $data->startdate;
        $record->enddate = self::end_of_day((int) $data->enddate);
        $record->scheduledate = (int) $data->scheduledate;
        $record->programdirectorid = !empty($data->programdirectorid) ? (int) $data->programdirectorid : null;
        $record->coursemanagerid = !empty($data->coursemanagerid) ? (int) $data->coursemanagerid : null;
        $record->status = self::STATUS_SCHEDULED;
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;

        $record->id = $DB->insert_record('local_schedulecoursecopy_job', $record);

        job_created::create(['objectid' => $record->id, 'context' => \context_system::instance()])->trigger();

        return $record->id;
    }

    /**
     * Update a job that has not started copying yet.
     *
     * @param int $id The job id.
     * @param \stdClass $data Form data, same shape as create().
     * @return void
     */
    public static function update(int $id, \stdClass $data): void {
        global $DB;

        $job = self::get($id);
        if ($job->status !== self::STATUS_SCHEDULED) {
            throw new \moodle_exception('errorjobnoteditable', 'local_schedulecoursecopy');
        }

        $template = $DB->get_record('course', ['shortname' => trim($data->templateshortname)], 'id', MUST_EXIST);
        $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => (int) $data->categoryid], '*', MUST_EXIST);
        if (empty($category->moodlecategoryid)) {
            throw new \moodle_exception('categorymissingmapping', 'local_schedulecoursecopy');
        }

        $update = new \stdClass();
        $update->id = $id;
        $update->categoryid = (int) $data->categoryid;
        $update->templatecourseid = (int) $template->id;
        $update->fullname = trim($data->fullname);
        $update->shortname = trim($data->shortname);
        $update->idnumber = trim($data->idnumber ?? '');
        $update->startdate = (int) $data->startdate;
        $update->enddate = self::end_of_day((int) $data->enddate);
        $update->scheduledate = (int) $data->scheduledate;
        $update->programdirectorid = !empty($data->programdirectorid) ? (int) $data->programdirectorid : null;
        $update->coursemanagerid = !empty($data->coursemanagerid) ? (int) $data->coursemanagerid : null;
        $update->timemodified = time();

        $DB->update_record('local_schedulecoursecopy_job', $update);
    }

    /**
     * Push a midnight timestamp to 23:59 the same day, so an end date covers
     * the whole day. Zero (no end date set) is left untouched.
     *
     * @param int $timestamp A midnight timestamp from a date_selector, or 0.
     * @return int
     */
    protected static function end_of_day(int $timestamp): int {
        if ($timestamp <= 0) {
            return 0;
        }
        return $timestamp + (23 * HOURSECS) + (59 * MINSECS);
    }

    /**
     * Delete a job that has not started copying, or that failed before copying anything.
     *
     * @param int $id The job id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;

        $job = self::get($id);
        if (!in_array($job->status, [self::STATUS_SCHEDULED, self::STATUS_FAILED], true)) {
            throw new \moodle_exception('errorjobnotdeletable', 'local_schedulecoursecopy');
        }

        $DB->delete_records('local_schedulecoursecopy_job', ['id' => $id]);
    }

    /**
     * Fetch one job.
     *
     * @param int $id The job id.
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_schedulecoursecopy_job', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Fetch jobs, optionally scoped to a category and its descendants.
     *
     * @param int|null $categoryid Restrict to this category and its descendants, or null for all.
     * @return \stdClass[]
     */
    public static function get_all(?int $categoryid = null): array {
        global $DB;

        if ($categoryid === null) {
            return $DB->get_records('local_schedulecoursecopy_job', null, 'scheduledate DESC');
        }

        $ids = category_manager::get_self_and_descendant_ids($categoryid);
        [$insql, $inparams] = $DB->get_in_or_equal($ids);
        return $DB->get_records_select('local_schedulecoursecopy_job', "categoryid $insql", $inparams, 'scheduledate DESC');
    }

    /**
     * Start every due job: submit it to Moodle's own course copy engine.
     *
     * @return void
     */
    public static function process_due_jobs(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');

        $due = $DB->get_records_select(
            'local_schedulecoursecopy_job',
            'status = :status AND scheduledate <= :now',
            ['status' => self::STATUS_SCHEDULED, 'now' => time()]
        );

        foreach ($due as $job) {
            self::start_copy($job);
        }
    }

    /**
     * Check every in-progress job and advance it once the copy finishes.
     *
     * @return void
     */
    public static function check_in_progress_jobs(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');

        $copying = $DB->get_records('local_schedulecoursecopy_job', ['status' => self::STATUS_COPYING]);

        foreach ($copying as $job) {
            self::check_progress($job);
        }
    }

    /**
     * Submit one job to Moodle's course copy engine.
     *
     * @param \stdClass $job The job record.
     * @return void
     */
    protected static function start_copy(\stdClass $job): void {
        global $DB;

        try {
            $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $job->categoryid], '*', MUST_EXIST);

            $formdata = new \stdClass();
            $formdata->courseid = (int) $job->templatecourseid;
            $formdata->fullname = $job->fullname;
            $formdata->shortname = $job->shortname;
            $formdata->category = (int) $category->moodlecategoryid;
            $formdata->visible = 0;
            $formdata->startdate = (int) $job->startdate;
            $formdata->enddate = (int) $job->enddate;
            $formdata->idnumber = (string) $job->idnumber;
            // Enrol every user from the template into the copy, with their
            // same role, and nothing else: no grades, submissions, logs or
            // any other user data comes across.
            //
            // This is Moodle's "keep enrolments, not full user data" path:
            // userdata=0 combined with at least one role listed to keep
            // (role_<id> => <id> in the form data, collected below into
            // "keptroles") makes the copy engine skip user data in the
            // backup entirely and instead plainly re-enrol each user from
            // the listed roles into the new course afterwards. Listing every
            // role actually used in the template keeps this "everyone who
            // was enrolled", not a partial subset.
            $formdata->userdata = 0;
            $templatecontext = \context_course::instance((int) $job->templatecourseid);
            foreach (get_roles_used_in_context($templatecontext, false) as $role) {
                $formdata->{'role_' . $role->id} = $role->id;
            }

            $processed = \copy_helper::process_formdata($formdata);
            $copyids = \copy_helper::create_copy($processed);

            $restore = \restore_controller::load_controller($copyids['restoreid']);
            $newcourseid = $restore->get_courseid();

            $update = new \stdClass();
            $update->id = $job->id;
            $update->backupid = $copyids['backupid'];
            $update->restoreid = $copyids['restoreid'];
            $update->newcourseid = $newcourseid;
            $DB->update_record('local_schedulecoursecopy_job', $update);

            self::transition($job->id, $job->status, self::STATUS_COPYING);
        } catch (\Throwable $e) {
            self::fail($job->id, $job->status, $e->getMessage());
        }
    }

    /**
     * Check one in-progress job's backup/restore status.
     *
     * @param \stdClass $job The job record.
     * @return void
     */
    protected static function check_progress(\stdClass $job): void {
        if (empty($job->restoreid)) {
            return;
        }

        try {
            $progress = \backup_controller_dbops::get_progress($job->restoreid);
        } catch (\dml_exception $e) {
            // The restore controller row is not there yet; try again next run.
            return;
        }

        if ((int) $progress['status'] === \backup::STATUS_FINISHED_ERR) {
            self::fail($job->id, $job->status, get_string('errorcopyfailed', 'local_schedulecoursecopy'));
            return;
        }

        if ((int) $progress['status'] === \backup::STATUS_FINISHED_OK) {
            self::transition($job->id, $job->status, self::STATUS_READY_TO_REVEAL);
        }
    }

    /**
     * Reveal a copied course: make it visible, and enrol the program
     * director if one is recorded against this job (it is not required).
     *
     * @param int $jobid The job id.
     * @return void
     */
    public static function reveal(int $jobid): void {
        global $DB;

        $job = self::get($jobid);
        if (empty($job->newcourseid)) {
            throw new \moodle_exception('errornocourseyet', 'local_schedulecoursecopy');
        }

        $DB->set_field('course', 'visible', 1, ['id' => $job->newcourseid]);

        if (!empty($job->programdirectorid)) {
            $roleid = enrolment_helper::resolve_role_id('programdirectorrole', 'manager');
            enrolment_helper::enrol($job->newcourseid, $job->programdirectorid, $roleid);
        }

        self::transition($jobid, $job->status, self::STATUS_REVEALED);
    }

    /**
     * Mark a job as failed and record why.
     *
     * @param int $jobid The job id.
     * @param string $fromstatus The status the job was in before failing.
     * @param string $reason A human readable failure reason.
     * @return void
     */
    protected static function fail(int $jobid, string $fromstatus, string $reason): void {
        global $DB;

        $update = new \stdClass();
        $update->id = $jobid;
        $update->failurereason = $reason;
        $DB->update_record('local_schedulecoursecopy_job', $update);

        self::transition($jobid, $fromstatus, self::STATUS_FAILED);
    }

    /**
     * Update a job's status, touch timemodified, and trigger the status changed event.
     *
     * @param int $jobid The job id.
     * @param string $fromstatus The previous status.
     * @param string $tostatus The new status.
     * @return void
     */
    protected static function transition(int $jobid, string $fromstatus, string $tostatus): void {
        global $DB;

        $update = new \stdClass();
        $update->id = $jobid;
        $update->status = $tostatus;
        $update->timemodified = time();
        $DB->update_record('local_schedulecoursecopy_job', $update);

        job_status_changed::create([
            'objectid' => $jobid,
            'context' => \context_system::instance(),
            'other' => ['from' => $fromstatus, 'to' => $tostatus],
        ])->trigger();
    }
}
