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
 * Add or edit a category in a modal on the Categories page (core_form/modalform).
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_modal extends \core_form\dynamic_form {
    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        category_form::add_fields($mform, (int) $this->optional_param('id', 0, PARAM_INT));
    }

    /**
     * The context the form is submitted in.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Only plugin managers may save categories.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/schedulecoursecopy:manage', \context_system::instance());
    }

    /**
     * Save the category.
     *
     * @return array The saved category id, for the page to open it.
     */
    public function process_dynamic_submission() {
        return ['id' => category_manager::save($this->get_data())];
    }

    /**
     * Load the category being edited, or prefill a new subcategory from its parent.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $id = (int) $this->optional_param('id', 0, PARAM_INT);
        if ($id) {
            $this->set_data($DB->get_record('local_schedulecoursecopy_category', ['id' => $id], '*', MUST_EXIST));
        } else {
            $this->set_data(category_form::new_child_data((int) $this->optional_param('parentid', 0, PARAM_INT)));
        }
    }

    /**
     * The page the form belongs to.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/schedulecoursecopy/categories.php');
    }
}
