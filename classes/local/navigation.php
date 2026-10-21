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

namespace local_schedulecoursecopy\local;

/**
 * Renders the tab bar shared by every Schedule Course Copy admin page.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Echo the Schedule Course Copy tab bar with the given tab highlighted.
     *
     * @param string $active One of 'index', 'categories' or 'report'.
     * @return void
     */
    public static function print_tabs(string $active): void {
        global $OUTPUT;

        $tabs = [
            new \tabobject(
                'index',
                new \moodle_url('/local/schedulecoursecopy/index.php'),
                get_string('navoverview', 'local_schedulecoursecopy')
            ),
            new \tabobject(
                'categories',
                new \moodle_url('/local/schedulecoursecopy/categories.php'),
                get_string('navcategories', 'local_schedulecoursecopy')
            ),
            new \tabobject(
                'report',
                new \moodle_url('/local/schedulecoursecopy/report.php'),
                get_string('navreport', 'local_schedulecoursecopy')
            ),
        ];

        echo $OUTPUT->tabtree($tabs, $active);
    }
}
