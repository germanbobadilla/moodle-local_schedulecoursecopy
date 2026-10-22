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
 * The full-width app shell shared by every Schedule Course Copy page: page setup, the
 * plugin's own app bar, the page title row, and the job detail drawer.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Configure $PAGE for a plugin page: block-free layout, no site administration
     * secondary navigation, no Moodle page heading or breadcrumb.
     *
     * @param \moodle_url $url The page URL.
     * @param string $title The browser tab title.
     * @return void
     */
    public static function setup_page(\moodle_url $url, string $title): void {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($url);
        // The admin layout is what brings in the site administration tabs and the
        // admin bookmarks drawer; base has no block regions at all.
        $PAGE->set_pagelayout('base');
        $PAGE->set_secondary_navigation(false);
        $PAGE->set_title($title . ' | ' . get_string('pluginname', 'local_schedulecoursecopy'));
        $PAGE->set_heading(get_string('pluginname', 'local_schedulecoursecopy'));
        $PAGE->add_body_class('local-schedulecoursecopy-app');
        $PAGE->requires->js_call_amd('local_schedulecoursecopy/app', 'init');
    }

    /**
     * Echo the Moodle header, the plugin app bar and the page title row.
     *
     * @param string $active One of 'index', 'categories', 'report' or 'settings'.
     * @param string $title The page title, or '' when the page draws its own.
     * @param string $subtitle Optional muted line under the title.
     * @param string $actions Optional HTML for buttons on the right of the title row.
     * @return void
     */
    public static function header(string $active, string $title, string $subtitle = '', string $actions = ''): void {
        global $OUTPUT, $DB;

        echo $OUTPUT->header();
        echo \html_writer::start_div('scc-app');

        $links = [
            'index' => [
                new \moodle_url('/local/schedulecoursecopy/index.php'),
                get_string('navoverview', 'local_schedulecoursecopy'),
            ],
            'categories' => [
                new \moodle_url('/local/schedulecoursecopy/categories.php'),
                get_string('navcategories', 'local_schedulecoursecopy'),
            ],
            'report' => [
                new \moodle_url('/local/schedulecoursecopy/report.php'),
                get_string('navreport', 'local_schedulecoursecopy'),
            ],
        ];
        if (has_capability('moodle/site:config', \context_system::instance())) {
            $links['settings'] = [
                new \moodle_url('/local/schedulecoursecopy/settings_page.php'),
                get_string('navsettings', 'local_schedulecoursecopy'),
            ];
        }

        $failed = $DB->count_records('local_schedulecoursecopy_job', ['status' => job_manager::STATUS_FAILED]);

        $nav = '';
        foreach ($links as $key => [$url, $label]) {
            $attrs = ['class' => 'scc-navlink' . ($key === $active ? ' is-active' : '')];
            if ($key === $active) {
                $attrs['aria-current'] = 'page';
            }
            if ($key === 'report' && $failed) {
                $label .= \html_writer::span($failed, 'scc-navbadge', [
                    'title' => get_string('navfailedbadge', 'local_schedulecoursecopy', $failed),
                ]);
            }
            $nav .= \html_writer::link($url, $label, $attrs);
        }

        $brand = \html_writer::link(
            new \moodle_url('/local/schedulecoursecopy/index.php'),
            \html_writer::span(ui::icon('fa-solid fa-clone'), 'scc-brandmark')
                . \html_writer::span(get_string('pluginname', 'local_schedulecoursecopy')),
            ['class' => 'scc-brand']
        );

        echo \html_writer::tag(
            'header',
            $brand . \html_writer::tag('nav', $nav, [
                'class' => 'scc-nav',
                'aria-label' => get_string('pluginname', 'local_schedulecoursecopy'),
            ]),
            ['class' => 'scc-appbar']
        );

        echo \html_writer::start_div('scc-content');

        // Pages such as Categories draw their own title inside the layout.
        if ($title === '') {
            return;
        }

        $heading = \html_writer::tag('h1', $title, ['class' => 'scc-title']);
        if ($subtitle !== '') {
            $heading .= \html_writer::tag('p', $subtitle, ['class' => 'scc-subtitle']);
        }
        echo \html_writer::div(
            \html_writer::div($heading, 'scc-pagehead-text')
                . ($actions !== '' ? \html_writer::div($actions, 'scc-pagehead-actions') : ''),
            'scc-pagehead'
        );
    }

    /**
     * Close the app shell and echo the Moodle footer. When the page URL carries a job
     * param, the job detail drawer is rendered over the page.
     *
     * @param \moodle_url|null $pageurl The page URL without the job param, for the drawer to close to.
     * @return void
     */
    public static function footer(?\moodle_url $pageurl = null): void {
        global $OUTPUT, $DB;

        $jobid = optional_param('job', 0, PARAM_INT);
        if ($pageurl && $jobid) {
            $job = $DB->get_record('local_schedulecoursecopy_job', ['id' => $jobid]);
            if ($job) {
                echo ui::job_drawer($job, $pageurl);
            }
        }

        echo \html_writer::end_div();
        echo \html_writer::end_div();
        echo $OUTPUT->footer();
    }
}
