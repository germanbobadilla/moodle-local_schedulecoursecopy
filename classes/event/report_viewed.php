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

namespace local_schedulecoursecopy\event;

/**
 * Event triggered when the Schedule Course Copy status report is viewed.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends \core\event\base {
    /**
     * Initialise the event metadata.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_report_viewed', 'local_schedulecoursecopy');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' viewed the Schedule Course Copy status report.";
    }

    /**
     * Return the URL of the report.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/schedulecoursecopy/report.php');
    }
}
