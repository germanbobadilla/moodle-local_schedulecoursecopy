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
 * One category: add one or more courses to it as rows in a table, and see
 * the courses already scheduled or copied inside it.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\bulk_course_table;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\job_table;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$jobid = optional_param('jobid', 0, PARAM_INT);

$category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $id], '*', MUST_EXIST);
$categoriesurl = new moodle_url('/local/schedulecoursecopy/categories.php');
$url = new moodle_url('/local/schedulecoursecopy/category_view.php', ['id' => $id]);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($category->name);
$PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
$PAGE->navbar->add(get_string('pluginname', 'local_schedulecoursecopy'), new moodle_url('/local/schedulecoursecopy/index.php'));
$PAGE->navbar->add(get_string('navcategories', 'local_schedulecoursecopy'), $categoriesurl);
$PAGE->navbar->add($category->name, $url);

if ($action === 'delete' && $jobid) {
    require_sesskey();
    job_manager::delete($jobid);
    redirect($url, get_string('jobdeleted', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($category->name);
navigation::print_tabs('categories');

if (empty($category->moodlecategoryid)) {
    echo $OUTPUT->notification(get_string('categorymappingneeded', 'local_schedulecoursecopy'), 'warning');
    echo $OUTPUT->single_button(
        new moodle_url('/local/schedulecoursecopy/category_edit.php', ['id' => $category->id]),
        get_string('categoryedit', 'local_schedulecoursecopy')
    );
} else {
    $rowerrors = [];

    if (bulk_course_table::was_submitted()) {
        require_sesskey();

        $rows = bulk_course_table::parse_submitted_rows();
        $rowerrors = bulk_course_table::validate_rows($rows);
        $hasvalidationerrors = (bool) array_filter($rowerrors);

        if (!$hasvalidationerrors) {
            $scheduled = 0;
            $creationerrors = [];

            foreach ($rows as $i => $row) {
                if (bulk_course_table::is_row_blank($row)) {
                    continue;
                }

                try {
                    job_manager::create(bulk_course_table::to_job_data($row, $category->id));
                    $scheduled++;
                } catch (\Throwable $e) {
                    $creationerrors[] = get_string('bulkrowerror', 'local_schedulecoursecopy', (object) [
                        'row' => $i + 1,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($scheduled) {
                \core\notification::success(get_string('bulksummary', 'local_schedulecoursecopy', $scheduled));
            }
            foreach ($creationerrors as $error) {
                \core\notification::error($error);
            }
            if (!$scheduled && !$creationerrors) {
                \core\notification::info(get_string('bulknothingtodo', 'local_schedulecoursecopy'));
            }

            redirect($url);
        }
    } else {
        $rows = bulk_course_table::default_rows();
    }

    echo bulk_course_table::render($category->id, $url, $rows, $rowerrors);

    $PAGE->requires->js_amd_inline("(function() {
        var tbody = document.getElementById('local-schedulecoursecopy-bulkrows');
        var template = document.getElementById('local-schedulecoursecopy-rowtemplate');
        var addbtn = document.getElementById('local-schedulecoursecopy-addrow');

        function wireRemove(row) {
            var btn = row.querySelector('.local-schedulecoursecopy-removerow');
            if (!btn) { return; }
            btn.addEventListener('click', function() {
                var next = row.nextElementSibling;
                if (next && next.classList.contains('local-schedulecoursecopy-rowerror')) {
                    next.remove();
                }
                row.remove();
            });
        }

        if (tbody) {
            tbody.querySelectorAll('.local-schedulecoursecopy-bulkrow').forEach(wireRemove);
        }

        if (addbtn && template && tbody) {
            addbtn.addEventListener('click', function() {
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('.local-schedulecoursecopy-bulkrow');
                tbody.appendChild(fragment);
                if (row) { wireRemove(row); }
            });
        }
    })();");
}

echo $OUTPUT->heading(get_string('bulkexistingcourses', 'local_schedulecoursecopy'), 3, 'mt-5');

$jobs = job_manager::get_all($category->id);
if (!$jobs) {
    echo $OUTPUT->notification(get_string('nojobs', 'local_schedulecoursecopy'), 'info');
} else {
    echo job_table::render($jobs, $url, false);
}

echo $OUTPUT->footer();
