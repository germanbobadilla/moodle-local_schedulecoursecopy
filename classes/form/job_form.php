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
use local_schedulecoursecopy\local\user_picker;

/**
 * Schedule a new Schedule Course Copy job: which template, where to, and for when.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_form extends \moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $categories = category_manager::get_job_categories();
        $categoryoptions = ['' => get_string('choosedots')];
        foreach ($categories as $category) {
            $categoryoptions[$category->id] = $category->name;
        }
        $mform->addElement(
            'select',
            'categoryid',
            get_string('jobcategory', 'local_schedulecoursecopy'),
            $categoryoptions
        );
        $mform->addHelpButton('categoryid', 'jobcategory', 'local_schedulecoursecopy');
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'templateshortname', get_string('jobtemplateshortname', 'local_schedulecoursecopy'));
        $mform->setType('templateshortname', PARAM_TEXT);
        $mform->addRule('templateshortname', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('templateshortname', 'jobtemplateshortname', 'local_schedulecoursecopy');

        $mform->addElement('text', 'fullname', get_string('jobfullname', 'local_schedulecoursecopy'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('jobshortname', 'local_schedulecoursecopy'));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('jobidnumber', 'local_schedulecoursecopy'));
        $mform->setType('idnumber', PARAM_TEXT);

        $mform->addElement(
            'date_selector',
            'startdate',
            get_string('jobstartdate', 'local_schedulecoursecopy'),
            ['optional' => true]
        );
        $mform->addElement('date_selector', 'enddate', get_string('jobenddate', 'local_schedulecoursecopy'), ['optional' => true]);
        $mform->addElement(
            'date_time_selector',
            'scheduledate',
            get_string('jobscheduledate', 'local_schedulecoursecopy')
        );
        $mform->addHelpButton('scheduledate', 'jobscheduledate', 'local_schedulecoursecopy');

        $useroptions = ['' => get_string('choosedots')] + user_picker::options();
        $mform->addElement(
            'autocomplete',
            'programdirectorid',
            get_string('jobprogramdirector', 'local_schedulecoursecopy'),
            $useroptions
        );
        $mform->addRule('programdirectorid', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons();
    }

    /**
     * Validate the submitted job data.
     *
     * @param array $data The submitted form data.
     * @param array $files The submitted files.
     * @return array A list of errors keyed by form element name.
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (empty($data['categoryid'])) {
            $errors['categoryid'] = get_string('required');
        }

        if (empty($data['programdirectorid'])) {
            $errors['programdirectorid'] = get_string('required');
        }

        if (!$DB->record_exists('course', ['shortname' => trim($data['templateshortname'])])) {
            $errors['templateshortname'] = get_string('errortemplatenotfound', 'local_schedulecoursecopy');
        }

        $existing = $DB->get_record('course', ['shortname' => trim($data['shortname'])], 'id');
        $editingid = !empty($data['id']) ? (int) $data['id'] : 0;
        if ($existing) {
            $current = $editingid ? $DB->get_record('local_schedulecoursecopy_job', ['id' => $editingid]) : null;
            if (!$current || (int) $current->newcourseid !== (int) $existing->id) {
                $errors['shortname'] = get_string('errorshortnametaken', 'local_schedulecoursecopy');
            }
        }

        if (!empty($data['startdate']) && !empty($data['enddate']) && $data['enddate'] < $data['startdate']) {
            $errors['enddate'] = get_string('errorenddatebeforestart', 'local_schedulecoursecopy');
        }

        return $errors;
    }
}
