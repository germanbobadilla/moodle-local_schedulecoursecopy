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
 * Filters jobs and turns them into plain report rows. Shared by the web service
 * (local_schedulecoursecopy_get_jobs) and the session-authenticated status.php endpoint.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_report {
    /**
     * Find jobs matching the filters. Every filter is optional; cancelled jobs are only
     * returned when asked for by status.
     *
     * @param array $filters categoryid (includes subcategories), status, courseid (the copied
     *                       course), templatecourseid, search (full or short name), startfrom,
     *                       startto, copyfrom, copyto (Unix timestamps).
     * @return \stdClass[] Jobs, newest copy date first.
     */
    public static function find(array $filters): array {
        global $DB;

        $where = [];
        $params = [];

        if (!empty($filters['categoryid'])) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                category_manager::get_self_and_descendant_ids((int) $filters['categoryid']),
                SQL_PARAMS_NAMED,
                'cat'
            );
            $where[] = "categoryid $insql";
            $params += $inparams;
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        } else {
            $where[] = 'status <> :cancelled';
            $params['cancelled'] = job_manager::STATUS_CANCELLED;
        }
        if (!empty($filters['courseid'])) {
            $where[] = 'newcourseid = :courseid';
            $params['courseid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['templatecourseid'])) {
            $where[] = 'templatecourseid = :templatecourseid';
            $params['templatecourseid'] = (int) $filters['templatecourseid'];
        }
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $like = '%' . $DB->sql_like_escape(trim($filters['search'])) . '%';
            $where[] = '(' . $DB->sql_like('fullname', ':s1', false) . ' OR ' . $DB->sql_like('shortname', ':s2', false) . ')';
            $params['s1'] = $like;
            $params['s2'] = $like;
        }
        foreach (['startfrom' => ['startdate', '>='], 'startto' => ['startdate', '<='],
                'copyfrom' => ['scheduledate', '>='], 'copyto' => ['scheduledate', '<=']] as $key => [$field, $op]) {
            if (!empty($filters[$key])) {
                $where[] = "$field $op :$key";
                $params[$key] = (int) $filters[$key];
            }
        }

        return array_values($DB->get_records_select(
            'local_schedulecoursecopy_job',
            implode(' AND ', $where),
            $params,
            'scheduledate DESC, id DESC'
        ));
    }

    /**
     * Turn jobs into report rows.
     *
     * @param \stdClass[] $jobs The jobs.
     * @return array[] One row per job; see the web service's return description for the fields.
     */
    public static function export(array $jobs): array {
        $categories = category_manager::get_all();
        $templates = ui::template_shortnames($jobs);
        $labels = ui::status_labels() + [
            job_manager::STATUS_CANCELLED => get_string('status_cancelled', 'local_schedulecoursecopy'),
        ];

        $rows = [];
        foreach ($jobs as $job) {
            $category = $categories[$job->categoryid] ?? null;
            $path = category_manager::get_path((int) $job->categoryid, $categories);
            $rows[] = [
                'id' => (int) $job->id,
                'fullname' => $job->fullname,
                'shortname' => $job->shortname,
                'status' => $job->status,
                'statuslabel' => $labels[$job->status] ?? $job->status,
                'categoryid' => (int) $job->categoryid,
                'categoryname' => $category ? $category->name : '',
                'categorypath' => implode(' / ', array_map(fn($c) => $c->name, $path)),
                'topcategoryid' => $path ? (int) reset($path)->id : 0,
                'moodlecategoryid' => $category ? (int) $category->moodlecategoryid : 0,
                'templatecourseid' => (int) $job->templatecourseid,
                'templateshortname' => $templates[$job->templatecourseid] ?? '',
                'courseid' => (int) $job->newcourseid,
                'courseurl' => $job->newcourseid
                    ? (new \moodle_url('/course/view.php', ['id' => $job->newcourseid]))->out(false)
                    : '',
                'scheduledate' => (int) $job->scheduledate,
                'startdate' => (int) $job->startdate,
                'enddate' => (int) $job->enddate,
                'visibility' => $job->visibility,
                'attempts' => (int) $job->attempts,
                'failurereason' => (string) $job->failurereason,
                'timecreated' => (int) $job->timecreated,
                'timemodified' => (int) $job->timemodified,
            ];
        }
        return $rows;
    }

    /**
     * Count rows per status.
     *
     * @param array[] $rows Rows from export().
     * @return array[] [['status' => ..., 'count' => ...], ...] in lifecycle order.
     */
    public static function counts(array $rows): array {
        $counts = [];
        foreach (array_keys(ui::status_labels()) as $status) {
            $counts[] = ['status' => $status, 'count' => count(array_filter($rows, fn($r) => $r['status'] === $status))];
        }
        return $counts;
    }

    /**
     * Throw unless the user may read reports: plugin managers, or holders of the read-only
     * "View reports" capability (meant for reporting service accounts).
     *
     * @return void
     */
    public static function require_access(): void {
        $context = \context_system::instance();
        if (!has_capability('local/schedulecoursecopy:manage', $context)) {
            require_capability('local/schedulecoursecopy:viewreports', $context);
        }
    }
}
