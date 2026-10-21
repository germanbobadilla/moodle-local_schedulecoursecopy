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

namespace local_schedulecoursecopy\task;

use local_schedulecoursecopy\local\job_manager;

/**
 * Scheduled task that starts due jobs and advances in-progress ones.
 *
 * Starting a job hands the actual course duplication off to Moodle's own course
 * copy engine, which runs on its own adhoc task; this task never performs a
 * backup or restore itself, it only submits the request and polls for its result.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_jobs extends \core\task\scheduled_task {
    /**
     * Return the localised task name shown in Site administration.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_jobs', 'local_schedulecoursecopy');
    }

    /**
     * Start due jobs and check on jobs already copying.
     *
     * @return void
     */
    public function execute() {
        job_manager::process_due_jobs();
        job_manager::check_in_progress_jobs();
    }
}
