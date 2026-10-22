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

use html_writer;
use moodle_url;

/**
 * Small rendering helpers shared by every page: status badges, date formats, icons and the
 * job detail drawer.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {
    /**
     * Every visible job status, in lifecycle order, mapped to its display label.
     * Cancelled jobs are never listed, so they are not included.
     *
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return [
            job_manager::STATUS_SCHEDULED => get_string('status_scheduled', 'local_schedulecoursecopy'),
            job_manager::STATUS_COPYING => get_string('status_copying', 'local_schedulecoursecopy'),
            job_manager::STATUS_READY_TO_REVEAL => get_string('status_ready_to_reveal', 'local_schedulecoursecopy'),
            job_manager::STATUS_REVEALED => get_string('status_revealed', 'local_schedulecoursecopy'),
            job_manager::STATUS_FAILED => get_string('status_failed', 'local_schedulecoursecopy'),
        ];
    }

    /**
     * A pill shaped status badge.
     *
     * @param string $status One of the job_manager::STATUS_* values.
     * @return string HTML.
     */
    public static function status_badge(string $status): string {
        $label = self::status_labels()[$status] ?? get_string('status_' . $status, 'local_schedulecoursecopy');
        return html_writer::span($label, 'scc-badge scc-badge-' . $status);
    }

    /**
     * A small status dot.
     *
     * @param string $status A job status, or 'all'.
     * @return string HTML.
     */
    public static function status_dot(string $status): string {
        return html_writer::span('', 'scc-dot scc-dot-' . $status, ['aria-hidden' => 'true']);
    }

    /**
     * The Font Awesome icon for a status, or for 'all'.
     *
     * @param string $status A job status, or 'all'.
     * @return string Icon classes.
     */
    public static function status_icon(string $status): string {
        return [
            'all' => 'fa-solid fa-layer-group',
            job_manager::STATUS_SCHEDULED => 'fa-regular fa-clock',
            job_manager::STATUS_COPYING => 'fa-solid fa-arrows-rotate',
            job_manager::STATUS_READY_TO_REVEAL => 'fa-solid fa-eye-slash',
            job_manager::STATUS_REVEALED => 'fa-solid fa-circle-check',
            job_manager::STATUS_FAILED => 'fa-solid fa-triangle-exclamation',
        ][$status] ?? 'fa-regular fa-circle';
    }

    /**
     * A Font Awesome icon, hidden from screen readers.
     *
     * @param string $classes e.g. 'fa-solid fa-plus'.
     * @return string HTML.
     */
    public static function icon(string $classes): string {
        return html_writer::tag('i', '', ['class' => $classes, 'aria-hidden' => 'true']);
    }

    /**
     * "Mon 22 Sep 2026", or a dash for no date.
     *
     * @param int $time Unix timestamp.
     * @return string
     */
    public static function date_long(int $time): string {
        return $time ? userdate($time, '%a %d %b %Y') : '—';
    }

    /**
     * "22 Sep 26", or a dash for no date.
     *
     * @param int $time Unix timestamp.
     * @return string
     */
    public static function date_short(int $time): string {
        return $time ? userdate($time, '%d %b %y') : '—';
    }

    /**
     * "28 Sep 26 – 18 Dec 26".
     *
     * @param \stdClass $job The job.
     * @return string
     */
    public static function runs(\stdClass $job): string {
        return self::date_short((int) $job->startdate) . ' – ' . self::date_short((int) $job->enddate);
    }

    /**
     * Every template shortname the jobs reference, keyed by course id.
     *
     * @param \stdClass[] $jobs The jobs.
     * @return array<int, string>
     */
    public static function template_shortnames(array $jobs): array {
        global $DB;

        $ids = array_unique(array_map(fn($j) => (int) $j->templatecourseid, $jobs));
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        return $DB->get_records_select_menu('course', "id $insql", $params, '', 'id, shortname');
    }

    /**
     * The URL with the job drawer open on a job.
     *
     * @param moodle_url $url The page URL.
     * @param int $jobid The job id.
     * @return moodle_url
     */
    public static function job_url(moodle_url $url, int $jobid): moodle_url {
        return new moodle_url($url, ['job' => $jobid]);
    }

    /**
     * The job detail drawer: header, failure panel, progress stepper, details and actions.
     *
     * @param \stdClass $job The job.
     * @param moodle_url $pageurl The page the drawer is open over (without the job param).
     * @return string HTML.
     */
    public static function job_drawer(\stdClass $job, moodle_url $pageurl): string {
        global $DB;

        $categories = category_manager::get_all();
        $category = $categories[$job->categoryid] ?? null;
        $template = $DB->get_record('course', ['id' => $job->templatecourseid], 'id, shortname, fullname');
        $moodlecategory = $category && $category->moodlecategoryid
            ? \core_course_category::get($category->moodlecategoryid, IGNORE_MISSING, true)
            : null;
        $scheduledby = $DB->get_record('user', ['id' => $job->usermodified]);
        $closeurl = $pageurl->out(false);

        $out = html_writer::start_div('scc-drawer-backdrop', ['data-scc-drawer' => '1']);
        $out .= html_writer::link($closeurl, '', [
            'class' => 'scc-drawer-dismiss',
            'aria-label' => get_string('closebuttontitle'),
            'tabindex' => '-1',
        ]);
        $out .= html_writer::start_tag('aside', [
            'class' => 'scc-drawer',
            'role' => 'dialog',
            'aria-modal' => 'true',
            'aria-labelledby' => 'scc-drawer-title',
        ]);

        // Header.
        $out .= html_writer::div(
            html_writer::div(
                html_writer::div(s(category_manager::get_path_string((int) $job->categoryid, $categories)), 'scc-muted small')
                    . html_writer::tag('h2', s($job->fullname), ['id' => 'scc-drawer-title', 'class' => 'scc-drawer-title'])
                    . self::status_badge($job->status),
                'scc-drawer-headtext'
            ) . html_writer::link($closeurl, self::icon('fa-solid fa-xmark'), [
                'class' => 'scc-iconbtn',
                'aria-label' => get_string('closebuttontitle'),
                'data-scc-drawer-close' => '1',
            ]),
            'scc-drawer-head'
        );

        // Failure panel.
        if ($job->status === job_manager::STATUS_FAILED) {
            $failed = html_writer::div(
                self::icon('fa-solid fa-triangle-exclamation') . ' '
                    . get_string('jobfailedattempt', 'local_schedulecoursecopy', (object) [
                        'n' => max(1, (int) $job->attempts),
                        'max' => job_manager::MAX_ATTEMPTS,
                    ]),
                'scc-fail-title'
            );
            if (!empty($job->failurereason)) {
                $failed .= html_writer::div(s($job->failurereason), 'scc-fail-reason');
            }
            if (!empty($job->log)) {
                $failed .= html_writer::tag('pre', s(trim($job->log)), ['class' => 'scc-fail-log']);
            }
            $out .= html_writer::div($failed, 'scc-fail');
        }

        // Progress.
        $out .= html_writer::start_div('scc-drawer-section');
        $out .= html_writer::div(get_string('jobprogress', 'local_schedulecoursecopy'), 'scc-drawer-label');
        $out .= self::stepper($job);
        $out .= html_writer::end_div();

        // Details.
        $notify = [];
        $roleids = array_filter(array_map('intval', explode(',', (string) get_config('local_schedulecoursecopy', 'notifyroles'))));
        if ($roleids) {
            $roles = role_fix_names($DB->get_records_list('role', 'id', $roleids), null, ROLENAME_ORIGINAL);
            $notify = array_map(fn($r) => $r->localname, $roles);
        }
        $details = [
            get_string('jobshortname', 'local_schedulecoursecopy') => s($job->shortname),
            get_string('bulkcoltemplate', 'local_schedulecoursecopy') => html_writer::span(
                $template ? s($template->shortname) : '—',
                'scc-mono'
            ),
            get_string('bulkcolscheduledate', 'local_schedulecoursecopy') => self::date_long((int) $job->scheduledate),
            get_string('jobruns', 'local_schedulecoursecopy') => self::runs($job),
            get_string('categorymoodlecategory', 'local_schedulecoursecopy') => $moodlecategory
                ? s($moodlecategory->get_nested_name(false)) : '—',
            get_string('jobvisibility', 'local_schedulecoursecopy') =>
                job_manager::visibility_options()[$job->visibility] ?? '—',
            get_string('jobscheduledby', 'local_schedulecoursecopy') => ($scheduledby ? s(fullname($scheduledby)) : '—')
                . ' · ' . self::date_short((int) $job->timecreated),
            get_string('jobnotified', 'local_schedulecoursecopy') => $notify
                ? s(implode(', ', $notify))
                : get_string('jobnotifiednobody', 'local_schedulecoursecopy'),
        ];
        $dl = '';
        foreach ($details as $label => $value) {
            $dl .= html_writer::tag('dt', $label) . html_writer::tag('dd', $value);
        }
        $out .= html_writer::div(
            html_writer::div(get_string('jobdetails', 'local_schedulecoursecopy'), 'scc-drawer-label')
                . html_writer::tag('dl', $dl, ['class' => 'scc-details']),
            'scc-drawer-section'
        );

        // Actions.
        $actions = '';
        $returnurl = (new moodle_url($pageurl))->out_as_local_url(false);
        $post = function (string $action, string $label, string $class) use ($job, $returnurl): string {
            return html_writer::tag(
                'form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids[]', 'value' => $job->id])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl', 'value' => $returnurl])
                    . html_writer::tag('button', $label, ['type' => 'submit', 'class' => $class]),
                [
                    'method' => 'post',
                    'action' => (new moodle_url('/local/schedulecoursecopy/job_action.php'))->out(false),
                    'class' => 'd-inline',
                ]
            );
        };
        if ($job->status === job_manager::STATUS_FAILED) {
            $actions .= $post('retry', self::icon('fa-solid fa-rotate-right') . ' '
                . get_string('jobretrynow', 'local_schedulecoursecopy'), 'btn btn-primary');
        }
        if ($job->status === job_manager::STATUS_READY_TO_REVEAL) {
            $actions .= $post('reveal', self::icon('fa-solid fa-eye') . ' '
                . get_string('jobrevealnow', 'local_schedulecoursecopy'), 'btn btn-primary');
        }
        if (in_array($job->status, [job_manager::STATUS_READY_TO_REVEAL, job_manager::STATUS_REVEALED], true)
                && $job->newcourseid) {
            $actions .= html_writer::link(
                new moodle_url('/course/view.php', ['id' => $job->newcourseid]),
                get_string('jobopencourse', 'local_schedulecoursecopy'),
                ['class' => 'btn btn-outline-secondary']
            );
        }
        if ($job->status === job_manager::STATUS_SCHEDULED) {
            $actions .= html_writer::link(
                new moodle_url('/local/schedulecoursecopy/job_edit.php', ['id' => $job->id, 'returnurl' => $returnurl]),
                get_string('edit'),
                ['class' => 'btn btn-outline-secondary']
            );
            $actions .= $post('cancel', get_string('jobcancelcopy', 'local_schedulecoursecopy'), 'btn btn-outline-danger');
        }
        $actions .= html_writer::link($closeurl, get_string('closebuttontitle'), [
            'class' => 'btn btn-outline-secondary ms-auto',
            'data-scc-drawer-close' => '1',
        ]);
        $out .= html_writer::div($actions, 'scc-drawer-foot');

        $out .= html_writer::end_tag('aside');
        $out .= html_writer::end_div();

        return $out;
    }

    /**
     * The four step progress stepper: Scheduled, Copying, Copied (hidden), Revealed.
     *
     * @param \stdClass $job The job.
     * @return string HTML.
     */
    protected static function stepper(\stdClass $job): string {
        $index = [
            job_manager::STATUS_SCHEDULED => 0,
            job_manager::STATUS_COPYING => 1,
            job_manager::STATUS_FAILED => 1,
            job_manager::STATUS_READY_TO_REVEAL => 2,
            job_manager::STATUS_REVEALED => 3,
            job_manager::STATUS_CANCELLED => 0,
        ][$job->status] ?? 0;

        $failed = $job->status === job_manager::STATUS_FAILED;
        $revealwhen = [
            job_manager::VISIBILITY_START => get_string('stepon', 'local_schedulecoursecopy', self::date_long((int) $job->startdate)),
            job_manager::VISIBILITY_COPY => get_string('stepwhencopied', 'local_schedulecoursecopy'),
            job_manager::VISIBILITY_MANUAL => get_string('stepmanual', 'local_schedulecoursecopy'),
        ][$job->visibility] ?? '';

        $steps = [
            [get_string('status_scheduled', 'local_schedulecoursecopy'), self::date_long((int) $job->timecreated)],
            [
                get_string('status_copying', 'local_schedulecoursecopy'),
                $failed
                    ? get_string('stepfailed', 'local_schedulecoursecopy', self::date_short((int) $job->timemodified))
                    : self::date_long((int) $job->scheduledate),
            ],
            [
                get_string('stepcopiedhidden', 'local_schedulecoursecopy'),
                $index >= 2 ? get_string('stepcoursecreated', 'local_schedulecoursecopy') : '—',
            ],
            [
                get_string('steprevealed', 'local_schedulecoursecopy'),
                $job->status === job_manager::STATUS_REVEALED ? self::date_long((int) $job->timemodified) : $revealwhen,
            ],
        ];

        $out = '';
        foreach ($steps as $i => [$label, $when]) {
            $done = $i < $index || ($i === $index && $job->status === job_manager::STATUS_REVEALED);
            $current = $i === $index && !$done;
            $state = $current && $failed ? 'failed' : ($done ? 'done' : ($current ? 'current' : 'future'));
            $icon = ['failed' => 'fa-solid fa-xmark', 'done' => 'fa-solid fa-check', 'current' => 'fa-solid fa-circle',
                'future' => ''][$state];
            $out .= html_writer::tag(
                'li',
                html_writer::span($icon ? self::icon($icon) : '', 'scc-step-mark')
                    . html_writer::div(
                        html_writer::div($label, 'scc-step-label') . html_writer::div($when, 'scc-step-when'),
                        'scc-step-text'
                    ),
                ['class' => 'scc-step is-' . $state]
            );
        }
        return html_writer::tag('ol', $out, ['class' => 'scc-steps']);
    }
}
