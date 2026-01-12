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
use local_casospracticos\filter_manager;

// Parameters.
$categoryid = optional_param('category', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

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

// Load JavaScript for bulk actions.
if (has_capability('local/casospracticos:bulk', $context)) {
    $PAGE->requires->js_call_amd('local_casospracticos/bulk_actions', 'init');
}

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
        case 'archive':
        case 'draft':
            require_capability('local/casospracticos:edit', $context);

            // Verificar propiedad del caso.
            $case = case_manager::get($id);
            if (!$case) {
                throw new moodle_exception('error:casenotfound', 'local_casospracticos');
            }

            // Solo el owner o usuarios con editall pueden cambiar el estado.
            if ($case->createdby != $USER->id && !has_capability('local/casospracticos:editall', $context)) {
                throw new moodle_exception('error:nopermission', 'local_casospracticos');
            }

            // Determinar el nuevo estado según la acción.
            $newstatus = match($action) {
                'publish' => case_manager::STATUS_PUBLISHED,
                'archive' => case_manager::STATUS_ARCHIVED,
                'draft' => case_manager::STATUS_DRAFT,
                default => throw new coding_exception('Invalid action: ' . $action)
            };

            case_manager::set_status($id, $newstatus);
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
// My attempts - for all users to view their practice history.
$buttons[] = $OUTPUT->single_button(
    new moodle_url('/local/casospracticos/my_attempts.php'),
    get_string('myattempts', 'local_casospracticos'),
    'get',
    ['class' => 'btn-outline-info']
);
// Achievements button - if gamification is enabled.
if (\local_casospracticos\achievements_manager::is_enabled()) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/achievements.php'),
        get_string('achievements', 'local_casospracticos'),
        'get',
        ['class' => 'btn-outline-warning']
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
// Links to audit log and review dashboard for managers.
if (has_capability('local/casospracticos:viewaudit', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/audit_log.php'),
        get_string('auditlog', 'local_casospracticos'),
        'get'
    );
}
if (has_capability('local/casospracticos:review', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/review_dashboard.php'),
        get_string('reviewdashboard', 'local_casospracticos'),
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

// Optimized: Use single query with counts instead of N+1 queries.
$categories = category_manager::get_flat_tree_with_counts();
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
        $indent = str_repeat('&nbsp;&nbsp;', (int)$category->depth);
        $url = new moodle_url('/local/casospracticos/index.php', ['category' => $category->id]);

        $casecount = $category->casecount ?? 0;
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
}

// Get filters from request.
$filters = filter_manager::parse_filters_from_request();
$sortparams = filter_manager::parse_sort_from_request();

// Add category to filters if set.
if ($categoryid > 0) {
    $filters['categoryid'] = $categoryid;
}

// Display filter form.
$filteroptions = filter_manager::get_filter_options();
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/casospracticos/index.php'),
    'class' => 'mb-3',
]);
if ($categoryid > 0) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'category', 'value' => $categoryid]);
}

echo html_writer::start_div('row g-2 align-items-end');

