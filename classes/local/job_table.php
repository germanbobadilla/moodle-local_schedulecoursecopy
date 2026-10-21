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
 * Renders the job listing table shared by the status report and the
 * per category view.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_table {
    /**
     * Build the HTML for a table of jobs.
     *
     * @param \stdClass[] $jobs Jobs to display, as returned by job_manager::get_all().
     * @param \moodle_url $actionurl The page actions (delete) should post/redirect back to.
     * @param bool $showcategorycolumn Whether to include the category column.
     * @return string The table HTML, or an empty string when there are no jobs.
     */
    public static function render(array $jobs, \moodle_url $actionurl, bool $showcategorycolumn = true): string {
        global $DB;

        if (!$jobs) {
            return '';
        }

        $statuslabels = [
            job_manager::STATUS_SCHEDULED => get_string('status_scheduled', 'local_schedulecoursecopy'),
            job_manager::STATUS_COPYING => get_string('status_copying', 'local_schedulecoursecopy'),
            job_manager::STATUS_READY_TO_REVEAL => get_string('status_ready_to_reveal', 'local_schedulecoursecopy'),
            job_manager::STATUS_REVEALED => get_string('status_revealed', 'local_schedulecoursecopy'),
            job_manager::STATUS_FAILED => get_string('status_failed', 'local_schedulecoursecopy'),
        ];

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-schedulecoursecopy-report';
        $table->head = [get_string('jobfullname', 'local_schedulecoursecopy')];
        if ($showcategorycolumn) {
            $table->head[] = get_string('jobcategory', 'local_schedulecoursecopy');
        }
        $table->head[] = get_string('jobscheduledate', 'local_schedulecoursecopy');
        $table->head[] = get_string('statuscolumn', 'local_schedulecoursecopy');
        $table->head[] = '';

        foreach ($jobs as $job) {
            $namecell = $job->newcourseid
                ? \html_writer::link(new \moodle_url('/course/view.php', ['id' => $job->newcourseid]), $job->fullname)
                : \html_writer::tag('span', $job->fullname);
            $namecell .= \html_writer::div($job->shortname, 'text-muted small');

            $status = \html_writer::tag(
                'span',
                $statuslabels[$job->status] ?? $job->status,
                ['class' => 'badge local-schedulecoursecopy-status-' . $job->status]
            );
            if ($job->status === job_manager::STATUS_FAILED && !empty($job->failurereason)) {
                $status .= \html_writer::div(s($job->failurereason), 'text-danger small mt-1');
            }

            $actions = [];
            if ($job->status === job_manager::STATUS_SCHEDULED) {
                $actions[] = \html_writer::link(
                    new \moodle_url('/local/schedulecoursecopy/job_edit.php', ['id' => $job->id]),
                    get_string('edit'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
                $actions[] = \html_writer::link(
                    new \moodle_url($actionurl, ['action' => 'delete', 'id' => $job->id, 'sesskey' => sesskey()]),
                    get_string('delete'),
                    ['class' => 'btn btn-sm btn-outline-danger']
                );
            }
            if ($job->status === job_manager::STATUS_READY_TO_REVEAL) {
                $actions[] = \html_writer::link(
                    new \moodle_url('/local/schedulecoursecopy/reveal.php', ['id' => $job->id]),
                    get_string('jobreveal', 'local_schedulecoursecopy'),
                    ['class' => 'btn btn-sm btn-primary']
                );
            }
            if ($job->status === job_manager::STATUS_FAILED) {
                $actions[] = \html_writer::link(
                    new \moodle_url($actionurl, ['action' => 'delete', 'id' => $job->id, 'sesskey' => sesskey()]),
                    get_string('delete'),
                    ['class' => 'btn btn-sm btn-outline-danger']
                );
            }

            $row = [$namecell];
            if ($showcategorycolumn) {
                $row[] = $DB->get_field('local_schedulecoursecopy_category', 'name', ['id' => $job->categoryid]) ?: '-';
            }
            $row[] = userdate($job->scheduledate);
            $row[] = $status;
            $row[] = \html_writer::div(implode(' ', $actions), 'd-flex gap-1 flex-wrap');

            $table->data[] = $row;
        }

        return \html_writer::table($table);
    }
}
