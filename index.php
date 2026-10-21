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
 * Schedule Course Copy dashboard: entry point linking to categories, scheduling and the report.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_schedulecoursecopy_index');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_schedulecoursecopy'));
navigation::print_tabs('index');

echo html_writer::tag('p', get_string('indexintro', 'local_schedulecoursecopy'), ['class' => 'mb-4']);

$cards = [
    [
        'url' => new moodle_url('/local/schedulecoursecopy/categories.php'),
        'title' => get_string('navcategories', 'local_schedulecoursecopy'),
        'body' => get_string('navcategories_desc', 'local_schedulecoursecopy'),
    ],
    [
        'url' => new moodle_url('/local/schedulecoursecopy/categories.php'),
        'title' => get_string('navnewjob', 'local_schedulecoursecopy'),
        'body' => get_string('navnewjob_desc', 'local_schedulecoursecopy'),
    ],
    [
        'url' => new moodle_url('/local/schedulecoursecopy/report.php'),
        'title' => get_string('navreport', 'local_schedulecoursecopy'),
        'body' => get_string('navreport_desc', 'local_schedulecoursecopy'),
    ],
];

echo html_writer::start_div('row row-cols-1 row-cols-md-3 g-3');
foreach ($cards as $card) {
    echo html_writer::start_div('col');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body d-flex flex-column');
    echo html_writer::tag('h3', $card['title'], ['class' => 'card-title h5']);
    echo html_writer::tag('p', $card['body'], ['class' => 'card-text flex-grow-1']);
    echo html_writer::link(
        $card['url'],
        get_string('navopen', 'local_schedulecoursecopy'),
        ['class' => 'btn btn-primary mt-auto align-self-start']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo $OUTPUT->footer();
