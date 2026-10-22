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
 * Retry, reveal or cancel one or more jobs, from the job drawer or the status report's
 * bulk bar. Jobs that are not at the right stage for the action are skipped.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\job_manager;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$ids = optional_param_array('ids', [], PARAM_INT);
$returnurl = new moodle_url(optional_param('returnurl', '/local/schedulecoursecopy/report.php', PARAM_LOCALURL));
$returnurl->remove_params('job');

$actions = [
    'retry' => [job_manager::STATUS_FAILED, 'retry', 'actionretried'],
    'reveal' => [job_manager::STATUS_READY_TO_REVEAL, 'reveal', 'actionrevealed'],
    'cancel' => [job_manager::STATUS_SCHEDULED, 'cancel', 'actioncancelled'],
];
if (!isset($actions[$action])) {
    throw new moodle_exception('invalidparameter', 'debug');
}
[$fromstatus, $method, $message] = $actions[$action];

$done = 0;
$errors = [];
foreach (array_unique($ids) as $id) {
    $job = $DB->get_record('local_schedulecoursecopy_job', ['id' => $id]);
    if (!$job || $job->status !== $fromstatus) {
        continue;
    }
    try {
        job_manager::$method((int) $id);
        $done++;
    } catch (\Throwable $e) {
        $errors[] = s($job->fullname) . ': ' . $e->getMessage();
    }
}

foreach ($errors as $error) {
    \core\notification::error($error);
}
if ($done) {
    \core\notification::success(get_string($message, 'local_schedulecoursecopy', $done));
} else if (!$errors) {
    \core\notification::info(get_string('actionnothing', 'local_schedulecoursecopy'));
}
redirect($returnurl);
