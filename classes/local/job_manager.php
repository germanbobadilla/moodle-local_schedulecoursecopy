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
 * -> revealed (or failed at any automated step; cancelled before copying). Copied
 * courses are revealed on their start date, as soon as they are copied, or by hand,
 * depending on the job's visibility mode. The actual course duplication is
 * delegated entirely to Moodle's own course copy engine (\copy_helper and the
 * core_course\task\asynchronous_copy_task adhoc task). Every user enrolled in
 * the template is enrolled in the copy with their same role, and nothing else:
 * no grades, submissions, logs or other user data comes across. There is
 * deliberately no separate "nominate an instructor" step: whoever is enrolled
 * in the template is already enrolled in the copy.
 *
 * The program director is enrolled at reveal time only if one was recorded
 * against the job (it is optional); this never sends them a notification.
 * Separately, if the "Notify roles" setting is configured, holders of those
 * roles in the template course are emailed when a job is scheduled and again
 * once it is revealed (live); on reveal, everyone enrolled in the template is
 * emailed too. See notification_helper. With no notify roles configured,
 * nobody is notified automatically and progress is only visible through the
 * status report and the status endpoint.
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

    /** @var string Cancelled before it started copying. Hidden from every list. */
    const STATUS_CANCELLED = 'cancelled';

    /** @var string Reveal the copy automatically on its course start date. */
    const VISIBILITY_START = 'start';

    /** @var string Reveal the copy as soon as it has been copied. */
    const VISIBILITY_COPY = 'copy';

    /** @var string Keep the copy hidden until an admin reveals it. */
    const VISIBILITY_MANUAL = 'manual';

    /** @var int Attempts shown against a failed job ("attempt 2 of 3"). Retrying is always manual. */
    const MAX_ATTEMPTS = 3;

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
        $record->visibility = self::clean_visibility($data->visibility ?? '');
        $record->attempts = 0;
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;
        $record->log = self::log_line('Scheduled');

        $record->id = $DB->insert_record('local_schedulecoursecopy_job', $record);

        job_created::create(['objectid' => $record->id, 'context' => \context_system::instance()])->trigger();

        try {
            notification_helper::notify_scheduled($record);
        } catch (\Throwable $e) {
            debugging('Failed to send job scheduled notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

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
        if (isset($data->visibility)) {
            $update->visibility = self::clean_visibility($data->visibility);
        }
        $update->timemodified = time();

        $DB->update_record('local_schedulecoursecopy_job', $update);
    }

    /**
     * The visibility modes, mapped to their display labels.
     *
     * @return array<string, string>
     */
    public static function visibility_options(): array {
        return [
            self::VISIBILITY_START => get_string('visibility_start', 'local_schedulecoursecopy'),
            self::VISIBILITY_COPY => get_string('visibility_copy', 'local_schedulecoursecopy'),
            self::VISIBILITY_MANUAL => get_string('visibility_manual', 'local_schedulecoursecopy'),
        ];
    }

    /**
     * The site default visibility mode (the "Default visibility" setting), reveal when copied
     * unless an admin has chosen otherwise.
     *
     * @return string
     */
    public static function default_visibility(): string {
        $value = (string) get_config('local_schedulecoursecopy', 'defaultvisibility');
        return isset(self::visibility_options()[$value]) ? $value : self::VISIBILITY_COPY;
    }

    /**
     * Fall back to the site default for anything that is not a known visibility mode.
     *
     * @param string $value The submitted value.
     * @return string
     */
    public static function clean_visibility(string $value): string {
        return isset(self::visibility_options()[$value]) ? $value : self::default_visibility();
    }

    /**
     * Cancel a job that has not started copying yet.
     *
     * @param int $id The job id.
     * @return void
     */
    public static function cancel(int $id): void {
        $job = self::get($id);
        if ($job->status !== self::STATUS_SCHEDULED) {
            throw new \moodle_exception('errorjobwrongstatus', 'local_schedulecoursecopy');
        }
        self::append_log($id, 'Cancelled');
        self::transition($id, $job->status, self::STATUS_CANCELLED);
    }

    /**
     * Put a failed job back in the queue; it starts on the next run of the scheduled task
     * if its copy date has already passed.
     *
     * @param int $id The job id.
     * @return void
     */
    public static function retry(int $id): void {
        global $DB;

        $job = self::get($id);
        if ($job->status !== self::STATUS_FAILED) {
            throw new \moodle_exception('errorjobwrongstatus', 'local_schedulecoursecopy');
        }

        $update = new \stdClass();
        $update->id = $id;
        $update->failurereason = null;
        $update->backupid = null;
        $update->restoreid = null;
        $update->newcourseid = null;
        $DB->update_record('local_schedulecoursecopy_job', $update);

        self::append_log($id, 'Retry queued');
        self::transition($id, $job->status, self::STATUS_SCHEDULED);
    }

    /**
     * One timestamped log line, e.g. "[2026-10-20 02:00:04] Backup started".
     *
     * @param string $message The message.
     * @return string The line, with a trailing newline.
     */
    protected static function log_line(string $message): string {
        return '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }

    /**
     * Append a line to a job's log.
     *
     * @param int $id The job id.
     * @param string $message The message.
     * @return void
     */
    protected static function append_log(int $id, string $message): void {
        global $DB;
        $log = (string) $DB->get_field('local_schedulecoursecopy_job', 'log', ['id' => $id]);
        $DB->set_field('local_schedulecoursecopy_job', 'log', $log . self::log_line($message), ['id' => $id]);
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
    public static function get_all(?int $categoryid = null, bool $includecancelled = false): array {
        global $DB;

        $where = [];
        $params = [];
        if ($categoryid !== null) {
            $ids = category_manager::get_self_and_descendant_ids($categoryid);
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $where[] = "categoryid $insql";
        }
        if (!$includecancelled) {
            $where[] = 'status <> :cancelled';
            $params['cancelled'] = self::STATUS_CANCELLED;
        }

        return $DB->get_records_select(
            'local_schedulecoursecopy_job',
            $where ? implode(' AND ', $where) : '',
            $params,
            'scheduledate DESC'
        );
    }

    /**
     * Whether a short name is already taken by a course or by another job that is not cancelled.
     *
     * @param string $shortname The short name.
     * @return bool
     */
    public static function shortname_in_use(string $shortname): bool {
        global $DB;

        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            return true;
        }
        return $DB->record_exists_select(
            'local_schedulecoursecopy_job',
            'shortname = :shortname AND status <> :cancelled',
            ['shortname' => $shortname, 'cancelled' => self::STATUS_CANCELLED]
        );
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
     * Reveal every copied job whose visibility mode is "on start date" and whose start date
     * has arrived.
     *
     * @return void
     */
    public static function reveal_due_jobs(): void {
        global $DB;

        $due = $DB->get_records_select(
            'local_schedulecoursecopy_job',
            'status = :status AND visibility = :visibility AND startdate <= :now',
            ['status' => self::STATUS_READY_TO_REVEAL, 'visibility' => self::VISIBILITY_START, 'now' => time()]
        );

        foreach ($due as $job) {
            try {
                self::reveal((int) $job->id);
            } catch (\Throwable $e) {
                debugging('Failed to reveal job ' . $job->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
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

        self::append_log((int) $job->id, 'Backup started (template id ' . $job->templatecourseid . ')');

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
            self::append_log((int) $job->id, 'Copy finished, course ' . $job->newcourseid . ' created hidden');
            self::transition($job->id, $job->status, self::STATUS_READY_TO_REVEAL);
            if ($job->visibility === self::VISIBILITY_COPY) {
                self::reveal((int) $job->id);
            }
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
        if ($job->status !== self::STATUS_READY_TO_REVEAL) {
            throw new \moodle_exception('errorjobwrongstatus', 'local_schedulecoursecopy');
        }
        if (empty($job->newcourseid)) {
            throw new \moodle_exception('errornocourseyet', 'local_schedulecoursecopy');
        }

        $DB->set_field('course', 'visible', 1, ['id' => $job->newcourseid]);

        if (!empty($job->programdirectorid)) {
            $roleid = enrolment_helper::resolve_role_id('programdirectorrole', 'manager');
            enrolment_helper::enrol($job->newcourseid, $job->programdirectorid, $roleid);
        }

        self::append_log($jobid, 'Revealed to students');
        self::transition($jobid, $job->status, self::STATUS_REVEALED);

        try {
            notification_helper::notify_live(self::get($jobid));
        } catch (\Throwable $e) {
            debugging('Failed to send job live notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
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
        $update->attempts = (int) $DB->get_field('local_schedulecoursecopy_job', 'attempts', ['id' => $jobid]) + 1;
        $DB->update_record('local_schedulecoursecopy_job', $update);

        self::append_log($jobid, 'ERROR ' . $reason);
        self::transition($jobid, $fromstatus, self::STATUS_FAILED);

        try {
            notification_helper::notify_failed(self::get($jobid));
        } catch (\Throwable $e) {
            debugging('Failed to send job failed notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
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
