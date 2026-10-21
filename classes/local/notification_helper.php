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
 * Sends the two optional job-lifecycle notifications: one when a job is scheduled, one when
 * its copy is revealed (live). Both are plain Moodle messages (Web popup by default; each
 * recipient can turn on email too in their own notification preferences) with an
 * admin-editable subject/body template and a small set of {token} placeholders.
 *
 * Recipients for "scheduled" are whoever holds one of the configured notify-roles in the
 * *template* course. Recipients for "live" are the same role-holders plus every user actually
 * enrolled in the template, regardless of role — covering instructors, managers, and anyone
 * else who was actually teaching it, not just the people picked out by role.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_helper {
    /**
     * Notify the configured notify-roles that a job has just been scheduled. The new course
     * does not exist yet, so {courselink} resolves to the template course's link instead.
     *
     * @param \stdClass $job The freshly created job record.
     * @return void
     */
    public static function notify_scheduled(\stdClass $job): void {
        global $DB;

        $roleids = self::get_notify_role_ids();
        if (empty($roleids)) {
            return;
        }

        $template = $DB->get_record('course', ['id' => $job->templatecourseid], '*', MUST_EXIST);
        $recipients = self::get_users_with_roles((int) $job->templatecourseid, $roleids);
        if (empty($recipients)) {
            return;
        }

        $tokens = self::base_tokens($job, $template);
        $subjecttemplate = get_config('local_schedulecoursecopy', 'scheduledemailsubject');
        $bodytemplate = get_config('local_schedulecoursecopy', 'scheduledemailbody');

        foreach ($recipients as $recipient) {
            self::send($recipient, $subjecttemplate, $bodytemplate, $tokens, 'job_scheduled');
        }
    }

    /**
     * Notify the configured notify-roles, plus everyone enrolled in the template, that a
     * job's copy is now live (revealed).
     *
     * @param \stdClass $job The job record, already updated with newcourseid.
     * @return void
     */
    public static function notify_live(\stdClass $job): void {
        global $DB;

        if (empty($job->newcourseid)) {
            return;
        }

        $template = $DB->get_record('course', ['id' => $job->templatecourseid], '*', MUST_EXIST);
        $newcourse = $DB->get_record('course', ['id' => $job->newcourseid], '*', MUST_EXIST);

        $roleids = self::get_notify_role_ids();
        $recipients = empty($roleids) ? [] : self::get_users_with_roles((int) $job->templatecourseid, $roleids);
        $recipients += self::get_all_enrolled_users((int) $job->templatecourseid);
        if (empty($recipients)) {
            return;
        }

        $tokens = self::base_tokens($job, $template, $newcourse);
        $subjecttemplate = get_config('local_schedulecoursecopy', 'liveemailsubject');
        $bodytemplate = get_config('local_schedulecoursecopy', 'liveemailbody');

        foreach ($recipients as $recipient) {
            self::send($recipient, $subjecttemplate, $bodytemplate, $tokens, 'job_live');
        }
    }

    /**
     * The configured notify-roles setting, as role ids.
     *
     * @return int[]
     */
    protected static function get_notify_role_ids(): array {
        $configured = get_config('local_schedulecoursecopy', 'notifyroles');
        if (empty($configured)) {
            return [];
        }
        return array_map('intval', explode(',', $configured));
    }

    /**
     * Every distinct user holding any of the given roles in a course, keyed by user id so
     * callers can safely array-union this with another recipient list.
     *
     * @param int $courseid The course to check role assignments in.
     * @param int[] $roleids The role ids to match.
     * @return \stdClass[] Keyed by user id.
     */
    protected static function get_users_with_roles(int $courseid, array $roleids): array {
        $context = \context_course::instance($courseid);
        $users = [];
        foreach ($roleids as $roleid) {
            foreach (get_role_users($roleid, $context) as $user) {
                $users[$user->id] = $user;
            }
        }
        return $users;
    }

    /**
     * Every user enrolled in a course, regardless of role, keyed by user id.
     *
     * @param int $courseid The course to check enrolments in.
     * @return \stdClass[] Keyed by user id.
     */
    protected static function get_all_enrolled_users(int $courseid): array {
        $context = \context_course::instance($courseid);
        $users = get_enrolled_users($context);
        $keyed = [];
        foreach ($users as $user) {
            $keyed[$user->id] = $user;
        }
        return $keyed;
    }

    /**
     * Build the {token} replacement map shared by both notifications.
     *
     * @param \stdClass $job The job record.
     * @param \stdClass $template The template course.
     * @param \stdClass|null $newcourse The new course, once it exists (live notification only).
     * @return array<string, string>
     */
    protected static function base_tokens(\stdClass $job, \stdClass $template, ?\stdClass $newcourse = null): array {
        $courselink = '';
        if ($newcourse) {
            $courselink = (new \moodle_url('/course/view.php', ['id' => $newcourse->id]))->out(false);
        }

        return [
            'coursefullname' => $job->fullname,
            'courseshortname' => $job->shortname,
            'templatefullname' => format_string($template->fullname),
            'courselink' => $courselink,
            'startdate' => $job->startdate ? userdate($job->startdate) : '',
        ];
    }

    /**
     * Substitute {token} placeholders in a string. Unknown tokens are left as-is rather than
     * silently blanked, so a typo in the admin's template is visible instead of hidden.
     *
     * @param string $text The template text.
     * @param array<string, string> $tokens Token name (without braces) => replacement value.
     * @return string
     */
    protected static function substitute(string $text, array $tokens): string {
        $search = [];
        $replace = [];
        foreach ($tokens as $name => $value) {
            $search[] = '{' . $name . '}';
            $replace[] = $value;
        }
        return str_replace($search, $replace, $text);
    }

    /**
     * Send one message to one recipient, substituting the recipient's own name in on top of
     * the shared tokens.
     *
     * @param \stdClass $recipient The user to send to.
     * @param string $subjecttemplate The admin-configured subject template.
     * @param string $bodytemplate The admin-configured body template.
     * @param array<string, string> $tokens The shared token replacement map.
     * @param string $messagename The message provider name ('job_scheduled' or 'job_live').
     * @return void
     */
    protected static function send(
        \stdClass $recipient,
        string $subjecttemplate,
        string $bodytemplate,
        array $tokens,
        string $messagename
    ): void {
        $tokens['recipientname'] = fullname($recipient);
        $subject = self::substitute($subjecttemplate, $tokens);
        $body = self::substitute($bodytemplate, $tokens);

        $message = new \core\message\message();
        $message->component = 'local_schedulecoursecopy';
        $message->name = $messagename;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;

        message_send($message);
    }
}
