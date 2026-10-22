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

/**
 * Schedule Course Copy dashboard: job counts by status, what needs attention, what is coming
 * up, and a per program breakdown.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\category_manager;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\navigation;
use local_schedulecoursecopy\local\ui;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$url = new moodle_url('/local/schedulecoursecopy/index.php');
navigation::setup_page($url, get_string('navoverview', 'local_schedulecoursecopy'));

$jobs = job_manager::get_all();
$categories = category_manager::get_all();
$children = category_manager::get_children_map($categories);
$statuslabels = ui::status_labels();
$templates = ui::template_shortnames($jobs);

$counts = array_fill_keys(array_keys($statuslabels), 0);
$bycategory = [];
$attention = [];
$upcoming = [];
foreach ($jobs as $job) {
    if (isset($counts[$job->status])) {
        $counts[$job->status]++;
    }
    $bycategory[$job->categoryid][$job->status] = ($bycategory[$job->categoryid][$job->status] ?? 0) + 1;

    if (in_array($job->status, [job_manager::STATUS_READY_TO_REVEAL, job_manager::STATUS_FAILED], true)) {
        $attention[] = $job;
    } else if ($job->status === job_manager::STATUS_SCHEDULED) {
        $upcoming[] = $job;
    }
}
usort($attention, fn($a, $b) => ($b->status === job_manager::STATUS_FAILED) <=> ($a->status === job_manager::STATUS_FAILED)
    ?: $b->timemodified <=> $a->timemodified);
usort($upcoming, fn($a, $b) => $a->scheduledate <=> $b->scheduledate);
$upcoming = array_slice($upcoming, 0, 5);

// "Schedule copies" opens the first leaf category that can hold jobs, with the add panel open.
$addurl = new moodle_url('/local/schedulecoursecopy/categories.php');
foreach (category_manager::get_options_for_select() as $id => $unused) {
    if (empty($children[$id]) && !empty($categories[$id]->moodlecategoryid)) {
        $addurl = new moodle_url('/local/schedulecoursecopy/categories.php', ['id' => $id, 'add' => 1]);
        break;
    }
}

navigation::header(
    'index',
    get_string('navoverview', 'local_schedulecoursecopy'),
    get_string('indexintro', 'local_schedulecoursecopy'),
    html_writer::link(
        $addurl,
        ui::icon('fa-solid fa-plus') . ' ' . get_string('schedulecopies', 'local_schedulecoursecopy'),
        ['class' => 'btn btn-primary']
    )
);

// Status cards: an icon tile, the count, and each status's share of all jobs.
$reporturl = new moodle_url('/local/schedulecoursecopy/report.php');
$alljobs = count($jobs);
$programcount = count($bycategory);
$card = function (string $status, string $label, int $count, moodle_url $link) use ($alljobs, $programcount): string {
    $share = $alljobs ? (int) round($count * 100 / $alljobs) : 0;
    $foot = $status === 'all'
        ? get_string('dashacross', 'local_schedulecoursecopy', $programcount)
        : get_string('dashshare', 'local_schedulecoursecopy', $share);
    return html_writer::link(
        $link,
        html_writer::div(
            html_writer::span($label, 'scc-stat-label')
                . html_writer::span(ui::icon(ui::status_icon($status)), 'scc-stat-icon'),
            'scc-stat-top'
        )
        . html_writer::span($count, 'scc-stat-value')
        . html_writer::span(html_writer::span('', 'scc-stat-fill', ['style' => 'width: ' .
            ($status === 'all' ? ($alljobs ? 100 : 0) : $share) . '%']), 'scc-stat-bar')
        . html_writer::span($foot, 'scc-stat-foot'),
        ['class' => 'scc-stat scc-stat-' . $status . ($count ? '' : ' is-zero')]
    );
};
$stats = $card('all', get_string('dashalljobs', 'local_schedulecoursecopy'), $alljobs, $reporturl);
foreach ($statuslabels as $status => $label) {
    $stats .= $card($status, $label, $counts[$status], new moodle_url($reporturl, ['status' => $status]));
}
echo html_writer::div($stats, 'scc-stats');

echo html_writer::start_div('scc-grid');

// Needs attention.
if (!$attention) {
    $body = html_writer::div(get_string('dashattentionempty', 'local_schedulecoursecopy'), 'scc-empty');
} else {
    $body = '';
    foreach ($attention as $job) {
        $failed = $job->status === job_manager::STATUS_FAILED;
        $jobsurl = ui::job_url($url, (int) $job->id);
        $note = $failed
            ? get_string('dashfailednote', 'local_schedulecoursecopy', (object) [
                'date' => ui::date_short((int) $job->timemodified),
                'path' => category_manager::get_path_string((int) $job->categoryid, $categories),
            ])
            : get_string('dashreadynote', 'local_schedulecoursecopy', $job->visibility === job_manager::VISIBILITY_START
                ? ui::date_long((int) $job->startdate)
                : get_string('dashrevealedbyhand', 'local_schedulecoursecopy'));
        if ($failed) {
            $action = html_writer::link($jobsurl, get_string('dashreview', 'local_schedulecoursecopy'),
                ['class' => 'btn btn-sm btn-outline-primary']);
        } else {
            $action = html_writer::tag(
                'form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'ids[]', 'value' => $job->id])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reveal'])
                    . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl',
                        'value' => $url->out_as_local_url(false)])
                    . html_writer::tag('button', get_string('jobrevealnow', 'local_schedulecoursecopy'),
                        ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']),
                ['method' => 'post', 'action' => (new moodle_url('/local/schedulecoursecopy/job_action.php'))->out(false)]
            );
        }
        $body .= html_writer::div(
            html_writer::span(
                ui::icon($failed ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-eye-slash'),
                'scc-roundicon scc-roundicon-' . $job->status
            )
            . html_writer::div(
                html_writer::link($jobsurl, s($job->fullname), ['class' => 'scc-item-title'])
                    . html_writer::div(s($note), 'scc-item-meta'),
                'scc-item-main'
            )
            . $action,
            'scc-item'
        );
    }
}
echo html_writer::tag(
    'section',
    html_writer::div(
        html_writer::tag('h2', html_writer::span(ui::icon('fa-solid fa-bell scc-ph-attention'), 'scc-panel-icon') . get_string('dashattention', 'local_schedulecoursecopy'),
            ['class' => 'scc-panel-title'])
            . html_writer::span(get_string('dashitems', 'local_schedulecoursecopy', count($attention)), 'scc-muted small'),
        'scc-panel-head'
    ) . $body,
    ['class' => 'scc-panel']
);

// Upcoming copies.
if (!$upcoming) {
    $body = html_writer::div(get_string('dashupcomingempty', 'local_schedulecoursecopy'), 'scc-empty');
} else {
    $body = '';
    foreach ($upcoming as $job) {
        $body .= html_writer::div(
            html_writer::div(
                html_writer::div(userdate($job->scheduledate, '%b'), 'scc-dateblock-mon')
                    . html_writer::div(userdate($job->scheduledate, '%d', 99, true), 'scc-dateblock-day'),
                'scc-dateblock'
            )
            . html_writer::div(
                html_writer::link(ui::job_url($url, (int) $job->id), s($job->fullname), ['class' => 'scc-item-title'])
                    . html_writer::div(
                        s(category_manager::get_path_string((int) $job->categoryid, $categories)) . ' · '
                            . get_string('fromtemplate', 'local_schedulecoursecopy', s($templates[$job->templatecourseid] ?? '—')),
                        'scc-item-meta'
                    ),
                'scc-item-main'
            ),
            'scc-item'
        );
    }
}
echo html_writer::tag(
    'section',
    html_writer::div(
        html_writer::tag('h2', html_writer::span(ui::icon('fa-regular fa-calendar scc-ph-upcoming'), 'scc-panel-icon') . get_string('dashupcoming', 'local_schedulecoursecopy'),
            ['class' => 'scc-panel-title'])
            . html_writer::link(
                new moodle_url($reporturl, ['status' => job_manager::STATUS_SCHEDULED]),
                get_string('dashviewall', 'local_schedulecoursecopy'),
                ['class' => 'scc-panel-link']
            ),
        'scc-panel-head'
    ) . $body,
    ['class' => 'scc-panel']
);

echo html_writer::end_div();

// Programs: every leaf category that has jobs.
$programs = array_filter($categories, fn($c) => empty($children[$c->id]) && !empty($bycategory[$c->id]));
if (!$programs) {
    $body = html_writer::div(get_string('dashprogramsempty', 'local_schedulecoursecopy'), 'scc-empty');
} else {
    $head = html_writer::tag('th', get_string('dashprogram', 'local_schedulecoursecopy'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('dashprogress', 'local_schedulecoursecopy'), ['scope' => 'col', 'class' => 'scc-col-progress']);
    foreach ($statuslabels as $label) {
        $head .= html_writer::tag('th', $label, ['scope' => 'col', 'class' => 'scc-num']);
    }
    $head .= html_writer::tag('th', get_string('dashtotal', 'local_schedulecoursecopy'), ['scope' => 'col', 'class' => 'scc-num']);

    $rows = '';
    foreach ($programs as $program) {
        $path = category_manager::get_path((int) $program->id, $categories);
        array_pop($path);
        $row = html_writer::tag('td', html_writer::link(
            new moodle_url('/local/schedulecoursecopy/categories.php', ['id' => $program->id]),
            format_string($program->name)
        ) . ($path ? html_writer::div(s(implode(' / ', array_map(fn($c) => $c->name, $path))),
            'scc-muted small') : ''));
        $total = array_sum($bycategory[$program->id]);
        $segments = '';
        foreach ($statuslabels as $status => $label) {
            $n = $bycategory[$program->id][$status] ?? 0;
            if ($n) {
                $segments .= html_writer::span('', 'scc-seg scc-seg-' . $status, [
                    'style' => 'width: ' . round($n * 100 / $total, 2) . '%',
                    'title' => $label . ': ' . $n,
                ]);
            }
        }
        $row .= html_writer::tag('td', html_writer::span($segments, 'scc-segbar'), ['class' => 'scc-col-progress']);
        foreach (array_keys($statuslabels) as $status) {
            $n = $bycategory[$program->id][$status] ?? 0;
            $row .= html_writer::tag('td', $n ? html_writer::span($n, 'scc-countpill scc-countpill-' . $status) : 0,
                ['class' => 'scc-num' . ($n ? '' : ' scc-zero')]);
        }
        $row .= html_writer::tag('td', html_writer::tag('strong', $total), ['class' => 'scc-num']);
        $rows .= html_writer::tag('tr', $row);
    }
    $body = html_writer::div(html_writer::tag(
        'table',
        html_writer::tag('thead', html_writer::tag('tr', $head)) . html_writer::tag('tbody', $rows),
        ['class' => 'scc-table table-reboot']
    ), 'scc-tablewrap scc-tablewrap-flush');
}
echo html_writer::tag(
    'section',
    html_writer::div(
        html_writer::tag('h2', html_writer::span(ui::icon('fa-solid fa-chart-column scc-ph-programs'), 'scc-panel-icon') . get_string('dashprograms', 'local_schedulecoursecopy'),
            ['class' => 'scc-panel-title'])
            . html_writer::link(
                new moodle_url('/local/schedulecoursecopy/categories.php'),
                get_string('dashmanagecategories', 'local_schedulecoursecopy'),
                ['class' => 'scc-panel-link']
            ),
        'scc-panel-head'
    ) . $body,
    ['class' => 'scc-panel']
);

navigation::footer($url);
