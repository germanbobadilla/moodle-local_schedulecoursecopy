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
 * Schedule a new Schedule Course Copy job, or edit one that has not started copying yet.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\form\job_form;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$reporturl = new moodle_url(optional_param('returnurl', '/local/schedulecoursecopy/report.php', PARAM_LOCALURL));
$url = new moodle_url('/local/schedulecoursecopy/job_edit.php', $id ? ['id' => $id] : []);

$title = $id ? get_string('jobedit', 'local_schedulecoursecopy') : get_string('navnewjob', 'local_schedulecoursecopy');
navigation::setup_page($url, $title);

$job = null;
if ($id) {
    $job = job_manager::get($id);
    if ($job->status !== job_manager::STATUS_SCHEDULED) {
        redirect(
            $reporturl,
            get_string('errorjobnoteditable', 'local_schedulecoursecopy'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$form = new job_form($url);

if ($job) {
    $template = $DB->get_record('course', ['id' => $job->templatecourseid], 'shortname');
    $formdata = clone $job;
    $formdata->templateshortname = $template ? $template->shortname : '';
    $formdata->returnurl = $reporturl->out_as_local_url(false);
    $form->set_data($formdata);
} else {
    $form->set_data(['returnurl' => $reporturl->out_as_local_url(false)]);
}

if ($form->is_cancelled()) {
    redirect($reporturl);
} else if ($data = $form->get_data()) {
    if ($id) {
        job_manager::update($id, $data);
        redirect($reporturl, get_string('jobsaved', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        job_manager::create($data);
        redirect(
            $reporturl,
            get_string('jobscheduled', 'local_schedulecoursecopy'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

navigation::header('report', $title, get_string('jobformintro', 'local_schedulecoursecopy'));
echo html_writer::start_div('scc-panel scc-formpanel');
$form->display();
echo html_writer::end_div();
navigation::footer();
