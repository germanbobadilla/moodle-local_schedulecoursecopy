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
 * Reveal a copied course: make it visible, and enrol the program director
 * if one is recorded against the job (it is optional).
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$job = job_manager::get($id);
$reporturl = new moodle_url('/local/schedulecoursecopy/report.php');

if ($job->status !== job_manager::STATUS_READY_TO_REVEAL) {
    redirect(
        $reporturl,
        get_string('errorjobwrongstatus', 'local_schedulecoursecopy'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$url = new moodle_url('/local/schedulecoursecopy/reveal.php', ['id' => $id]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('jobreveal', 'local_schedulecoursecopy'));
$PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
$PAGE->navbar->add(get_string('pluginname', 'local_schedulecoursecopy'), new moodle_url('/local/schedulecoursecopy/index.php'));
$PAGE->navbar->add(get_string('navreport', 'local_schedulecoursecopy'), $reporturl);
$PAGE->navbar->add(get_string('jobreveal', 'local_schedulecoursecopy'), $url);

if ($confirm) {
    require_sesskey();
    job_manager::reveal($id);
    redirect($reporturl, get_string('jobrevealed', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
navigation::print_tabs('report');
echo $OUTPUT->confirm(
    get_string('jobrevealconfirm', 'local_schedulecoursecopy', $job->fullname),
    new moodle_url('/local/schedulecoursecopy/reveal.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
    $reporturl
);
echo $OUTPUT->footer();
