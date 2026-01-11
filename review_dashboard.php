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
 * Review dashboard for practical cases workflow.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_casospracticos\workflow_manager;
use local_casospracticos\case_manager;

// Parameters.
$action = optional_param('action', '', PARAM_ALPHA);
$reviewid = optional_param('reviewid', 0, PARAM_INT);
$caseid = optional_param('caseid', 0, PARAM_INT);
$decision = optional_param('decision', '', PARAM_ALPHA);
$comments = optional_param('comments', '', PARAM_TEXT);
$tab = optional_param('tab', 'pending', PARAM_ALPHA);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:review', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/review_dashboard.php'));
$PAGE->set_title(get_string('reviewdashboard', 'local_casospracticos'));
$PAGE->set_heading(get_string('reviewdashboard', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('reviewdashboard', 'local_casospracticos'));

// Process actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'approve':
            if ($reviewid) {
                workflow_manager::submit_review($reviewid, 'approved', $comments);
                \core\notification::success(get_string('reviewsubmitted', 'local_casospracticos'));
            }
            break;

        case 'reject':
            if ($reviewid) {
                workflow_manager::submit_review($reviewid, 'rejected', $comments);
                \core\notification::success(get_string('reviewsubmitted', 'local_casospracticos'));
            }
            break;

        case 'revision':
            if ($reviewid) {
                workflow_manager::submit_review($reviewid, 'revision_requested', $comments);
                \core\notification::success(get_string('reviewsubmitted', 'local_casospracticos'));
            }
            break;

        case 'assign':
            if ($caseid) {
                workflow_manager::assign_reviewer($caseid, $USER->id);
                \core\notification::success(get_string('reviewerassigned', 'local_casospracticos'));
            }
            break;

        case 'publish':
            if ($caseid) {
                workflow_manager::publish($caseid);
                \core\notification::success(get_string('casepublished', 'local_casospracticos'));
            }
            break;
    }

    redirect(new moodle_url('/local/casospracticos/review_dashboard.php', ['tab' => $tab]));
}

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

// Tabs.
$tabs = [
    'pending' => get_string('pendingreview', 'local_casospracticos'),
    'myreviews' => get_string('myreviews', 'local_casospracticos'),
    'approved' => get_string('approvedcases', 'local_casospracticos'),
    'all' => get_string('allreviews', 'local_casospracticos'),
];

echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-3']);
foreach ($tabs as $key => $label) {
    $class = ($tab === $key) ? 'nav-link active' : 'nav-link';
    $url = new moodle_url('/local/casospracticos/review_dashboard.php', ['tab' => $key]);
    echo html_writer::tag('li',
        html_writer::link($url, $label, ['class' => $class]),
        ['class' => 'nav-item']
    );
}
echo html_writer::end_tag('ul');

// Get data based on tab.
$reviews = [];
$caseswaitingassignment = [];

