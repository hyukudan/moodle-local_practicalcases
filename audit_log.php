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
 * Audit log viewer for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_casospracticos\audit_logger;

// Parameters.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$objecttype = optional_param('objecttype', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$datefrom = optional_param('datefrom', 0, PARAM_INT);
$dateto = optional_param('dateto', 0, PARAM_INT);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:viewaudit', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/audit_log.php'));
$PAGE->set_title(get_string('auditlog', 'local_casospracticos'));
$PAGE->set_heading(get_string('auditlog', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('auditlog', 'local_casospracticos'));

echo $OUTPUT->header();

// Back button.
echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/index.php'),
        get_string('back'),
        'get'
    ),
    'mb-3'
);

// Filter form.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/casospracticos/audit_log.php'),
    'class' => 'mb-3',
]);

echo html_writer::start_div('row g-2 align-items-end');

// Object type filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('objecttype', 'local_casospracticos'), ['class' => 'form-label', 'for' => 'filter-objecttype']);
echo html_writer::start_tag('select', ['name' => 'objecttype', 'id' => 'filter-objecttype', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', get_string('all'), ['value' => '']);
$objecttypes = [
    'case' => get_string('case', 'local_casospracticos'),
    'question' => get_string('question', 'local_casospracticos'),
    'category' => get_string('category', 'local_casospracticos'),
    'answer' => get_string('answer', 'local_casospracticos'),
];
foreach ($objecttypes as $key => $label) {
    $selected = ($objecttype === $key) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $label, array_merge(['value' => $key], $selected));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Action filter.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', get_string('action', 'local_casospracticos'), ['class' => 'form-label', 'for' => 'filter-action']);
echo html_writer::start_tag('select', ['name' => 'action', 'id' => 'filter-action', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', get_string('all'), ['value' => '']);
$actions = [
    'create' => get_string('action_create', 'local_casospracticos'),
    'update' => get_string('action_update', 'local_casospracticos'),
    'delete' => get_string('action_delete', 'local_casospracticos'),
    'publish' => get_string('action_publish', 'local_casospracticos'),
    'archive' => get_string('action_archive', 'local_casospracticos'),
    'submit_review' => get_string('action_submit_review', 'local_casospracticos'),
    'approve' => get_string('action_approve', 'local_casospracticos'),
    'reject' => get_string('action_reject', 'local_casospracticos'),
];
foreach ($actions as $key => $label) {
    $selected = ($action === $key) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $label, array_merge(['value' => $key], $selected));
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

// User filter.
echo html_writer::start_div('col-md-3');
echo html_writer::tag('label', get_string('user'), ['class' => 'form-label', 'for' => 'filter-userid']);
$users = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname
      FROM {user} u
      JOIN {local_cp_audit_log} l ON l.userid = u.id
  ORDER BY u.lastname, u.firstname
");
echo html_writer::start_tag('select', ['name' => 'userid', 'id' => 'filter-userid', 'class' => 'form-select form-select-sm']);
echo html_writer::tag('option', get_string('all'), ['value' => '0']);
foreach ($users as $user) {
    $selected = ($userid == $user->id) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', fullname($user), array_merge(['value' => $user->id], $selected));
}
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

// Clear button.
echo html_writer::start_div('col-md-2');
echo html_writer::link(
    new moodle_url('/local/casospracticos/audit_log.php'),
    get_string('clear', 'local_casospracticos'),
    ['class' => 'btn btn-secondary btn-sm w-100']
);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');

// Build filters for query.
$filters = [];
if (!empty($objecttype)) {
    $filters['objecttype'] = $objecttype;
}
if (!empty($action)) {
    $filters['action'] = $action;
}
if (!empty($userid)) {
    $filters['userid'] = $userid;
}
if (!empty($datefrom)) {
    $filters['datefrom'] = $datefrom;
}
if (!empty($dateto)) {
    $filters['dateto'] = $dateto;
}

// Get logs.
$result = audit_logger::get_all_logs($filters, $page, $perpage);
$logs = $result['logs'];
$total = $result['total'];

if (empty($logs)) {
    echo html_writer::tag('p', get_string('noauditlogs', 'local_casospracticos'), ['class' => 'text-muted']);
} else {
    // Show count.
    echo html_writer::tag('p', get_string('showingitems', 'local_casospracticos', [
        'from' => ($page * $perpage) + 1,
        'to' => min(($page + 1) * $perpage, $total),
        'total' => $total,
    ]), ['class' => 'text-muted small']);

    $table = new html_table();
    $table->head = [
        get_string('date'),
        get_string('user'),
        get_string('objecttype', 'local_casospracticos'),
        get_string('objectid', 'local_casospracticos'),
        get_string('action', 'local_casospracticos'),
        get_string('changes', 'local_casospracticos'),
        get_string('ipaddress', 'local_casospracticos'),
    ];
    $table->attributes['class'] = 'table table-striped table-sm';

    foreach ($logs as $log) {
        // Format changes.
        $changeshtml = '';
        if (!empty($log->changes)) {
            $changes = json_decode($log->changes, true);
            if (is_array($changes)) {
                $changeshtml = '<small>';
                foreach ($changes as $field => $change) {
                    if (is_array($change)) {
                        $old = $change['old'] ?? '-';
                        $new = $change['new'] ?? '-';
                        $changeshtml .= "<strong>{$field}:</strong> " . s($old) . " &rarr; " . s($new) . "<br>";
                    } else {
                        $changeshtml .= "<strong>{$field}:</strong> " . s($change) . "<br>";
                    }
                }
                $changeshtml .= '</small>';
            }
        }

        // Get action label.
        $actionlabel = $actions[$log->action] ?? $log->action;

        // Get object type label.
        $objecttypelabel = $objecttypes[$log->objecttype] ?? $log->objecttype;

        // Get user name.
        $user = $DB->get_record('user', ['id' => $log->userid]);
        $username = $user ? fullname($user) : get_string('unknownuser', 'local_casospracticos');

        // Build object link.
        $objectlink = $log->objectid;
        if ($log->objecttype === 'case') {
            $objectlink = html_writer::link(
                new moodle_url('/local/casospracticos/case_view.php', ['id' => $log->objectid]),
                $log->objectid
            );
        }

        $table->data[] = [
            userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')),
            $username,
            $objecttypelabel,
            $objectlink,
            $actionlabel,
            $changeshtml,
            $log->ipaddress ?? '-',
        ];
    }

    echo html_writer::table($table);

    // Pagination.
    $totalpages = ceil($total / $perpage);
    if ($totalpages > 1) {
        $baseurl = new moodle_url('/local/casospracticos/audit_log.php', $filters);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
    }
}

echo $OUTPUT->footer();
