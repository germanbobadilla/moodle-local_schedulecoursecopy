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

/**
 * JSON report of Schedule Course Copy jobs for a logged-in browser session. Takes the same
 * filters and returns the same fields as the local_schedulecoursecopy_get_jobs web service,
 * which is the one to use from other systems (token authentication).
 *
 * Filters (all optional): categoryid (subcategories included), status, courseid,
 * templatecourseid, search, and startfrom/startto/copyfrom/copyto as Unix timestamps or
 * YYYY-MM-DD dates.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\job_report;

require(__DIR__ . '/../../config.php');

require_login();
job_report::require_access();

$date = function (string $name): int {
    $value = trim(optional_param($name, '', PARAM_TEXT));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        // "to" dates cover the whole day.
        return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3]) + (str_ends_with($name, 'to') ? DAYSECS - 1 : 0);
    }
    return (int) $value;
};

$filters = [
    'categoryid' => optional_param('categoryid', 0, PARAM_INT),
    'status' => optional_param('status', '', PARAM_ALPHAEXT),
    'courseid' => optional_param('courseid', 0, PARAM_INT),
    'templatecourseid' => optional_param('templatecourseid', 0, PARAM_INT),
    'search' => optional_param('search', '', PARAM_TEXT),
    'startfrom' => $date('startfrom'),
    'startto' => $date('startto'),
    'copyfrom' => $date('copyfrom'),
    'copyto' => $date('copyto'),
];

$rows = job_report::export(job_report::find($filters));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'filters' => array_filter($filters),
    'total' => count($rows),
    'counts' => job_report::counts($rows),
    'jobs' => $rows,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