switch ($tab) {
    case 'pending':
        // Cases waiting for a reviewer to be assigned.
        $caseswaitingassignment = $DB->get_records_sql("
            SELECT c.*, cat.name AS categoryname,
                   (SELECT COUNT(*) FROM {local_cp_questions} q WHERE q.caseid = c.id) AS questioncount
              FROM {local_cp_cases} c
              JOIN {local_cp_categories} cat ON c.categoryid = cat.id
             WHERE c.status = 'pending_review'
               AND NOT EXISTS (
                   SELECT 1 FROM {local_cp_reviews} r
                   WHERE r.caseid = c.id AND r.status = 'pending'
               )
          ORDER BY c.timemodified DESC
        ");

        // My pending reviews.
        $reviews = workflow_manager::get_pending_reviews($USER->id);
        break;

    case 'myreviews':
        // All my reviews (pending and completed).
        $reviews = $DB->get_records_sql("
            SELECT r.*, c.name AS casename, c.status AS casestatus,
                   (SELECT COUNT(*) FROM {local_cp_questions} q WHERE q.caseid = c.id) AS questioncount
              FROM {local_cp_reviews} r
              JOIN {local_cp_cases} c ON r.caseid = c.id
             WHERE r.reviewerid = :userid
          ORDER BY r.timemodified DESC
        ", ['userid' => $USER->id]);
        break;

    case 'approved':
        // Approved cases waiting to be published.
        $caseswaitingassignment = $DB->get_records_sql("
            SELECT c.*, cat.name AS categoryname,
                   (SELECT COUNT(*) FROM {local_cp_questions} q WHERE q.caseid = c.id) AS questioncount
              FROM {local_cp_cases} c
              JOIN {local_cp_categories} cat ON c.categoryid = cat.id
             WHERE c.status = 'approved'
          ORDER BY c.timemodified DESC
        ");
        break;

    case 'all':
        // All reviews.
        $reviews = $DB->get_records_sql("
            SELECT r.*, c.name AS casename, c.status AS casestatus,
                   u.firstname, u.lastname,
                   (SELECT COUNT(*) FROM {local_cp_questions} q WHERE q.caseid = c.id) AS questioncount
              FROM {local_cp_reviews} r
              JOIN {local_cp_cases} c ON r.caseid = c.id
              JOIN {user} u ON r.reviewerid = u.id
          ORDER BY r.timemodified DESC
             LIMIT 100
        ");
        break;
}

// Cases waiting for assignment.
if (!empty($caseswaitingassignment)) {
    echo html_writer::tag('h5', get_string('caseswaitingassignment', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('category', 'local_casospracticos'),
        get_string('questions', 'local_casospracticos'),
        get_string('modified', 'local_casospracticos'),
        get_string('actions', 'local_casospracticos'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($caseswaitingassignment as $case) {
        $viewurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);

        $actions = [];
        $actions[] = html_writer::link($viewurl, $OUTPUT->pix_icon('i/preview', get_string('view')));

        if ($tab === 'pending') {
            $assignurl = new moodle_url('/local/casospracticos/review_dashboard.php', [
                'action' => 'assign',
                'caseid' => $case->id,
                'tab' => $tab,
                'sesskey' => sesskey(),
            ]);
            $actions[] = html_writer::link($assignurl, get_string('assigntome', 'local_casospracticos'),
                ['class' => 'btn btn-sm btn-primary']);
        } else if ($tab === 'approved') {
            $publishurl = new moodle_url('/local/casospracticos/review_dashboard.php', [
                'action' => 'publish',
                'caseid' => $case->id,
                'tab' => $tab,
                'sesskey' => sesskey(),
            ]);
            $actions[] = html_writer::link($publishurl, get_string('publish', 'local_casospracticos'),
                ['class' => 'btn btn-sm btn-success']);
        }

        $table->data[] = [
            html_writer::link($viewurl, format_string($case->name)),
            format_string($case->categoryname),
            $case->questioncount,
            userdate($case->timemodified, get_string('strftimedatetime', 'langconfig')),
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

// Reviews list.
if (!empty($reviews)) {
    $title = ($tab === 'pending') ? get_string('mypendingreview', 'local_casospracticos') : get_string('reviews', 'local_casospracticos');
    echo html_writer::tag('h5', $title, ['class' => 'mt-4 mb-3']);

    $table = new html_table();
    $tableheaders = [
        get_string('case', 'local_casospracticos'),
        get_string('status', 'local_casospracticos'),
        get_string('questions', 'local_casospracticos'),
        get_string('date'),
    ];

    if ($tab === 'all') {
        array_splice($tableheaders, 1, 0, get_string('reviewer', 'local_casospracticos'));
    }

    $tableheaders[] = get_string('actions', 'local_casospracticos');
    $table->head = $tableheaders;
    $table->attributes['class'] = 'table table-striped';

    $reviewstatuses = [
        'pending' => ['label' => get_string('reviewstatus_pending', 'local_casospracticos'), 'class' => 'badge bg-warning text-dark'],
        'approved' => ['label' => get_string('reviewstatus_approved', 'local_casospracticos'), 'class' => 'badge bg-success'],
        'rejected' => ['label' => get_string('reviewstatus_rejected', 'local_casospracticos'), 'class' => 'badge bg-danger'],
        'revision_requested' => ['label' => get_string('reviewstatus_revision', 'local_casospracticos'), 'class' => 'badge bg-info'],
    ];

    foreach ($reviews as $review) {
        $viewurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $review->caseid]);

        $row = [];
        $row[] = html_writer::link($viewurl, format_string($review->casename));

        if ($tab === 'all') {
            $row[] = fullname($review);
        }

        $statusinfo = $reviewstatuses[$review->status] ?? ['label' => $review->status, 'class' => 'badge bg-secondary'];
        $row[] = html_writer::tag('span', $statusinfo['label'], ['class' => $statusinfo['class']]);
        $row[] = $review->questioncount ?? 0;
        $row[] = userdate($review->timemodified, get_string('strftimedatetime', 'langconfig'));

        // Actions.
        $actions = [];
        $actions[] = html_writer::link($viewurl, $OUTPUT->pix_icon('i/preview', get_string('view')));

        if ($review->status === 'pending' && $review->reviewerid == $USER->id) {
            // Show review form.
            $approveurl = new moodle_url('/local/casospracticos/review_dashboard.php', [
                'action' => 'approve',
                'reviewid' => $review->id,
                'tab' => $tab,
                'sesskey' => sesskey(),
            ]);
            $rejecturl = new moodle_url('/local/casospracticos/review_dashboard.php', [
                'action' => 'reject',
                'reviewid' => $review->id,
                'tab' => $tab,
                'sesskey' => sesskey(),
            ]);
            $revisionurl = new moodle_url('/local/casospracticos/review_dashboard.php', [
                'action' => 'revision',
                'reviewid' => $review->id,
                'tab' => $tab,
                'sesskey' => sesskey(),
            ]);

            $actions[] = html_writer::link($approveurl, get_string('approve', 'local_casospracticos'),
                ['class' => 'btn btn-sm btn-success']);
            $actions[] = html_writer::link($revisionurl, get_string('requestrevision', 'local_casospracticos'),
                ['class' => 'btn btn-sm btn-warning']);
            $actions[] = html_writer::link($rejecturl, get_string('reject', 'local_casospracticos'),
                ['class' => 'btn btn-sm btn-danger']);
        }

        $row[] = implode(' ', $actions);

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

// Empty state.
if (empty($reviews) && empty($caseswaitingassignment)) {
    echo html_writer::tag('p', get_string('noitemsfound', 'local_casospracticos'), ['class' => 'text-muted']);
}

echo $OUTPUT->footer();
