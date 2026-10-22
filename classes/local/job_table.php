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
 * Renders the job listing tables: the per category course list and the status report.
 * Each row opens the job detail drawer.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_table {
    /**
     * The "Courses in this category" table.
     *
     * @param \stdClass[] $jobs The jobs, newest copy date first.
     * @param moodle_url $pageurl The page the drawer opens over.
     * @return string HTML.
     */
    public static function render_category(array $jobs, moodle_url $pageurl): string {
        $templates = ui::template_shortnames($jobs);

        $head = '';
        foreach (['jobcourse', 'bulkcoltemplate', 'bulkcolscheduledate', 'jobruns', 'statuscolumn'] as $key) {
            $head .= html_writer::tag('th', get_string($key, 'local_schedulecoursecopy'), ['scope' => 'col']);
        }

        $rows = '';
        foreach ($jobs as $job) {
            $url = ui::job_url($pageurl, (int) $job->id);
            $rows .= html_writer::tag(
                'tr',
                html_writer::tag('td', html_writer::link($url, s($job->fullname), ['class' => 'scc-rowlink'])
                    . html_writer::div(s($job->shortname), 'scc-muted small'))
                . html_writer::tag('td', s($templates[$job->templatecourseid] ?? '—'), ['class' => 'scc-mono'])
                . html_writer::tag('td', ui::date_long((int) $job->scheduledate), ['class' => 'text-nowrap'])
                . html_writer::tag('td', ui::runs($job), ['class' => 'text-nowrap scc-secondary'])
                . html_writer::tag('td', ui::status_badge($job->status)),
                ['class' => 'scc-clickrow', 'data-href' => $url->out(false)]
            );
        }

        return html_writer::div(
            html_writer::tag(
                'table',
                html_writer::tag('thead', html_writer::tag('tr', $head)) . html_writer::tag('tbody', $rows),
                ['class' => 'scc-table table-reboot']
            ),
            'scc-tablewrap'
        );
    }

    /**
     * The status report table, with a selection checkbox per row.
     *
     * @param \stdClass[] $jobs The jobs, already filtered and sorted.
     * @param moodle_url $pageurl The page the drawer opens over.
     * @return string HTML.
     */
    public static function render_report(array $jobs, moodle_url $pageurl): string {
        $templates = ui::template_shortnames($jobs);
        $categories = category_manager::get_all();

        $selectall = html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'data-scc-selectall' => '1',
            'aria-label' => get_string('selectall'),
        ]);
        $head = html_writer::tag('th', $selectall, ['scope' => 'col', 'class' => 'scc-col-check']);
        foreach (['jobcourse', 'jobcategory', 'bulkcolscheduledate', 'jobruns', 'statuscolumn'] as $key) {
            $head .= html_writer::tag('th', get_string($key, 'local_schedulecoursecopy'), ['scope' => 'col']);
        }
        $head .= html_writer::tag('th', '', ['scope' => 'col']);

        $rows = '';
        foreach ($jobs as $job) {
            $url = ui::job_url($pageurl, (int) $job->id);
            $check = html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'ids[]',
                'value' => $job->id,
                'class' => 'form-check-input',
                'data-scc-select' => '1',
                'aria-label' => get_string('select') . ' ' . s($job->fullname),
            ]);
            $rows .= html_writer::tag(
                'tr',
                html_writer::tag('td', $check, ['class' => 'scc-col-check'])
                . html_writer::tag('td', html_writer::link($url, s($job->fullname), ['class' => 'scc-rowlink'])
                    . html_writer::div(
                        s($job->shortname) . ' · ' . get_string('fromtemplate', 'local_schedulecoursecopy',
                            s($templates[$job->templatecourseid] ?? '—')),
                        'scc-muted small'
                    ))
                . html_writer::tag('td', s(category_manager::get_path_string((int) $job->categoryid, $categories)),
                    ['class' => 'scc-secondary'])
                . html_writer::tag('td', ui::date_long((int) $job->scheduledate), ['class' => 'text-nowrap'])
                . html_writer::tag('td', ui::runs($job), ['class' => 'text-nowrap scc-secondary'])
                . html_writer::tag('td', ui::status_badge($job->status))
                . html_writer::tag('td', ui::icon('fa-solid fa-chevron-right'), ['class' => 'text-end scc-muted']),
                ['class' => 'scc-clickrow', 'data-href' => $url->out(false)]
            );
        }

        return html_writer::div(
            html_writer::tag(
                'table',
                html_writer::tag('thead', html_writer::tag('tr', $head)) . html_writer::tag('tbody', $rows),
                ['class' => 'scc-table table-reboot']
            ),
            'scc-tablewrap'
        );
    }

    /**
     * Every visible job status, in lifecycle order, mapped to its display label.
     *
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return ui::status_labels();
    }

    /**
     * A coloured status badge.
     *
     * @param string $status One of the job_manager::STATUS_* values.
     * @return string HTML.
     */
    public static function status_badge(string $status): string {
        return ui::status_badge($status);
    }
}
