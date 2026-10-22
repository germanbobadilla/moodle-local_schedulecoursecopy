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
 * Categories: the campus/program tree on the left, the selected category on the right. A
 * category with subcategories shows them as cards; a leaf category lists its courses and
 * hosts the "Schedule copies" panel.
 *
 * @package    local_schedulecoursecopy
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_schedulecoursecopy\local\bulk_course_table;
use local_schedulecoursecopy\local\category_manager;
use local_schedulecoursecopy\local\job_manager;
use local_schedulecoursecopy\local\job_table;
use local_schedulecoursecopy\local\navigation;
use local_schedulecoursecopy\local\ui;

require(__DIR__ . '/../../config.php');

require_login();
require_capability('local/schedulecoursecopy:manage', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$add = optional_param('add', 0, PARAM_BOOL);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$categories = category_manager::get_all();
$children = category_manager::get_children_map($categories);
if (!isset($categories[$id])) {
    $id = !empty($children[0]) ? (int) reset($children[0])->id : 0;
}

$baseurl = new moodle_url('/local/schedulecoursecopy/categories.php');
$url = new moodle_url($baseurl, $id ? ['id' => $id] : []);
navigation::setup_page($url, get_string('navcategories', 'local_schedulecoursecopy'));

if ($deleteid) {
    require_sesskey();
    $category = $DB->get_record('local_schedulecoursecopy_category', ['id' => $deleteid], '*', MUST_EXIST);
    $parenturl = new moodle_url($baseurl, $category->parentid ? ['id' => $category->parentid] : []);

    if (!$confirm) {
        navigation::header('categories', get_string('navcategories', 'local_schedulecoursecopy'));
        echo $OUTPUT->confirm(
            get_string('categorydeleteconfirm', 'local_schedulecoursecopy', format_string($category->name)),
            new moodle_url($baseurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            new moodle_url($baseurl, ['id' => $deleteid])
        );
        navigation::footer();
        exit;
    }

    if (!category_manager::delete($deleteid)) {
        redirect(
            new moodle_url($baseurl, ['id' => $deleteid]),
            get_string('categorydeletefailed', 'local_schedulecoursecopy'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    redirect($parenturl, get_string('categorydeleted', 'local_schedulecoursecopy'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$category = $id ? $categories[$id] : null;
$isleaf = $category && empty($children[$id]);
$canschedule = $isleaf && !empty($category->moodlecategoryid);

// Handle a bulk submission before any output, so a success can redirect.
$rows = bulk_course_table::default_rows();
$defaults = bulk_course_table::initial_defaults();
$rowerrors = [];
if ($canschedule && bulk_course_table::was_submitted()) {
    require_sesskey();

    $rows = bulk_course_table::parse_submitted_rows();
    $defaults = bulk_course_table::parse_submitted_defaults();
    $rowerrors = bulk_course_table::validate_rows($rows, $defaults);
    $add = true;

    if (!array_filter($rowerrors)) {
        $scheduled = 0;
        foreach ($rows as $i => $row) {
            if (bulk_course_table::is_row_blank($row)) {
                continue;
            }
            try {
                job_manager::create(bulk_course_table::to_job_data($row, $defaults, $id));
                $scheduled++;
            } catch (\Throwable $e) {
                \core\notification::error(get_string('bulkrowerror', 'local_schedulecoursecopy', (object) [
                    'row' => $i + 1,
                    'message' => $e->getMessage(),
                ]));
            }
        }
        if ($scheduled) {
            \core\notification::success(get_string('bulksummary', 'local_schedulecoursecopy', $scheduled));
        } else {
            \core\notification::info(get_string('bulknothingtodo', 'local_schedulecoursecopy'));
        }
        redirect($url);
    }
    // Keep at least the submitted rows, padded so there is always room to type.
    while (count($rows) < 3) {
        $rows[] = bulk_course_table::blank_row();
        $rowerrors[] = null;
    }
}

navigation::header('categories', '');

// Job counts per category, including descendants.
$jobs = job_manager::get_all();
$direct = [];
foreach ($jobs as $job) {
    $direct[$job->categoryid] = ($direct[$job->categoryid] ?? 0) + 1;
}
$recursive = function (int $catid) use (&$recursive, $children, $direct): int {
    $n = $direct[$catid] ?? 0;
    foreach ($children[$catid] ?? [] as $child) {
        $n += $recursive((int) $child->id);
    }
    return $n;
};

echo html_writer::start_div('scc-catlayout');

// Tree.
$tree = function (int $parentid, int $depth) use (&$tree, $children, $id, $baseurl, $recursive): string {
    $items = '';
    foreach ($children[$parentid] ?? [] as $node) {
        $hassubs = !empty($children[$node->id]);
        $selected = (int) $node->id === $id;
        $count = $recursive((int) $node->id);
        $toggle = html_writer::tag('button', ui::icon('fa-solid fa-chevron-down'), [
            'type' => 'button',
            'class' => 'scc-tree-toggle',
            'data-scc-toggle' => '1',
            'aria-expanded' => 'true',
            'aria-label' => get_string('categorytoggle', 'local_schedulecoursecopy', format_string($node->name)),
            'hidden' => $hassubs ? null : 'hidden',
        ]);
        $link = html_writer::link(
            new moodle_url($baseurl, ['id' => $node->id]),
            ui::icon($hassubs ? 'fa-regular fa-folder-open scc-tree-icon' : 'fa-solid fa-graduation-cap scc-tree-icon')
                . html_writer::span(format_string($node->name), 'scc-tree-name')
                . html_writer::span($count ?: '', 'scc-tree-count'),
            ['class' => 'scc-tree-link', 'aria-current' => $selected ? 'page' : null]
        );
        $items .= html_writer::tag(
            'li',
            html_writer::div($toggle . $link, 'scc-tree-row' . ($selected ? ' is-selected' : ''),
                ['style' => '--scc-depth: ' . $depth])
                . ($hassubs ? html_writer::tag('ul', $tree((int) $node->id, $depth + 1), ['role' => 'group']) : ''),
            ['data-scc-node' => '1', 'data-name' => core_text::strtolower(format_string($node->name))]
        );
    }
    return $items;
};

$addroot = html_writer::link(
    new moodle_url('/local/schedulecoursecopy/category_edit.php'),
    ui::icon('fa-solid fa-plus') . ' ' . get_string('add'),
    ['class' => 'btn btn-sm btn-outline-secondary', 'data-scc-catmodal' => 'add', 'data-parentid' => 0,
        'title' => get_string('categoryaddtop', 'local_schedulecoursecopy')]
);
echo html_writer::tag(
    'aside',
    html_writer::div(html_writer::span(get_string('navcategories', 'local_schedulecoursecopy'), 'fw-bold') . $addroot,
        'scc-tree-head')
    . html_writer::div(html_writer::empty_tag('input', [
        'type' => 'search',
        'class' => 'form-control form-control-sm',
        'placeholder' => get_string('categoryfilter', 'local_schedulecoursecopy'),
        'aria-label' => get_string('categoryfilter', 'local_schedulecoursecopy'),
        'data-scc-treefilter' => '1',
    ]), 'scc-tree-filter')
    . ($categories
        ? html_writer::tag('ul', $tree(0, 0), ['class' => 'scc-tree', 'role' => 'tree'])
        : html_writer::div(get_string('categorynoneyet', 'local_schedulecoursecopy'), 'scc-empty')),
    ['class' => 'scc-treepanel']
);

echo html_writer::start_div('scc-catdetail');

if (!$category) {
    echo html_writer::div(get_string('categorynoneyet', 'local_schedulecoursecopy'), 'scc-panel scc-empty');
} else {
    // Breadcrumb, title, meta and actions.
    $path = category_manager::get_path($id, $categories);
    array_pop($path);
    $crumbs = '';
    foreach ($path as $ancestor) {
        $crumbs .= html_writer::tag('li', html_writer::link(new moodle_url($baseurl, ['id' => $ancestor->id]),
            format_string($ancestor->name)));
    }

    $moodlecategory = $category->moodlecategoryid
        ? \core_course_category::get($category->moodlecategoryid, IGNORE_MISSING, true)
        : null;
    $meta = html_writer::span(ui::icon('fa-solid fa-folder-tree') . ' '
        . get_string('categorymoodlecategory', 'local_schedulecoursecopy') . ': '
        . html_writer::tag('strong', $moodlecategory ? s($moodlecategory->get_nested_name(false)) : '—'))
        . html_writer::span(ui::icon('fa-solid fa-hashtag') . ' '
            . get_string('categoryidnumber', 'local_schedulecoursecopy') . ': '
            . ($category->idnumber !== null && $category->idnumber !== '' ? s($category->idnumber) : '—'));

    $deletable = $isleaf && !$recursive($id);
    $actions = html_writer::link(
        new moodle_url('/local/schedulecoursecopy/category_edit.php', ['parentid' => $id]),
        ui::icon('fa-solid fa-folder-plus') . ' ' . get_string('categoryaddsub', 'local_schedulecoursecopy'),
        ['class' => 'btn btn-outline-secondary', 'data-scc-catmodal' => 'add', 'data-parentid' => $id]
    ) . html_writer::link(
        new moodle_url('/local/schedulecoursecopy/category_edit.php', ['id' => $id]),
        ui::icon('fa-solid fa-pen') . ' ' . get_string('edit'),
        ['class' => 'btn btn-outline-secondary', 'data-scc-catmodal' => 'edit', 'data-id' => $id]
    );
    if ($deletable) {
        $actions .= html_writer::link(
            new moodle_url($baseurl, ['delete' => $id, 'sesskey' => sesskey()]),
            ui::icon('fa-solid fa-trash'),
            ['class' => 'btn btn-outline-danger', 'title' => get_string('categorydelete', 'local_schedulecoursecopy'),
                'aria-label' => get_string('categorydelete', 'local_schedulecoursecopy')]
        );
    } else {
        $actions .= html_writer::tag('span', html_writer::tag('button', ui::icon('fa-solid fa-trash'), [
            'type' => 'button', 'class' => 'btn btn-outline-danger', 'disabled' => 'disabled',
            'aria-label' => get_string('categorydelete', 'local_schedulecoursecopy'),
        ]), ['class' => 'd-inline-block', 'title' => get_string('categorydeleteonlyempty', 'local_schedulecoursecopy')]);
    }

    echo html_writer::div(
        ($crumbs ? html_writer::tag('ol', $crumbs, ['class' => 'scc-crumbs']) : '')
        . html_writer::div(
            html_writer::div(
                html_writer::tag('h1', format_string($category->name), ['class' => 'scc-title'])
                    . html_writer::div($meta, 'scc-catmeta'),
                ''
            ) . html_writer::div($actions, 'scc-pagehead-actions'),
            'scc-cathead'
        ),
        ''
    );

    if (!$isleaf) {
        // Subcategory cards.
        $cards = '';
        foreach ($children[$id] as $child) {
            $subs = count($children[$child->id] ?? []);
            $n = $recursive((int) $child->id);
            $meta = ($subs ? get_string('categorysubcount', 'local_schedulecoursecopy', $subs) . ' · ' : '')
                . get_string('categorycoursecount', 'local_schedulecoursecopy', $n);
            $cards .= html_writer::link(
                new moodle_url($baseurl, ['id' => $child->id]),
                html_writer::span(ui::icon($subs ? 'fa-regular fa-folder' : 'fa-solid fa-graduation-cap') . ' '
                    . format_string($child->name), 'scc-card-title')
                    . html_writer::span($meta, 'scc-muted small'),
                ['class' => 'scc-card']
            );
        }
        echo html_writer::tag(
            'section',
            html_writer::tag('h2', get_string('categorysubcategories', 'local_schedulecoursecopy'), ['class' => 'scc-section-title'])
                . html_writer::div($cards, 'scc-cards')
                . html_writer::tag('p', ui::icon('fa-solid fa-circle-info') . ' '
                    . get_string('categoryleafonly', 'local_schedulecoursecopy'), ['class' => 'scc-muted small mt-3 mb-0']),
            []
        );
    } else {
        if (!$canschedule) {
            echo $OUTPUT->notification(get_string('categorymappingneeded', 'local_schedulecoursecopy'), 'warning');
        } else {
            echo bulk_course_table::render($category, $url, $rows, $defaults, $rowerrors, (bool) $add);
        }

        $catjobs = array_filter($jobs, fn($j) => (int) $j->categoryid === $id);
        $openbutton = $canschedule ? html_writer::link(
            new moodle_url($url, ['add' => 1]),
            ui::icon('fa-solid fa-plus') . ' ' . get_string('schedulecopies', 'local_schedulecoursecopy'),
            ['class' => 'btn btn-primary', 'data-scc-bulkopen' => '1', 'hidden' => $add ? 'hidden' : null]
        ) : '';
        echo html_writer::start_tag('section');
        echo html_writer::div(
            html_writer::tag('h2', get_string('bulkexistingcourses', 'local_schedulecoursecopy')
                . ' ' . html_writer::span('(' . count($catjobs) . ')', 'scc-muted fw-normal'), ['class' => 'scc-section-title'])
                . $openbutton,
            'scc-section-head'
        );
        echo $catjobs
            ? job_table::render_category($catjobs, $url)
            : html_writer::div(get_string('categorynocourses', 'local_schedulecoursecopy'), 'scc-panel scc-empty');
        echo html_writer::end_tag('section');
    }
}

echo html_writer::end_div();
echo html_writer::end_div();

navigation::footer($url);
