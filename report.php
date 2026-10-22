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
use local_schedulecoursecopy\local\ui;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHAEXT);
$q = trim(optional_param('q', '', PARAM_TEXT));

$statuslabels = ui::status_labels();
if (!isset($statuslabels[$status])) {
    $status = '';
}

$filters = array_filter(['categoryid' => $categoryid, 'status' => $status, 'q' => $q]);
$baseurl = new moodle_url('/local/schedulecoursecopy/report.php');
$url = new moodle_url($baseurl, $filters);
navigation::setup_page($url, get_string('navreport', 'local_schedulecoursecopy'));

report_viewed::create(['context' => context_system::instance()])->trigger();

navigation::header(
    'report',
    get_string('navreport', 'local_schedulecoursecopy'),
    get_string('navreport_desc', 'local_schedulecoursecopy')
);

// Category and search filter first; the chips count what is left.
$jobs = job_manager::get_all($categoryid ?: null);
if ($q !== '') {
    $needle = core_text::strtolower($q);
    $jobs = array_filter($jobs, fn($j) => str_contains(core_text::strtolower($j->fullname . ' ' . $j->shortname), $needle));
}

$chips = '';
foreach (['' => get_string('all')] + $statuslabels as $key => $label) {
    $count = $key === '' ? count($jobs) : count(array_filter($jobs, fn($j) => $j->status === $key));
    $active = $status === $key;
    $chips .= html_writer::link(
        new moodle_url($baseurl, array_filter(['categoryid' => $categoryid, 'q' => $q, 'status' => $key])),
        $label . ' ' . html_writer::span($count, 'scc-chip-count'),
        ['class' => 'scc-chip' . ($active ? ' is-active' : ''), 'aria-current' => $active ? 'true' : null]
    );
}

$catoptions = html_writer::tag('option', get_string('allcategories', 'local_schedulecoursecopy'), ['value' => 0]);
foreach (category_manager::get_options_for_select() as $id => $label) {
    $catoptions .= html_writer::tag('option', s($label), [
        'value' => $id,
        'selected' => (int) $id === $categoryid ? 'selected' : null,
    ]);
}
$filterform = html_writer::tag(
    'form',
    ($status !== '' ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]) : '')
    . html_writer::tag('select', $catoptions, [
        'name' => 'categoryid',
        'class' => 'form-select',
        'aria-label' => get_string('jobcategory', 'local_schedulecoursecopy'),
        'data-scc-autosubmit' => '1',
    ])
    . html_writer::empty_tag('input', [
        'type' => 'search',
        'name' => 'q',
        'value' => $q,
        'class' => 'form-control scc-search',
        'placeholder' => get_string('reportsearch', 'local_schedulecoursecopy'),
        'aria-label' => get_string('reportsearch', 'local_schedulecoursecopy'),
    ])
    . html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-outline-secondary']),
    ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'scc-report-search']
);
echo html_writer::div(html_writer::div($chips, 'scc-chips') . $filterform, 'scc-report-filters');

if ($status !== '') {
    $jobs = array_filter($jobs, fn($j) => $j->status === $status);
}
usort($jobs, fn($a, $b) => ($b->status === job_manager::STATUS_FAILED) <=> ($a->status === job_manager::STATUS_FAILED)
    ?: $b->scheduledate <=> $a->scheduledate);

if (!$jobs) {
    echo html_writer::div(get_string('nojobs', 'local_schedulecoursecopy'), 'scc-panel scc-empty');
} else {
    $bulkbutton = fn(string $action, string $label, string $class) => html_writer::tag('button', $label, [
        'type' => 'submit', 'name' => 'action', 'value' => $action, 'class' => 'btn btn-sm ' . $class,
    ]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/schedulecoursecopy/job_action.php'))->out(false),
        'data-scc-report' => '1',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl', 'value' => $url->out_as_local_url(false)]);
    echo html_writer::div(
        html_writer::tag('strong', '', ['data-scc-selcount' => '1'])
        . $bulkbutton('retry', get_string('reportretryfailed', 'local_schedulecoursecopy'), 'btn-outline-primary')
        . $bulkbutton('reveal', get_string('jobrevealnow', 'local_schedulecoursecopy'), 'btn-outline-primary')
        . $bulkbutton('cancel', get_string('reportcancelscheduled', 'local_schedulecoursecopy'), 'btn-outline-danger')
        . html_writer::tag('button', get_string('reportclear', 'local_schedulecoursecopy'), [
            'type' => 'button', 'class' => 'btn btn-sm btn-link ms-auto', 'data-scc-clearsel' => '1',
        ]),
        'scc-bulkbar',
        ['hidden' => 'hidden', 'data-scc-bulkbar' => '1']
    );
    echo job_table::render_report($jobs, $url);
    echo html_writer::end_tag('form');
}

navigation::footer($url);
