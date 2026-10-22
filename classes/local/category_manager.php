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
 * Manages the plugin's own campus/program category tree.
 *
 * This tree is independent of, but each leaf usually points at, a real Moodle
 * course category (the place new live courses are created).
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {
    /**
     * Create or update a category.
     *
     * @param \stdClass $data Must contain name, parentid (0 for none) and moodlecategoryid (0 for none);
     *                        idnumber is optional.
     * @return int The category id.
     */
    public static function save(\stdClass $data): int {
        global $DB, $USER;

        $record = new \stdClass();
        $record->name = trim($data->name);
        $record->parentid = !empty($data->parentid) ? (int) $data->parentid : null;
        $record->moodlecategoryid = !empty($data->moodlecategoryid) ? (int) $data->moodlecategoryid : null;
        $record->idnumber = trim($data->idnumber ?? '') !== '' ? trim($data->idnumber) : null;
        $record->usermodified = $USER->id;
        $record->timemodified = time();

        if (!empty($data->id)) {
            $record->id = (int) $data->id;
            $DB->update_record('local_schedulecoursecopy_category', $record);
            return $record->id;
        }

        $record->timecreated = $record->timemodified;
        return $DB->insert_record('local_schedulecoursecopy_category', $record);
    }

    /**
     * Delete a category. Refuses when it still has children or jobs.
     *
     * @param int $id The category id.
     * @return bool True on success, false when the category is not empty.
     */
    public static function delete(int $id): bool {
        global $DB;

        if ($DB->record_exists('local_schedulecoursecopy_category', ['parentid' => $id])) {
            return false;
        }
        if ($DB->record_exists('local_schedulecoursecopy_job', ['categoryid' => $id])) {
            return false;
        }

        $DB->delete_records('local_schedulecoursecopy_category', ['id' => $id]);
        return true;
    }

    /**
     * Return every category, indexed by id.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;
        return $DB->get_records('local_schedulecoursecopy_category', null, 'name ASC');
    }

    /**
     * Return the categories that can directly hold jobs (they have a Moodle category mapped).
     *
     * @return \stdClass[]
     */
    public static function get_job_categories(): array {
        global $DB;
        return $DB->get_records_select(
            'local_schedulecoursecopy_category',
            'moodlecategoryid IS NOT NULL',
            null,
            'name ASC'
        );
    }

    /**
     * Group every category by its parent id (0 for top level).
     *
     * @param \stdClass[]|null $all Categories to group, or null for every category.
     * @return array<int, \stdClass[]>
     */
    public static function get_children_map(?array $all = null): array {
        $map = [];
        foreach ($all ?? self::get_all() as $category) {
            $map[(int) ($category->parentid ?? 0)][] = $category;
        }
        return $map;
    }

    /**
     * Whether a category has no subcategories, so courses can be scheduled into it.
     *
     * @param int $id The category id.
     * @return bool
     */
    public static function is_leaf(int $id): bool {
        global $DB;
        return !$DB->record_exists('local_schedulecoursecopy_category', ['parentid' => $id]);
    }

    /**
     * The category and its ancestors, top level first.
     *
     * @param int $id The category id.
     * @param \stdClass[]|null $all Every category, indexed by id, or null to load them.
     * @return \stdClass[]
     */
    public static function get_path(int $id, ?array $all = null): array {
        $all = $all ?? self::get_all();
        $path = [];
        $seen = [];
        while ($id && isset($all[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            array_unshift($path, $all[$id]);
            $id = (int) $all[$id]->parentid;
        }
        return $path;
    }

    /**
     * The category's path as "Main Campus / Nursing / Vocational Nurse".
     *
     * @param int $id The category id.
     * @param \stdClass[]|null $all Every category, indexed by id, or null to load them.
     * @return string Plain text; escape it with s() on output.
     */
    public static function get_path_string(int $id, ?array $all = null): string {
        return implode(' / ', array_map(fn($c) => $c->name, self::get_path($id, $all)));
    }

    /**
     * Return this category's id and the id of every descendant category.
     *
     * @param int $id The category id.
     * @return int[]
     */
    public static function get_self_and_descendant_ids(int $id): array {
        $all = self::get_all();
        $ids = [$id];
        $queue = [$id];

        while ($queue) {
            $current = array_pop($queue);
            foreach ($all as $category) {
                if ((int) $category->parentid === $current && !in_array((int) $category->id, $ids, true)) {
                    $ids[] = (int) $category->id;
                    $queue[] = (int) $category->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Build a flat, indent-ready list of categories for a select control.
     *
     * @return array Category id mapped to an indented display name.
     */
    public static function get_options_for_select(): array {
        $all = self::get_all();
        $bychildren = [];
        foreach ($all as $category) {
            $parent = $category->parentid ?? 0;
            $bychildren[$parent][] = $category;
        }

        $options = [];
        self::flatten($bychildren, 0, 0, $options);

        return $options;
    }

    /**
     * Recursively flatten the category tree into an indented option list.
     *
     * @param array $bychildren Categories grouped by parentid (0 for top level).
     * @param int $parentid The parent to expand.
     * @param int $depth Current indentation depth.
     * @param array $options The option list being built, passed by reference.
     * @return void
     */
    protected static function flatten(array $bychildren, int $parentid, int $depth, array &$options): void {
        foreach ($bychildren[$parentid] ?? [] as $category) {
            $options[$category->id] = str_repeat('— ', $depth) . $category->name;
            self::flatten($bychildren, (int) $category->id, $depth + 1, $options);
        }
    }
}
