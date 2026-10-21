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
 * List, and offer to delete, Schedule Course Copy categories.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\category_manager;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/local/schedulecoursecopy/categories.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('navcategories', 'local_schedulecoursecopy'));
$PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
$PAGE->navbar->add(get_string('pluginname', 'local_schedulecoursecopy'), new moodle_url('/local/schedulecoursecopy/index.php'));
$PAGE->navbar->add(get_string('navcategories', 'local_schedulecoursecopy'), $url);

if ($deleteid) {
    require_sesskey();
    $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $deleteid], '*', MUST_EXIST);

    if (!$confirm) {
        echo $OUTPUT->header();
        navigation::print_tabs('categories');
        echo $OUTPUT->confirm(
            get_string('categorydeleteconfirm', 'local_schedulecoursecopy', $category->name),
            new moodle_url('/local/schedulecoursecopy/categories.php', [
                'delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey(),
            ]),
            $url
        );
        echo $OUTPUT->footer();
        exit;
    }

    if (!category_manager::delete($deleteid)) {
        redirect(
            $url,
            get_string('categorydeletefailed', 'local_schedulecoursecopy'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    redirect($url, get_string('categorydeleted', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('navcategories', 'local_schedulecoursecopy'));
navigation::print_tabs('categories');

echo html_writer::div(
    get_string('navcategories_desc', 'local_schedulecoursecopy'),
    'text-muted mb-3'
);

echo $OUTPUT->single_button(
    new moodle_url('/local/schedulecoursecopy/category_edit.php'),
    get_string('categoryadd', 'local_schedulecoursecopy')
);

$categories = category_manager::get_all();
$moodlecategories = \core_course_category::make_categories_list('local/schedulecoursecopy:manage');

$table = new html_table();
$table->attributes['class'] = 'generaltable mt-3';
$table->head = [
    get_string('categoryname', 'local_schedulecoursecopy'),
    get_string('categoryparent', 'local_schedulecoursecopy'),
    get_string('categorymoodlecategory', 'local_schedulecoursecopy'),
    '',
];

foreach ($categories as $category) {
    $parentname = $category->parentid && isset($categories[$category->parentid])
        ? $categories[$category->parentid]->name
        : '-';
    $moodlecategoryname = $category->moodlecategoryid && isset($moodlecategories[$category->moodlecategoryid])
        ? $moodlecategories[$category->moodlecategoryid]
        : '-';

    $viewurl = new moodle_url('/local/schedulecoursecopy/category_view.php', ['id' => $category->id]);
    $editurl = new moodle_url('/local/schedulecoursecopy/category_edit.php', ['id' => $category->id]);
    $deleteurl = new moodle_url('/local/schedulecoursecopy/categories.php', ['delete' => $category->id, 'sesskey' => sesskey()]);
    $actions = html_writer::link($editurl, get_string('edit'))
        . ' | '
        . html_writer::link($deleteurl, get_string('delete'));

    $namecell = html_writer::link($viewurl, $category->name);

    $table->data[] = [$namecell, $parentname, $moodlecategoryname, $actions];
}

if ($categories) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('categorynoneyet', 'local_schedulecoursecopy'), 'info');
}

echo $OUTPUT->footer();
