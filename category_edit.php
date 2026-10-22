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
$parentid = optional_param('parentid', 0, PARAM_INT);
$listurl = new moodle_url('/local/schedulecoursecopy/categories.php', $id ? ['id' => $id] : ($parentid ? ['id' => $parentid] : []));
$url = new moodle_url('/local/schedulecoursecopy/category_edit.php', $id ? ['id' => $id] : ['parentid' => $parentid]);

$title = $id ? get_string('categoryedit', 'local_schedulecoursecopy') : get_string('categoryadd', 'local_schedulecoursecopy');
navigation::setup_page($url, $title);

$category = null;
if ($id) {
    $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $id], '*', MUST_EXIST);
}

$form = new category_form($url, ['excludeid' => $id]);
$form->set_data($category ?: category_form::new_child_data($parentid));

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($data = $form->get_data()) {
    $savedid = category_manager::save($data);
    redirect(new moodle_url('/local/schedulecoursecopy/categories.php', ['id' => $savedid]), get_string('categorysaved', 'local_schedulecoursecopy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

navigation::header('categories', $title);
echo html_writer::start_div('scc-panel scc-formpanel');
$form->display();
echo html_writer::end_div();
navigation::footer();
