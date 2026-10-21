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
 * JSON status endpoint for Schedule Course Copy jobs, optionally scoped to one category
 * (a program, or a whole campus, since descendants are included).
 *
 * Session authenticated and admin-only, like every other page in this plugin;
 * this is not a public or token based API.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\job_manager;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$categoryid = optional_param('categoryid', 0, PARAM_INT);

$jobs = job_manager::get_all($categoryid ?: null);

$data = [];
foreach ($jobs as $job) {
    $data[] = [
        'id' => (int) $job->id,
        'fullname' => $job->fullname,
        'shortname' => $job->shortname,
        'categoryid' => (int) $job->categoryid,
        'status' => $job->status,
        'scheduledate' => (int) $job->scheduledate,
        'newcourseid' => $job->newcourseid ? (int) $job->newcourseid : null,
        'courseurl' => $job->newcourseid
            ? (new moodle_url('/course/view.php', ['id' => $job->newcourseid]))->out(false)
            : null,
        'failurereason' => $job->failurereason,
        'timemodified' => (int) $job->timemodified,
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['categoryid' => $categoryid ?: null, 'jobs' => $data]);
