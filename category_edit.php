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
 * Create or edit an Schedule Course Copy category.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\form\category_form;
use local_schedulecoursecopy\local\category_manager;
use local_schedulecoursecopy\local\navigation;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$listurl = new moodle_url('/local/schedulecoursecopy/categories.php');
$url = new moodle_url('/local/schedulecoursecopy/category_edit.php', $id ? ['id' => $id] : []);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$title = $id ? get_string('categoryedit', 'local_schedulecoursecopy') : get_string('categoryadd', 'local_schedulecoursecopy');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
$PAGE->navbar->add(get_string('pluginname', 'local_schedulecoursecopy'), new moodle_url('/local/schedulecoursecopy/index.php'));
$PAGE->navbar->add(get_string('navcategories', 'local_schedulecoursecopy'), $listurl);
$PAGE->navbar->add($title, $url);

$category = null;
if ($id) {
    $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $id], '*', MUST_EXIST);
}

$form = new category_form($url, ['excludeid' => $id]);
if ($category) {
    $form->set_data($category);
}

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($data = $form->get_data()) {
    category_manager::save($data);
    redirect($listurl, get_string('categorysaved', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
navigation::print_tabs('categories');
$form->display();
echo $OUTPUT->footer();
