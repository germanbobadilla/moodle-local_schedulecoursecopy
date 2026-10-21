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
 * A dashboard style bulk course entry table: add one or more courses to a
 * category as plain rows in a table, with a client side "add row" button.
 *
 * This deliberately does not use moodleform/repeat_elements. That approach
 * was tried first, but Moodle's form renderer has two hard limitations that
 * make a compact multi field repeating row impractical:
 * - HTML_QuickForm_group never passes error state down to its own children
 *   when rendering, so a per field error inside a repeated group is always
 *   silently swallowed; only one combined error per whole row is possible.
 * - A repeated group's rendered width depends on the Bootstrap grid column
 *   it happens to land in, which drifts independently between the column
 *   header strip and each row whenever their content differs even slightly.
 * A plain table has neither problem: every input is a real table cell, and
 * a native date input needs no work-around, since nothing here forces its
 * type back to "text" the way Moodle's own text form element does.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_course_table {
    /** @var string The name of the submit button, used to detect a real submission. */
    const SUBMIT_NAME = 'bulksavecourses';

    /** @var string[] The row fields, in column order. */
    const FIELDS = ['templateshortname', 'fullname', 'shortname', 'scheduledate', 'startdate', 'enddate'];

    /**
     * Whether the table's form was actually submitted (as opposed to a
     * first, blank load of the page).
     *
     * @return bool
     */
    public static function was_submitted(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST' && optional_param(self::SUBMIT_NAME, 0, PARAM_INT) === 1;
    }

    /**
     * Build a set of blank rows for a first, empty load of the page.
     *
     * @param int $count How many blank rows to build.
     * @return \stdClass[] Row objects with every field set to ''.
     */
    public static function default_rows(int $count = 3): array {
        $today = userdate(time(), '%Y-%m-%d');
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = new \stdClass();
            foreach (self::FIELDS as $field) {
                $row->$field = '';
            }
            $row->scheduledate = $today;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Read the submitted rows back from the request.
     *
     * @return \stdClass[] Row objects, one per submitted row, in order.
     */
    public static function parse_submitted_rows(): array {
        $columns = [];
        foreach (self::FIELDS as $field) {
            $columns[$field] = optional_param_array($field, [], PARAM_TEXT);
        }

        $count = 0;
        foreach ($columns as $values) {
            $count = max($count, count($values));
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = new \stdClass();
            foreach (self::FIELDS as $field) {
                $row->$field = trim($columns[$field][$i] ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * A row counts as blank if the three identifying fields are all empty;
     * a blank row is silently skipped rather than treated as an error.
     *
     * @param \stdClass $row The row to check.
     * @return bool
     */
    public static function is_row_blank(\stdClass $row): bool {
        return $row->templateshortname === '' && $row->fullname === '' && $row->shortname === '';
    }

    /**
     * Check whether a date string is either blank or a valid "YYYY-MM-DD" date.
     *
     * @param string $value The raw input value.
     * @return bool
     */
    protected static function is_blank_or_valid_date(string $value): bool {
        if ($value === '') {
            return true;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return false;
        }
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * Turn a "YYYY-MM-DD" date string into a midnight Unix timestamp.
     *
     * job_manager::create()/update() are responsible for pushing an end
     * date on to 23:59; this always returns midnight.
     *
     * @param string $value The raw date input value.
     * @return int The timestamp, or 0 if the value is blank or invalid.
     */
    protected static function date_string_to_timestamp(string $value): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return 0;
        }
        return make_timestamp((int) $matches[1], (int) $matches[2], (int) $matches[3], 0, 0);
    }

    /**
     * Validate every non blank row.
     *
     * @param \stdClass[] $rows The submitted rows.
     * @return array[] A list of error message arrays, one per row (empty for a valid or blank row).
     */
    public static function validate_rows(array $rows): array {
        global $DB;

        $shortnamesseen = [];
        $errorsperrow = [];

        foreach ($rows as $i => $row) {
            $errorsperrow[$i] = [];

            if (self::is_row_blank($row)) {
                continue;
            }

            $errors = &$errorsperrow[$i];

            if ($row->templateshortname === '') {
                $errors[] = get_string('bulkcoltemplate', 'local_schedulecoursecopy') . ': ' . get_string('required');
            } else if (!$DB->record_exists('course', ['shortname' => $row->templateshortname])) {
                $errors[] = get_string('errortemplatenotfound', 'local_schedulecoursecopy');
            }

            if ($row->fullname === '') {
                $errors[] = get_string('bulkcolfullname', 'local_schedulecoursecopy') . ': ' . get_string('required');
            }

            if ($row->shortname === '') {
                $errors[] = get_string('bulkcolshortname', 'local_schedulecoursecopy') . ': ' . get_string('required');
            } else if (
                isset($shortnamesseen[$row->shortname])
                || $DB->record_exists('course', ['shortname' => $row->shortname])
            ) {
                $errors[] = get_string('errorshortnametaken', 'local_schedulecoursecopy');
            }
            if ($row->shortname !== '') {
                $shortnamesseen[$row->shortname] = true;
            }

            $invaliddate = get_string('errorinvaliddate', 'local_schedulecoursecopy');
            if ($row->scheduledate === '' || !self::is_blank_or_valid_date($row->scheduledate)) {
                $errors[] = get_string('bulkcolscheduledate', 'local_schedulecoursecopy') . ': ' . $invaliddate;
            }
            if (!self::is_blank_or_valid_date($row->startdate)) {
                $errors[] = get_string('bulkcolstartdate', 'local_schedulecoursecopy') . ': ' . $invaliddate;
            }
            if (!self::is_blank_or_valid_date($row->enddate)) {
                $errors[] = get_string('bulkcolenddate', 'local_schedulecoursecopy') . ': ' . $invaliddate;
            } else if ($row->startdate !== '' && $row->enddate !== '' && $row->enddate < $row->startdate) {
                $errors[] = get_string('errorenddatebeforestart', 'local_schedulecoursecopy');
            }
        }

        return $errorsperrow;
    }

    /**
     * Convert one validated row into the shape job_manager::create() expects.
     *
     * @param \stdClass $row The row, already validated.
     * @param int $categoryid The Schedule Course Copy category this course belongs to.
     * @return \stdClass
     */
    public static function to_job_data(\stdClass $row, int $categoryid): \stdClass {
        $data = new \stdClass();
        $data->categoryid = $categoryid;
        $data->templateshortname = $row->templateshortname;
        $data->fullname = $row->fullname;
        $data->shortname = $row->shortname;
        $data->idnumber = '';
        $data->scheduledate = self::date_string_to_timestamp($row->scheduledate);
        $data->startdate = self::date_string_to_timestamp($row->startdate);
        $data->enddate = self::date_string_to_timestamp($row->enddate);
        return $data;
    }

    /**
     * Render one table cell: a labelled input for one field of one row.
     *
     * @param string $field The field name (becomes name="{$field}[]").
     * @param string $label The column label, used as the input's aria-label.
     * @param string $value The current value.
     * @param bool $isdate Whether to render a native date input instead of text.
     * @return string
     */
    protected static function render_cell(string $field, string $label, string $value, bool $isdate): string {
        $attributes = [
            'type' => $isdate ? 'date' : 'text',
            'name' => $field . '[]',
            'class' => 'form-control form-control-sm',
            'aria-label' => $label,
            'value' => $value,
        ];
        return \html_writer::empty_tag('input', $attributes);
    }

    /**
     * Render the whole "add courses" panel: an intro, the table of rows, and
     * the add row/save/cancel controls.
     *
     * @param int $categoryid The Schedule Course Copy category id.
     * @param \moodle_url $posturl Where the form posts back to.
     * @param \stdClass[] $rows The rows to pre-fill (defaults or a redisplay after an error).
     * @param array[] $errorsperrow Per row error message arrays, same length as $rows.
     * @return string
     */
    public static function render(int $categoryid, \moodle_url $posturl, array $rows, array $errorsperrow = []): string {
        $datefields = ['scheduledate' => true, 'startdate' => true, 'enddate' => true];

        $columns = [
            'templateshortname' => get_string('bulkcoltemplate', 'local_schedulecoursecopy'),
            'fullname' => get_string('bulkcolfullname', 'local_schedulecoursecopy'),
            'shortname' => get_string('bulkcolshortname', 'local_schedulecoursecopy'),
            'scheduledate' => get_string('bulkcolscheduledate', 'local_schedulecoursecopy'),
            'startdate' => get_string('bulkcolstartdate', 'local_schedulecoursecopy'),
            'enddate' => get_string('bulkcolenddate', 'local_schedulecoursecopy'),
        ];

        $out = \html_writer::start_div('card local-schedulecoursecopy-bulkcard');
        $out .= \html_writer::start_div('card-body');
        $out .= \html_writer::tag('h3', get_string('bulkaddheading', 'local_schedulecoursecopy'), ['class' => 'card-title h5']);
        $out .= \html_writer::div(get_string('bulkintro', 'local_schedulecoursecopy'), 'card-subtitle text-muted mb-3');

        $out .= \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $posturl->out(false),
            'id' => 'local-schedulecoursecopy-bulkform',
        ]);
        $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $categoryid]);

        $out .= \html_writer::start_tag('table', ['class' => 'table table-sm align-middle local-schedulecoursecopy-bulktable']);
        $out .= \html_writer::start_tag('thead');
        $out .= \html_writer::start_tag('tr');
        foreach ($columns as $label) {
            $out .= \html_writer::tag('th', $label, ['scope' => 'col']);
        }
        $out .= \html_writer::tag('th', '', ['scope' => 'col', 'class' => 'local-schedulecoursecopy-col-remove-head']);
        $out .= \html_writer::end_tag('tr');
        $out .= \html_writer::end_tag('thead');

        $out .= \html_writer::start_tag('tbody', ['id' => 'local-schedulecoursecopy-bulkrows']);
        foreach ($rows as $i => $row) {
            $out .= self::render_row($columns, $datefields, $row);
            if (!empty($errorsperrow[$i])) {
                $colspan = count($columns) + 1;
                $out .= \html_writer::tag(
                    'tr',
                    \html_writer::tag(
                        'td',
                        \html_writer::div(implode(' ', $errorsperrow[$i]), 'text-danger small'),
                        ['colspan' => $colspan]
                    ),
                    ['class' => 'local-schedulecoursecopy-rowerror']
                );
            }
        }
        $out .= \html_writer::end_tag('tbody');
        $out .= \html_writer::end_tag('table');

        $out .= \html_writer::tag(
            'template',
            self::render_row($columns, $datefields, self::default_rows(1)[0]),
            ['id' => 'local-schedulecoursecopy-rowtemplate']
        );

        $out .= \html_writer::start_div('d-flex gap-2 mt-3');
        $out .= \html_writer::tag(
            'button',
            get_string('bulkaddrow', 'local_schedulecoursecopy'),
            ['type' => 'button', 'id' => 'local-schedulecoursecopy-addrow', 'class' => 'btn btn-outline-secondary']
        );
        $out .= \html_writer::tag(
            'button',
            get_string('bulksavecourses', 'local_schedulecoursecopy'),
            ['type' => 'submit', 'name' => self::SUBMIT_NAME, 'value' => '1', 'class' => 'btn btn-primary']
        );
        $out .= \html_writer::end_div();

        $out .= \html_writer::end_tag('form');
        $out .= \html_writer::end_div();
        $out .= \html_writer::end_div();

        return $out;
    }

    /**
     * Render one `<tr>` of editable cells for a row.
     *
     * @param array $columns Column keys mapped to their labels.
     * @param array $datefields Column keys that should render as a native date input.
     * @param \stdClass $row The row's current values.
     * @return string
     */
    protected static function render_row(array $columns, array $datefields, \stdClass $row): string {
        $out = \html_writer::start_tag('tr', ['class' => 'local-schedulecoursecopy-bulkrow']);
        foreach ($columns as $field => $label) {
            $isdate = !empty($datefields[$field]);
            $out .= \html_writer::tag('td', self::render_cell($field, $label, $row->$field ?? '', $isdate));
        }
        $out .= \html_writer::tag(
            'td',
            \html_writer::tag('button', '&times;', [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-danger local-schedulecoursecopy-removerow',
                'aria-label' => get_string('bulkremoverow', 'local_schedulecoursecopy'),
            ]),
            ['class' => 'text-end']
        );
        $out .= \html_writer::end_tag('tr');
        return $out;
    }
}
