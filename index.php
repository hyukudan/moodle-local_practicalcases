<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main page for managing practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;

// Parameters.
$categoryid = optional_param('category', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/index.php', ['category' => $categoryid]));
$PAGE->set_title(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'));

// Process actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'deletecat':
            require_capability('local/casospracticos:managecategories', $context);
            if ($confirm) {
                try {
                    category_manager::delete($id);
                    \core\notification::success(get_string('categorydeleted', 'local_casospracticos'));
                } catch (\moodle_exception $e) {
                    \core\notification::error($e->getMessage());
                }
            } else {
                // Show confirmation.
                echo $OUTPUT->header();
                $category = category_manager::get($id);
                echo $OUTPUT->confirm(
                    get_string('deletecategory', 'local_casospracticos') . ': ' . format_string($category->name) . '?',
                    new moodle_url('/local/casospracticos/index.php', [
                        'action' => 'deletecat',
                        'id' => $id,
                        'confirm' => 1,
                        'sesskey' => sesskey(),
                    ]),
                    new moodle_url('/local/casospracticos/index.php')
                );
                echo $OUTPUT->footer();
                exit;
            }
            break;

        case 'deletecase':
            require_capability('local/casospracticos:delete', $context);
            if ($confirm) {
                $case = case_manager::get($id);
                case_manager::delete($id);
                \core\notification::success(get_string('casedeleted', 'local_casospracticos'));
                redirect(new moodle_url('/local/casospracticos/index.php', ['category' => $case->categoryid]));
            } else {
                // Show confirmation.
                echo $OUTPUT->header();
                $case = case_manager::get($id);
                echo $OUTPUT->confirm(
                    get_string('confirmdeletecase', 'local_casospracticos'),
                    new moodle_url('/local/casospracticos/index.php', [
                        'action' => 'deletecase',
                        'id' => $id,
                        'confirm' => 1,
                        'sesskey' => sesskey(),
                    ]),
                    new moodle_url('/local/casospracticos/index.php', ['category' => $case->categoryid])
                );
                echo $OUTPUT->footer();
                exit;
            }
            break;

        case 'publish':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_PUBLISHED);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;

        case 'archive':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_ARCHIVED);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;

        case 'draft':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_DRAFT);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;
    }

    // Redirect to avoid resubmission.
    redirect(new moodle_url('/local/casospracticos/index.php', ['category' => $categoryid]));
}

echo $OUTPUT->header();

// Action buttons.
$buttons = [];
if (has_capability('local/casospracticos:managecategories', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/category_edit.php'),
        get_string('newcategory', 'local_casospracticos'),
        'get'
    );
}
if (has_capability('local/casospracticos:create', $context) && $categoryid > 0) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/case_edit.php', ['category' => $categoryid]),
        get_string('newcase', 'local_casospracticos'),
        'get'
    );
}
if (has_capability('local/casospracticos:export', $context)) {
    $exporturl = new moodle_url('/local/casospracticos/export.php');
    if ($categoryid > 0) {
        $exporturl->param('categoryid', $categoryid);
    }
    $buttons[] = $OUTPUT->single_button($exporturl, get_string('export', 'local_casospracticos'), 'get');
}
if (has_capability('local/casospracticos:import', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/import.php'),
        get_string('import', 'local_casospracticos'),
        'get'
    );
}
if (!empty($buttons)) {
    echo html_writer::div(implode(' ', $buttons), 'mb-3');
}

// Categories sidebar and cases list.
echo html_writer::start_div('row');

// Categories sidebar.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('h4', get_string('categories', 'local_casospracticos'));

