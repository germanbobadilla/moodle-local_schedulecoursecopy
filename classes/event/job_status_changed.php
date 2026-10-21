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
 * Event triggered whenever an Schedule Course Copy job moves to a new status.
 *
 * The event's `other` data carries `from` and `to` status strings.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_status_changed extends \core\event\base {
    /**
     * Initialise the event metadata.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_schedulecoursecopy_job';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_job_status_changed', 'local_schedulecoursecopy');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description() {
        $from = $this->other['from'] ?? '?';
        $to = $this->other['to'] ?? '?';
        return "Schedule Course Copy job '{$this->objectid}' moved from status '{$from}' to '{$to}'.";
    }

    /**
     * Return the URL to view this job.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/schedulecoursecopy/report.php', ['id' => $this->objectid]);
    }

    /**
     * This event is specific to the plugin's own table, it is not part of course backups.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_schedulecoursecopy_job', 'restore' => self::NOT_MAPPED];
    }
}
