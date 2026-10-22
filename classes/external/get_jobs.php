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

namespace local_schedulecoursecopy\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\job_report;

/**
 * Web service: list Schedule Course Copy jobs, filtered by category (subcategories included),
 * status, course, name and dates, with each job's status, course link and course dates.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_jobs extends external_api {
    /**
     * Describe the parameters. Every filter is optional; 0 or '' means "any".
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT,
                'Plugin category id; its subcategories are included. 0 for every category', VALUE_DEFAULT, 0),
            'status' => new external_value(PARAM_ALPHAEXT,
                'scheduled, copying, ready_to_reveal, revealed, failed or cancelled. Empty for every status except cancelled',
                VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Copied (live) course id. 0 for any', VALUE_DEFAULT, 0),
            'templatecourseid' => new external_value(PARAM_INT, 'Template course id. 0 for any', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Part of the course full or short name', VALUE_DEFAULT, ''),
            'startfrom' => new external_value(PARAM_INT, 'Course start date on or after (Unix time). 0 for any',
                VALUE_DEFAULT, 0),
            'startto' => new external_value(PARAM_INT, 'Course start date on or before (Unix time). 0 for any',
                VALUE_DEFAULT, 0),
            'copyfrom' => new external_value(PARAM_INT, 'Copy date on or after (Unix time). 0 for any', VALUE_DEFAULT, 0),
            'copyto' => new external_value(PARAM_INT, 'Copy date on or before (Unix time). 0 for any', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * List the matching jobs.
     *
     * @param int $categoryid
     * @param string $status
     * @param int $courseid
     * @param int $templatecourseid
     * @param string $search
     * @param int $startfrom
     * @param int $startto
     * @param int $copyfrom
     * @param int $copyto
     * @return array
     */
    public static function execute(int $categoryid = 0, string $status = '', int $courseid = 0, int $templatecourseid = 0,
            string $search = '', int $startfrom = 0, int $startto = 0, int $copyfrom = 0, int $copyto = 0): array {
        $filters = self::validate_parameters(self::execute_parameters(), compact('categoryid', 'status', 'courseid',
            'templatecourseid', 'search', 'startfrom', 'startto', 'copyfrom', 'copyto'));

        self::validate_context(\context_system::instance());
        job_report::require_access();

        $statuses = [job_manager::STATUS_SCHEDULED, job_manager::STATUS_COPYING, job_manager::STATUS_READY_TO_REVEAL,
            job_manager::STATUS_REVEALED, job_manager::STATUS_FAILED, job_manager::STATUS_CANCELLED];
        if ($filters['status'] !== '' && !in_array($filters['status'], $statuses, true)) {
            throw new \invalid_parameter_exception('Unknown status: ' . $filters['status']);
        }

        $rows = job_report::export(job_report::find($filters));
        return [
            'total' => count($rows),
            'counts' => job_report::counts($rows),
            'jobs' => $rows,
        ];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Number of jobs returned'),
            'counts' => new external_multiple_structure(new external_single_structure([
                'status' => new external_value(PARAM_ALPHAEXT, 'Status'),
                'count' => new external_value(PARAM_INT, 'Jobs in this status among the results'),
            ]), 'Jobs per status among the results'),
            'jobs' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Job id'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'status' => new external_value(PARAM_ALPHAEXT, 'Status code'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Status, as shown in the UI'),
                'categoryid' => new external_value(PARAM_INT, 'Plugin category id'),
                'categoryname' => new external_value(PARAM_TEXT, 'Plugin category name'),
                'categorypath' => new external_value(PARAM_TEXT, 'Full category path, e.g. Main Campus / Nursing / VN'),
                'topcategoryid' => new external_value(PARAM_INT, 'Id of the top level category (e.g. the campus)'),
                'moodlecategoryid' => new external_value(PARAM_INT, 'Moodle course category the course is created in'),
                'templatecourseid' => new external_value(PARAM_INT, 'Template course id'),
                'templateshortname' => new external_value(PARAM_TEXT, 'Template short name, empty if it was deleted'),
                'courseid' => new external_value(PARAM_INT, 'Copied course id, 0 until it exists'),
                'courseurl' => new external_value(PARAM_URL, 'Link to the copied course, empty until it exists'),
                'scheduledate' => new external_value(PARAM_INT, 'Copy date (Unix time)'),
                'startdate' => new external_value(PARAM_INT, 'Course start date (Unix time, 0 if unset)'),
                'enddate' => new external_value(PARAM_INT, 'Course end date (Unix time, 0 if unset)'),
                'visibility' => new external_value(PARAM_ALPHA, 'start, copy or manual'),
                'attempts' => new external_value(PARAM_INT, 'Failed attempts'),
                'failurereason' => new external_value(PARAM_TEXT, 'Why the last attempt failed'),
                'timecreated' => new external_value(PARAM_INT, 'When the job was scheduled'),
                'timemodified' => new external_value(PARAM_INT, 'When the job last changed'),
            ])),
        ]);
    }
}
