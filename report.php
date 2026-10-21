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
 * Status report: every Schedule Course Copy job across every category, with the actions
 * available at its current stage.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\event\report_viewed;
use local_schedulecoursecopy\local\category_manager;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\job_table;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/schedulecoursecopy/report.php', $categoryid ? ['categoryid' => $categoryid] : []);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('navreport', 'local_schedulecoursecopy'));
$PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
$PAGE->navbar->add(get_string('pluginname', 'local_schedulecoursecopy'), new moodle_url('/local/schedulecoursecopy/index.php'));
$PAGE->navbar->add(get_string('navreport', 'local_schedulecoursecopy'), $url);

if ($action === 'delete' && $id) {
    require_sesskey();
    job_manager::delete($id);
    redirect($url, get_string('jobdeleted', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

report_viewed::create(['context' => context_system::instance()])->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('navreport', 'local_schedulecoursecopy'));
navigation::print_tabs('report');

echo html_writer::div(get_string('navreport_desc', 'local_schedulecoursecopy'), 'text-muted mb-3');

echo html_writer::start_div('d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3');

$options = [0 => get_string('allcategories', 'local_schedulecoursecopy')] + category_manager::get_options_for_select();
$select = new single_select($url, 'categoryid', $options, $categoryid, null);
$select->set_label(get_string('jobcategory', 'local_schedulecoursecopy'));
echo $OUTPUT->render($select);

echo html_writer::link(
    new moodle_url('/local/schedulecoursecopy/categories.php'),
    get_string('navnewjob', 'local_schedulecoursecopy'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

$jobs = job_manager::get_all($categoryid ?: null);

if (!$jobs) {
    echo $OUTPUT->notification(get_string('nojobs', 'local_schedulecoursecopy'), 'info');
} else {
    echo job_table::render($jobs, $url);
}

echo $OUTPUT->footer();
