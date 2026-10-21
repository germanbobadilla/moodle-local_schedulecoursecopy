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
 * Builds the user option list used by the single job scheduling form.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_picker {
    /**
     * Return every active, non-guest user as id => "Full name (email)".
     *
     * @return array
     */
    public static function options(): array {
        global $DB;

        $guest = guest_user();
        $users = $DB->get_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND id <> :guestid',
            ['guestid' => $guest->id],
            'lastname ASC, firstname ASC'
        );

        $options = [];
        foreach ($users as $user) {
            $options[$user->id] = fullname($user) . ' (' . $user->email . ')';
        }

        return $options;
    }
}