// Status filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('status', 'local_casospracticos'), ['class' => 'form-label', 'for' => 'filter-status']);
echo html_writer::start_tag('select', ['name' => 'status', 'id' => 'filter-status', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', get_string('all'), ['value' => '']);
foreach ($filteroptions['statuses'] as $s) {
    $selected = (isset($filters['status']) && $filters['status'] === $s['value']) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $s['label'], array_merge(['value' => $s['value']], $selected));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Difficulty filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('difficulty', 'local_casospracticos'), ['class' => 'form-label', 'for' => 'filter-difficulty']);
echo html_writer::start_tag('select', ['name' => 'difficulty', 'id' => 'filter-difficulty', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', get_string('all'), ['value' => '']);
foreach ($filteroptions['difficulties'] as $d) {
    $selected = (isset($filters['difficulty']) && $filters['difficulty'] == $d['value']) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $d['label'], array_merge(['value' => $d['value']], $selected));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Search filter.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('search'), ['class' => 'form-label', 'for' => 'filter-search']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'filter-search',
    'class' => 'form-control form-control-sm',
    'value' => $filters['search'] ?? '',
    'placeholder' => get_string('searchcases', 'local_casospracticos'),
]);
echo html_writer::end_div();

// Sort.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('sort'), ['class' => 'form-label', 'for' => 'filter-sort']);
echo html_writer::start_tag('select', ['name' => 'sort', 'id' => 'filter-sort', 'class' => 'form-select form-select-sm']);
$sortoptions = [
    'timemodified' => get_string('modified', 'local_casospracticos'),
    'timecreated' => get_string('created', 'local_casospracticos'),
    'name' => get_string('name'),
    'difficulty' => get_string('difficulty', 'local_casospracticos'),
];
foreach ($sortoptions as $key => $label) {
    $selected = ($sortparams['sort'] === $key) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $label, array_merge(['value' => $key], $selected));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Order.
echo html_writer::start_div('col-md-1');
echo html_writer::tag('label', get_string('order'), ['class' => 'form-label', 'for' => 'filter-order']);
echo html_writer::start_tag('select', ['name' => 'order', 'id' => 'filter-order', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', 'DESC', ($sortparams['order'] === 'DESC') ? ['value' => 'DESC', 'selected' => 'selected'] : ['value' => 'DESC']);
echo html_writer::tag('option', 'ASC', ($sortparams['order'] === 'ASC') ? ['value' => 'ASC', 'selected' => 'selected'] : ['value' => 'ASC']);
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Filter button.
echo html_writer::start_div('col-md-2');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('filter', 'local_casospracticos'),
    'class' => 'btn btn-primary btn-sm w-100',
]);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');

// Get filtered cases.
$result = filter_manager::get_filtered_cases($filters, $sortparams['sort'], $sortparams['order'], $page, $perpage);
$cases = $result['cases'];
$total = $result['total'];
$totalpages = $result['pages'];

// Bulk actions toolbar (hidden by default, shown when items selected).
if (has_capability('local/casospracticos:bulk', $context)) {
    echo html_writer::start_div('bulk-actions-container d-none alert alert-info py-2 mb-2');
    echo html_writer::tag('span', get_string('selected', 'local_casospracticos') . ': ', ['class' => 'me-2']);
    echo html_writer::tag('strong', '0', ['class' => 'selected-count me-3']);
    echo html_writer::tag('button', get_string('delete'), [
        'type' => 'button',
        'class' => 'btn btn-danger btn-sm me-1',
        'data-bulk-action' => 'delete',
    ]);
    echo html_writer::tag('button', get_string('publish', 'local_casospracticos'), [
        'type' => 'button',
        'class' => 'btn btn-success btn-sm me-1',
        'data-bulk-action' => 'publish',
    ]);
    echo html_writer::tag('button', get_string('archive', 'local_casospracticos'), [
        'type' => 'button',
        'class' => 'btn btn-warning btn-sm me-1',
        'data-bulk-action' => 'archive',
    ]);
    echo html_writer::tag('button', get_string('move', 'local_casospracticos'), [
        'type' => 'button',
        'class' => 'btn btn-secondary btn-sm me-1',
        'data-bulk-action' => 'move',
    ]);
    echo html_writer::tag('button', 'PDF', [
        'type' => 'button',
        'class' => 'btn btn-outline-primary btn-sm me-1',
        'data-bulk-action' => 'export-pdf',
    ]);
    echo html_writer::tag('button', 'CSV', [
        'type' => 'button',
        'class' => 'btn btn-outline-primary btn-sm',
        'data-bulk-action' => 'export-csv',
    ]);
    echo html_writer::end_div();
}

if (empty($cases)) {
    echo html_writer::tag('p', get_string('nocases', 'local_casospracticos'), ['class' => 'text-muted']);
} else {
    // Show count and pagination info.
    echo html_writer::tag('p', get_string('showingcases', 'local_casospracticos', [
        'from' => ($page * $perpage) + 1,
        'to' => min(($page + 1) * $perpage, $total),
        'total' => $total,
    ]), ['class' => 'text-muted small']);

    $table = new html_table();
    $tableheaders = [];

    // Add select all checkbox for bulk actions.
    if (has_capability('local/casospracticos:bulk', $context)) {
        $tableheaders[] = html_writer::checkbox('selectall', '1', false, '', ['id' => 'select-all-cases', 'class' => 'form-check-input']);
    }

    $tableheaders[] = get_string('name');
    $tableheaders[] = get_string('status', 'local_casospracticos');
    $tableheaders[] = get_string('difficulty', 'local_casospracticos');
    $tableheaders[] = get_string('questions', 'local_casospracticos');
    $tableheaders[] = get_string('modified', 'local_casospracticos');
    $tableheaders[] = get_string('actions', 'local_casospracticos');

    $table->head = $tableheaders;
    $table->attributes['class'] = 'table table-striped';

    $statuses = local_casospracticos_get_status_options();

    foreach ($cases as $case) {
        $viewurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);
        $editurl = new moodle_url('/local/casospracticos/case_edit.php', ['id' => $case->id]);

        $row = [];

        // Checkbox for bulk select.
        if (has_capability('local/casospracticos:bulk', $context)) {
            $row[] = html_writer::checkbox('case_' . $case->id, $case->id, false, '', [
                'class' => 'case-select-checkbox form-check-input',
            ]);
        }

        // Name.
        $row[] = html_writer::link($viewurl, format_string($case->name));

        // Status badge.
        $statusclass = [
            'draft' => 'badge bg-secondary',
            'pending_review' => 'badge bg-info',
            'in_review' => 'badge bg-info',
            'approved' => 'badge bg-primary',
            'published' => 'badge bg-success',
            'archived' => 'badge bg-warning text-dark',
        ];
        $statuslabel = $statuses[$case->status] ?? $case->status;
        $row[] = html_writer::tag('span', $statuslabel, [
            'class' => $statusclass[$case->status] ?? 'badge bg-secondary',
        ]);

        // Difficulty.
        $difficultyhtml = '';
        if ($case->difficulty > 0) {
            for ($i = 1; $i <= 5; $i++) {
                $difficultyhtml .= ($i <= $case->difficulty) ? '★' : '☆';
            }
        } else {
            $difficultyhtml = '-';
        }
        $row[] = $difficultyhtml;

        // Question count.
        $row[] = $case->questioncount;

        // Modified date.
        $row[] = userdate($case->timemodified, get_string('strftimedatetime', 'langconfig'));

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
                $actions[] = html_writer::link($publishurl, $OUTPUT->pix_icon('t/approve', get_string('publish', 'local_casospracticos')));
            } else if ($case->status === 'published') {
                $archiveurl = new moodle_url('/local/casospracticos/index.php', [
                    'action' => 'archive',
                    'id' => $case->id,
                    'category' => $categoryid,
                    'sesskey' => sesskey(),
                ]);
                $actions[] = html_writer::link($archiveurl, $OUTPUT->pix_icon('t/hide', get_string('archive', 'local_casospracticos')));
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

        $row[] = implode(' ', $actions);

        $table->data[] = $row;
    }

    echo html_writer::table($table);

    // Pagination.
    if ($totalpages > 1) {
        $baseurl = new moodle_url('/local/casospracticos/index.php', array_merge([
            'category' => $categoryid,
            'sort' => $sortparams['sort'],
            'order' => $sortparams['order'],
        ], $filters));
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
    }
}

echo html_writer::end_div(); // col-md-9
echo html_writer::end_div(); // row

echo $OUTPUT->footer();
