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
 * Web service functions and the predefined reporting service.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_schedulecoursecopy_get_jobs' => [
        'classname' => 'local_schedulecoursecopy\external\get_jobs',
        'description' => 'List course copy jobs, filtered by category (subcategories included), status, course, name and dates.',
        'type' => 'read',
        'capabilities' => 'local/schedulecoursecopy:viewreports',
        'ajax' => true,
    ],
];

$services = [
    'Schedule Course Copy reports' => [
        'functions' => ['local_schedulecoursecopy_get_jobs'],
        'shortname' => 'local_schedulecoursecopy_reports',
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
