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
 * Create or edit a Schedule Course Copy category (a campus or a program within one). This
 * is the no-JavaScript page; the Categories page opens category_modal instead.
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

        self::add_fields($mform, (int) ($this->_customdata['excludeid'] ?? 0));

        $this->add_action_buttons();
    }

    /**
     * Add the category fields, shared with category_modal.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param int $editingid The category being edited (it and its descendants cannot be its parent), or 0.
     * @return void
     */
    public static function add_fields(\MoodleQuickForm $mform, int $editingid): void {
        $mform->addElement('text', 'name', get_string('categoryname', 'local_schedulecoursecopy'),
            ['placeholder' => get_string('categorynameplaceholder', 'local_schedulecoursecopy')]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $excluded = $editingid ? category_manager::get_self_and_descendant_ids($editingid) : [];
        $parentoptions = [0 => get_string('categorynoparent', 'local_schedulecoursecopy')];
        foreach (category_manager::get_options_for_select() as $id => $label) {
            if (!in_array((int) $id, $excluded, true)) {
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

        $mform->addElement('text', 'idnumber', get_string('categoryidnumber', 'local_schedulecoursecopy'),
            ['placeholder' => get_string('optional', 'form')]);
        $mform->setType('idnumber', PARAM_TEXT);
    }

    /**
     * Starting values for a new subcategory: the parent, and the parent's Moodle category.
     *
     * @param int $parentid The parent category id, or 0.
     * @return \stdClass
     */
    public static function new_child_data(int $parentid): \stdClass {
        global $DB;
        $parent = $parentid ? $DB->get_record('local_schedulecoursecopy_category', ['id' => $parentid]) : null;
        return (object) [
            'id' => 0,
            'parentid' => $parent ? (int) $parent->id : 0,
            'moodlecategoryid' => $parent ? (int) $parent->moodlecategoryid : 0,
        ];
    }
}