$categories = category_manager::get_flat_tree();
if (empty($categories)) {
    echo html_writer::tag('p', get_string('nocategories', 'local_casospracticos'), ['class' => 'text-muted']);
} else {
    echo html_writer::start_tag('ul', ['class' => 'list-group']);

    // "All" option.
    $class = $categoryid == 0 ? 'list-group-item active' : 'list-group-item';
    $url = new moodle_url('/local/casospracticos/index.php');
    echo html_writer::tag('li',
        html_writer::link($url, get_string('all')),
        ['class' => $class]
    );

    foreach ($categories as $category) {
        $class = $category->id == $categoryid ? 'list-group-item active' : 'list-group-item';
        $indent = str_repeat('&nbsp;&nbsp;', $category->depth);
        $url = new moodle_url('/local/casospracticos/index.php', ['category' => $category->id]);

        $casecount = category_manager::count_cases($category->id);
        $badge = html_writer::tag('span', $casecount, ['class' => 'badge bg-secondary float-end']);

        $actions = '';
        if (has_capability('local/casospracticos:managecategories', $context)) {
            $editurl = new moodle_url('/local/casospracticos/category_edit.php', ['id' => $category->id]);
            $deleteurl = new moodle_url('/local/casospracticos/index.php', [
                'action' => 'deletecat',
                'id' => $category->id,
                'sesskey' => sesskey(),
            ]);
            $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
            if ($casecount == 0) {
                $actions .= html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));
            }
        }

        echo html_writer::tag('li',
            $indent . html_writer::link($url, format_string($category->name)) . $badge . $actions,
            ['class' => $class]
        );
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div(); // col-md-3

// Cases list.
echo html_writer::start_div('col-md-9');

if ($categoryid > 0) {
    $category = category_manager::get($categoryid);
    echo html_writer::tag('h4', format_string($category->name));
    $cases = case_manager::get_with_counts($categoryid);
} else {
    echo html_writer::tag('h4', get_string('cases', 'local_casospracticos'));
    $cases = case_manager::get_with_counts();
}

if (empty($cases)) {
    echo html_writer::tag('p', get_string('nocases', 'local_casospracticos'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('status', 'local_casospracticos'),
        get_string('questions', 'local_casospracticos'),
        get_string('modified', 'local_casospracticos'),
        get_string('actions', 'local_casospracticos'),
    ];
    $table->attributes['class'] = 'table table-striped';

    $statuses = local_casospracticos_get_status_options();

    foreach ($cases as $case) {
        $viewurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);
        $editurl = new moodle_url('/local/casospracticos/case_edit.php', ['id' => $case->id]);

        // Status badge.
        $statusclass = [
            'draft' => 'badge bg-secondary',
            'published' => 'badge bg-success',
            'archived' => 'badge bg-warning',
        ];
        $status = html_writer::tag('span', $statuses[$case->status], [
            'class' => $statusclass[$case->status] ?? 'badge bg-secondary',
        ]);

        // Actions.
        $actions = [];
        $actions[] = html_writer::link($viewurl, $OUTPUT->pix_icon('i/preview', get_string('view')));

        if (has_capability('local/casospracticos:edit', $context)) {
            $actions[] = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));

            // Status change actions.
            if ($case->status === 'draft') {
                $publishurl = new moodle_url('/local/casospracticos/index.php', [
                    'action' => 'publish',
                    'id' => $case->id,
                    'category' => $categoryid,
                    'sesskey' => sesskey(),
                ]);
                $actions[] = html_writer::link($publishurl, $OUTPUT->pix_icon('t/approve', get_string('status_published', 'local_casospracticos')));
            }
        }

        if (has_capability('local/casospracticos:delete', $context)) {
            $deleteurl = new moodle_url('/local/casospracticos/index.php', [
                'action' => 'deletecase',
                'id' => $case->id,
                'sesskey' => sesskey(),
            ]);
            $actions[] = html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));
        }

        $table->data[] = [
            html_writer::link($viewurl, format_string($case->name)),
            $status,
            $case->questioncount,
            userdate($case->timemodified, get_string('strftimedatetime', 'langconfig')),
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

echo html_writer::end_div(); // col-md-9
echo html_writer::end_div(); // row

echo $OUTPUT->footer();
