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

namespace local_schedulecoursecopy\form;

use local_schedulecoursecopy\local\category_manager;

/**
 * Create or edit an Schedule Course Copy category (a campus or a program within one).
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_form extends \moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('categoryname', 'local_schedulecoursecopy'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $parentoptions = [0 => get_string('categorynoparent', 'local_schedulecoursecopy')];
        $excludeid = $this->_customdata['excludeid'] ?? 0;
        foreach (category_manager::get_options_for_select() as $id => $label) {
            if ($id != $excludeid) {
                $parentoptions[$id] = $label;
            }
        }
        $mform->addElement('select', 'parentid', get_string('categoryparent', 'local_schedulecoursecopy'), $parentoptions);
        $mform->addHelpButton('parentid', 'categoryparent', 'local_schedulecoursecopy');

        $categories = \core_course_category::make_categories_list('local/schedulecoursecopy:manage');
        $categories = [0 => get_string('categorynomapping', 'local_schedulecoursecopy')] + $categories;
        $mform->addElement(
            'select',
            'moodlecategoryid',
            get_string('categorymoodlecategory', 'local_schedulecoursecopy'),
            $categories
        );
        $mform->addHelpButton('moodlecategoryid', 'categorymoodlecategory', 'local_schedulecoursecopy');

        $this->add_action_buttons();
    }
}
