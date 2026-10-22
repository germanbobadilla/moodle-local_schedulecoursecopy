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

use html_writer;
use moodle_url;

/**
 * The "Schedule copies" panel inside a leaf category: a defaults row, then one table row
 * per course to create. Blank cells inherit the defaults. Rows can also be pasted in from a
 * spreadsheet or imported from a CSV file (both client side, see amd/src/app.js).
 *
 * This deliberately does not use moodleform/repeat_elements: a repeated group never shows a
 * per field error, and its columns drift out of alignment with the header. A plain table
 * has neither problem.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_course_table {
    /** @var string The name of the submit button, used to detect a real submission. */
    const SUBMIT_NAME = 'bulksavecourses';

    /** @var string[] The row fields, in column order. Pasted columns fill in this order. */
    const FIELDS = ['templateshortname', 'fullname', 'shortname', 'scheduledate', 'startdate', 'enddate'];

    /** @var string[] Row fields that can inherit a value from the defaults row. */
    const DEFAULTABLE = ['templateshortname', 'scheduledate', 'startdate', 'enddate'];

    /** @var string[] Row fields that are dates. */
    const DATES = ['scheduledate', 'startdate', 'enddate'];

    /**
     * Whether the panel's form was actually submitted.
     *
     * @return bool
     */
    public static function was_submitted(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST' && optional_param(self::SUBMIT_NAME, 0, PARAM_INT) === 1;
    }

    /**
     * A blank row.
     *
     * @return \stdClass
     */
    public static function blank_row(): \stdClass {
        $row = new \stdClass();
        foreach (self::FIELDS as $field) {
            $row->$field = '';
        }
        return $row;
    }

    /**
     * Blank rows for a first, empty load of the panel.
     *
     * @param int $count How many rows.
     * @return \stdClass[]
     */
    public static function default_rows(int $count = 3): array {
        return array_map(fn() => self::blank_row(), range(1, $count));
    }

    /**
     * The defaults row for a first load: copy today, with the site's default visibility.
     *
     * @return \stdClass
     */
    public static function initial_defaults(): \stdClass {
        return (object) [
            'templateshortname' => '',
            'scheduledate' => userdate(time(), '%Y-%m-%d'),
            'startdate' => '',
            'enddate' => '',
            'visibility' => job_manager::default_visibility(),
        ];
    }

    /**
     * Read the submitted defaults row.
     *
     * @return \stdClass
     */
    public static function parse_submitted_defaults(): \stdClass {
        $defaults = new \stdClass();
        foreach (self::DEFAULTABLE as $field) {
            $value = trim(optional_param('default_' . $field, '', PARAM_TEXT));
            $defaults->$field = in_array($field, self::DATES, true) ? self::normalise_date($value) : $value;
        }
        $defaults->visibility = job_manager::clean_visibility(optional_param('visibility', '', PARAM_ALPHA));
        return $defaults;
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

        $count = max(array_map('count', $columns));
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = new \stdClass();
            foreach (self::FIELDS as $field) {
                $value = trim($columns[$field][$i] ?? '');
                $row->$field = in_array($field, self::DATES, true) ? self::normalise_date($value) : $value;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * A row counts as blank when it has no template, full name or short name of its own;
     * a blank row is skipped rather than treated as an error.
     *
     * @param \stdClass $row The row to check.
     * @return bool
     */
    public static function is_row_blank(\stdClass $row): bool {
        return $row->templateshortname === '' && $row->fullname === '' && $row->shortname === '';
    }

    /**
     * The row with every blank cell filled in from the defaults.
     *
     * @param \stdClass $row The row.
     * @param \stdClass $defaults The defaults row.
     * @return \stdClass
     */
    public static function with_defaults(\stdClass $row, \stdClass $defaults): \stdClass {
        $row = clone $row;
        foreach (self::DEFAULTABLE as $field) {
            if ($row->$field === '') {
                $row->$field = $defaults->$field;
            }
        }
        return $row;
    }

    /**
     * Turn "YYYY-MM-DD" or "DD/MM/YYYY" into "YYYY-MM-DD"; anything else is returned as is.
     *
     * @param string $value The raw value.
     * @return string
     */
    public static function normalise_date(string $value): string {
        if (preg_match('~^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$~', trim($value), $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return trim($value);
    }

    /**
     * Whether a date string is either blank or a valid "YYYY-MM-DD" date.
     *
     * @param string $value The value.
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
     * Turn a "YYYY-MM-DD" date string into a midnight timestamp, or 0.
     *
     * @param string $value The value.
     * @return int
     */
    protected static function date_string_to_timestamp(string $value): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return 0;
        }
        return make_timestamp((int) $matches[1], (int) $matches[2], (int) $matches[3], 0, 0);
    }

    /**
     * Validate every non blank row, checking in this order: full name, short name, short name
     * in use, duplicate in this list, template, copy date, then the other dates.
     *
     * @param \stdClass[] $rows The submitted rows.
     * @param \stdClass $defaults The defaults row.
     * @return array<int, array{field: string, message: string}|null> The first error per row, or null.
     */
    public static function validate_rows(array $rows, \stdClass $defaults): array {
        global $DB;

        $counts = [];
        foreach ($rows as $row) {
            if ($row->shortname !== '') {
                $key = \core_text::strtolower($row->shortname);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $errors = [];
        foreach ($rows as $i => $row) {
            $errors[$i] = null;
            if (self::is_row_blank($row)) {
                continue;
            }
            $full = self::with_defaults($row, $defaults);
            $error = function (string $field, string $key, $a = null) {
                return ['field' => $field, 'message' => get_string($key, 'local_schedulecoursecopy', $a)];
            };

            if ($row->fullname === '') {
                $errors[$i] = $error('fullname', 'errorfullnamerequired');
            } else if ($row->shortname === '') {
                $errors[$i] = $error('shortname', 'errorshortnamerequired');
            } else if (job_manager::shortname_in_use($row->shortname)) {
                $errors[$i] = $error('shortname', 'errorshortnametaken');
            } else if ($counts[\core_text::strtolower($row->shortname)] > 1) {
                $errors[$i] = $error('shortname', 'errorshortnameduplicate');
            } else if ($full->templateshortname === '') {
                $errors[$i] = $error('templateshortname', 'errortemplaterequired');
            } else if (!$DB->record_exists('course', ['shortname' => $full->templateshortname])) {
                $errors[$i] = $error('templateshortname', 'errortemplatenotfound');
            } else if ($full->scheduledate === '') {
                $errors[$i] = $error('scheduledate', 'errorcopydaterequired');
            } else {
                foreach (self::DATES as $field) {
                    if (!self::is_blank_or_valid_date($full->$field)) {
                        $errors[$i] = $error($field, 'errorinvaliddate');
                        break;
                    }
                }
                if (!$errors[$i] && $full->startdate !== '' && $full->enddate !== '' && $full->enddate < $full->startdate) {
                    $errors[$i] = $error('enddate', 'errorenddatebeforestart');
                }
            }
        }
        return $errors;
    }

    /**
     * Convert one validated row into the shape job_manager::create() expects.
     *
     * @param \stdClass $row The row, already validated.
     * @param \stdClass $defaults The defaults row.
     * @param int $categoryid The Schedule Course Copy category this course belongs to.
     * @return \stdClass
     */
    public static function to_job_data(\stdClass $row, \stdClass $defaults, int $categoryid): \stdClass {
        $row = self::with_defaults($row, $defaults);
        $data = new \stdClass();
        $data->categoryid = $categoryid;
        $data->templateshortname = $row->templateshortname;
        $data->fullname = $row->fullname;
        $data->shortname = $row->shortname;
        $data->idnumber = '';
        $data->scheduledate = self::date_string_to_timestamp($row->scheduledate);
        $data->startdate = self::date_string_to_timestamp($row->startdate);
        $data->enddate = self::date_string_to_timestamp($row->enddate);
        $data->visibility = $defaults->visibility;
        return $data;
    }

    /**
     * Course shortnames offered as templates: every course on the site, up to a limit.
     *
     * @return string[]
     */
    protected static function template_suggestions(): array {
        global $DB;
        return array_values($DB->get_records_select_menu(
            'course',
            'id <> :siteid',
            ['siteid' => SITEID],
            'shortname ASC',
            'id, shortname',
            0,
            1000
        ));
    }

    /**
     * Render the whole panel.
     *
     * @param \stdClass $category The leaf category.
     * @param moodle_url $posturl Where the form posts back to.
     * @param \stdClass[] $rows The rows to pre-fill.
     * @param \stdClass $defaults The defaults row.
     * @param array $errors The first error per row, as returned by validate_rows().
     * @param bool $open Whether the panel starts open.
     * @return string HTML.
     */
    public static function render(
        \stdClass $category,
        moodle_url $posturl,
        array $rows,
        \stdClass $defaults,
        array $errors = [],
        bool $open = false
    ): string {
        $str = fn($key, $a = null) => get_string($key, 'local_schedulecoursecopy', $a);

        $out = html_writer::start_tag('section', [
            'class' => 'scc-bulk',
            'id' => 'scc-bulk',
            'data-scc-bulk' => '1',
            'hidden' => $open ? null : 'hidden',
            'aria-labelledby' => 'scc-bulk-title',
        ]);

        // Header with the Table | Upload CSV toggle.
        $modes = '';
        foreach (['grid' => ['bulkmodetable', 'fa-solid fa-table'], 'csv' => ['bulkmodecsv', 'fa-solid fa-file-arrow-up']] as
                $mode => [$label, $icon]) {
            $modes .= html_writer::tag('button', ui::icon($icon) . ' ' . $str($label), [
                'type' => 'button',
                'class' => 'scc-segment' . ($mode === 'grid' ? ' is-active' : ''),
                'data-scc-mode' => $mode,
                'aria-pressed' => $mode === 'grid' ? 'true' : 'false',
            ]);
        }
        $out .= html_writer::div(
            html_writer::div(
                html_writer::tag('h2', $str('bulkheading', format_string($category->name)),
                    ['class' => 'scc-bulk-title', 'id' => 'scc-bulk-title'])
                    . html_writer::tag('p', $str('bulkintro'), ['class' => 'scc-muted small mb-0']),
                ''
            ) . html_writer::div($modes, 'scc-segmented', ['role' => 'group']),
            'scc-bulk-head'
        );

        $out .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $posturl->out(false),
            'id' => 'scc-bulkform',
            'novalidate' => 'novalidate',
        ]);
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'add', 'value' => 1]);

        // Defaults row.
        $field = function (string $label, string $control) {
            return html_writer::tag('label', html_writer::span($label) . $control, ['class' => 'scc-field']);
        };
        $input = function (string $name, string $type, string $value, array $extra = []) {
            return html_writer::empty_tag('input', [
                'type' => $type,
                'name' => $name,
                'value' => $value,
                'class' => 'form-control',
            ] + $extra);
        };
        $visibility = '';
        foreach (job_manager::visibility_options() as $value => $label) {
            $visibility .= html_writer::tag('option', $label, [
                'value' => $value,
                'selected' => $defaults->visibility === $value ? 'selected' : null,
            ]);
        }
        $out .= html_writer::div(
            html_writer::div($str('bulkdefaults'), 'scc-bulk-label')
            . html_writer::div(
                $field($str('bulkdeftemplate'), $input('default_templateshortname', 'text', $defaults->templateshortname, [
                    'list' => 'scc-templates', 'placeholder' => $str('bulkshortnameplaceholder'),
                    'data-scc-default' => 'templateshortname', 'autocomplete' => 'off',
                ]))
                . $field($str('bulkcolscheduledate'), $input('default_scheduledate', 'date', $defaults->scheduledate,
                    ['data-scc-default' => 'scheduledate']))
                . $field($str('bulkdefstart'), $input('default_startdate', 'date', $defaults->startdate,
                    ['data-scc-default' => 'startdate']))
                . $field($str('bulkdefend'), $input('default_enddate', 'date', $defaults->enddate,
                    ['data-scc-default' => 'enddate']))
                . $field($str('jobvisibility'), html_writer::tag('select', $visibility,
                    ['name' => 'visibility', 'class' => 'form-select'])),
                'scc-bulk-defaults'
            ),
            'scc-bulk-defaultsrow'
        );

        $options = '';
        foreach (self::template_suggestions() as $shortname) {
            $options .= html_writer::tag('option', '', ['value' => $shortname]);
        }
        $out .= html_writer::tag('datalist', $options, ['id' => 'scc-templates']);

        // Table mode.
        $columns = [
            'templateshortname' => $str('bulkcoltemplate'),
            'fullname' => $str('bulkcolfullname') . ' *',
            'shortname' => $str('bulkcolshortname') . ' *',
            'scheduledate' => $str('bulkcolscheduledate'),
            'startdate' => $str('bulkcolstart'),
            'enddate' => $str('bulkcolend'),
        ];
        $head = html_writer::tag('th', '#', ['scope' => 'col', 'class' => 'scc-col-n']);
        foreach ($columns as $label) {
            $head .= html_writer::tag('th', $label, ['scope' => 'col']);
        }
        $head .= html_writer::tag('th', html_writer::span($str('bulkremoverow'), 'visually-hidden'),
            ['scope' => 'col', 'class' => 'scc-col-remove']);

        $body = '';
        foreach ($rows as $i => $row) {
            $body .= self::render_row($columns, $row, $i + 1, $errors[$i] ?? null);
        }

        $grid = html_writer::div(
            html_writer::tag(
                'table',
                html_writer::tag('thead', html_writer::tag('tr', $head))
                    . html_writer::tag('tbody', $body, ['data-scc-rows' => '1']),
                ['class' => 'scc-table scc-bulktable table-reboot']
            ),
            'scc-bulk-scroll'
        );
        $grid .= html_writer::div(
            html_writer::tag('button', ui::icon('fa-solid fa-plus') . ' ' . $str('bulkaddrow'),
                ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary', 'data-scc-addrows' => '1'])
            . html_writer::tag('button', $str('bulkaddfive'),
                ['type' => 'button', 'class' => 'btn btn-sm btn-link', 'data-scc-addrows' => '5'])
            . html_writer::span(ui::icon('fa-regular fa-paste') . ' ' . $str('bulkpastetip'), 'scc-muted small'),
            'scc-bulk-tools'
        );
        $out .= html_writer::div($grid, '', ['data-scc-pane' => 'grid']);

        // CSV mode.
        $csv = html_writer::div(
            ui::icon('fa-solid fa-file-csv scc-drop-icon')
            . html_writer::div($str('bulkcsvdrop'), 'fw-semibold')
            . html_writer::div($str('bulkcsvcolumns'), 'scc-muted small')
            . html_writer::div(
                html_writer::tag('label', $str('bulkcsvchoose')
                    . html_writer::empty_tag('input', [
                        'type' => 'file', 'accept' => '.csv,text/csv', 'class' => 'visually-hidden', 'data-scc-csvfile' => '1',
                    ]), ['class' => 'btn btn-primary'])
                . html_writer::link(new moodle_url('/local/schedulecoursecopy/example.csv'), $str('bulkcsvexample'),
                    ['download' => 'courses.csv']),
                'scc-drop-actions'
            ),
            'scc-drop',
            ['data-scc-dropzone' => '1']
        );
        $csv .= html_writer::tag('p', $str('bulkcsvreview'), ['class' => 'scc-muted small mb-0']);
        $out .= html_writer::div($csv, 'scc-bulk-csv', ['data-scc-pane' => 'csv', 'hidden' => 'hidden']);

        // Footer.
        $errorcount = count(array_filter($errors));
        $out .= html_writer::div(
            html_writer::span(
                $errorcount ? $str($errorcount === 1 ? 'bulksummaryerrors1' : 'bulksummaryerrors',
                    (object) ['errors' => $errorcount, 'valid' => count(array_filter($rows, fn($r) => !self::is_row_blank($r))) - $errorcount])
                    : $str('bulksummaryempty'),
                'scc-bulk-summary' . ($errorcount ? ' is-error' : ''),
                ['data-scc-summary' => '1', 'aria-live' => 'polite']
            )
            . html_writer::div(
                html_writer::link(new moodle_url($posturl, ['add' => null]), get_string('cancel'),
                    ['class' => 'btn btn-outline-secondary', 'data-scc-bulkclose' => '1'])
                . html_writer::tag('button', $str('schedulecopies'), [
                    'type' => 'submit',
                    'name' => self::SUBMIT_NAME,
                    'value' => '1',
                    'class' => 'btn btn-primary',
                    'data-scc-submit' => '1',
                ]),
                'd-flex gap-2'
            ),
            'scc-bulk-foot'
        );

        $out .= html_writer::tag('template', self::render_row($columns, self::blank_row(), 0, null),
            ['data-scc-rowtemplate' => '1']);

        $out .= html_writer::end_tag('form');
        $out .= html_writer::end_tag('section');
        return $out;
    }

    /**
     * Render one row of editable cells.
     *
     * @param array $columns Column keys mapped to their labels.
     * @param \stdClass $row The row's own values (blank where it inherits a default).
     * @param int $n The row number shown in the first column.
     * @param array|null $error The row's first error, if any.
     * @return string HTML.
     */
    protected static function render_row(array $columns, \stdClass $row, int $n, ?array $error): string {
        $cells = html_writer::tag('td', $n ?: '', ['class' => 'scc-col-n', 'data-scc-rownum' => '1']);
        foreach ($columns as $field => $label) {
            $isdate = in_array($field, self::DATES, true);
            $attrs = [
                'type' => $isdate ? 'date' : 'text',
                'name' => $field . '[]',
                'class' => 'form-control form-control-sm' . ($error && $error['field'] === $field ? ' is-invalid' : ''),
                'aria-label' => trim($label, ' *'),
                'value' => $row->$field ?? '',
                'data-scc-field' => $field,
            ];
            if ($field === 'templateshortname') {
                $attrs['list'] = 'scc-templates';
                $attrs['autocomplete'] = 'off';
            }
            $cell = html_writer::empty_tag('input', $attrs);
            if ($field === 'shortname') {
                $cell .= html_writer::div($error ? s($error['message']) : '', 'scc-rowerror', ['data-scc-rowerror' => '1']);
            }
            $cells .= html_writer::tag('td', $cell);
        }
        $cells .= html_writer::tag(
            'td',
            html_writer::tag('button', ui::icon('fa-solid fa-xmark'), [
                'type' => 'button',
                'class' => 'scc-iconbtn scc-iconbtn-bordered',
                'data-scc-removerow' => '1',
                'title' => get_string('bulkremoverow', 'local_schedulecoursecopy'),
                'aria-label' => get_string('bulkremoverow', 'local_schedulecoursecopy'),
            ]),
            ['class' => 'scc-col-remove']
        );
        return html_writer::tag('tr', $cells, ['class' => 'scc-bulkrow' . ($error ? ' has-error' : '')]);
    }
}
