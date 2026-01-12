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
 * View all practice attempts for a case or all cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;

$caseid = optional_param('caseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

$case = null;
if ($caseid) {
    $case = case_manager::get($caseid);
    if (!$case) {
        throw new moodle_exception('error:casenotfound', 'local_casospracticos');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $caseid]));
$PAGE->set_title(get_string('myattempts', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
if ($case) {
    $PAGE->navbar->add(format_string($case->name),
        new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
}
$PAGE->navbar->add(get_string('myattempts', 'local_casospracticos'));

echo $OUTPUT->header();

if ($case) {
    echo $OUTPUT->heading(format_string($case->name) . ' - ' . get_string('myattempts', 'local_casospracticos'));
} else {
    echo $OUTPUT->heading(get_string('myattempts', 'local_casospracticos'));
}

// Check if table exists.
if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
    echo html_writer::div(get_string('noattemptsyet', 'local_casospracticos'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

// Build query.
$params = ['userid' => $USER->id, 'status' => 'finished'];
$where = 'userid = :userid AND status = :status';

if ($caseid) {
    $where .= ' AND caseid = :caseid';
    $params['caseid'] = $caseid;
}

// Count total.
$total = $DB->count_records_select('local_cp_practice_attempts', $where, $params);

if ($total == 0) {
    echo html_writer::div(get_string('noattemptsyet', 'local_casospracticos'), 'alert alert-info');

    if ($case) {
        $practiceurl = new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid]);
        echo html_writer::div(
            html_writer::link($practiceurl, get_string('startpractice', 'local_casospracticos'), ['class' => 'btn btn-primary']),
            'mt-3'
        );
    }

    echo $OUTPUT->footer();
    exit;
}

// Get attempts with case info.
$sql = "SELECT a.*, c.name as casename
        FROM {local_cp_practice_attempts} a
        JOIN {local_cp_cases} c ON c.id = a.caseid
        WHERE a.$where
        ORDER BY a.timefinished DESC";

$attempts = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Summary stats.
$sql = "SELECT COUNT(*) as total_attempts,
               AVG(percentage) as avg_percentage,
               MAX(percentage) as best_percentage,
               SUM(CASE WHEN percentage >= 70 THEN 1 ELSE 0 END) as passed_attempts
        FROM {local_cp_practice_attempts}
        WHERE $where";
$stats = $DB->get_record_sql($sql, $params);

// Stats cards.
echo html_writer::start_div('row mb-4');

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h4', $stats->total_attempts, ['class' => 'text-primary']);
echo html_writer::tag('p', get_string('totalattempts', 'local_casospracticos'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
$avgclass = $stats->avg_percentage >= 70 ? 'text-success' : ($stats->avg_percentage >= 50 ? 'text-warning' : 'text-danger');
echo html_writer::tag('h4', round($stats->avg_percentage, 1) . '%', ['class' => $avgclass]);
echo html_writer::tag('p', get_string('averagescore', 'local_casospracticos'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h4', round($stats->best_percentage, 1) . '%', ['class' => 'text-success']);
echo html_writer::tag('p', get_string('bestscore', 'local_casospracticos'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
$passrate = $stats->total_attempts > 0 ? round(($stats->passed_attempts / $stats->total_attempts) * 100) : 0;
echo html_writer::tag('h4', $passrate . '%', ['class' => 'text-info']);
echo html_writer::tag('p', get_string('passrate', 'local_casospracticos'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Attempts table.
$table = new html_table();
$table->head = [
    '#',
    get_string('case', 'local_casospracticos'),
    get_string('date'),
    get_string('score', 'grades'),
    get_string('timetaken', 'local_casospracticos'),
    get_string('actions'),
];
$table->attributes['class'] = 'table table-striped';

$num = $total - ($page * $perpage);
foreach ($attempts as $attempt) {
    $scoreclass = $attempt->percentage >= 70 ? 'text-success' :
                 ($attempt->percentage >= 50 ? 'text-warning' : 'text-danger');

    $timetaken = $attempt->timefinished - $attempt->timestarted;
    $timetakenstr = $timetaken > 0 ? format_time($timetaken) : '-';

    $reviewurl = new moodle_url('/local/casospracticos/review_attempt.php', ['id' => $attempt->id]);
    $caseurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $attempt->caseid]);

    $table->data[] = [
        $num--,
        html_writer::link($caseurl, format_string($attempt->casename)),
        userdate($attempt->timefinished, get_string('strftimedatetime', 'langconfig')),
        html_writer::tag('span',
            round($attempt->score, 1) . '/' . round($attempt->maxscore, 1) .
            ' (' . round($attempt->percentage) . '%)',
            ['class' => $scoreclass]
        ),
        $timetakenstr,
        html_writer::link($reviewurl, get_string('review', 'local_casospracticos'),
            ['class' => 'btn btn-sm btn-outline-primary']),
    ];
}

echo html_writer::table($table);

// Pagination.
$baseurl = new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $caseid]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

// Back button.
echo html_writer::start_div('mt-4');
if ($case) {
    $practiceurl = new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid]);
    echo html_writer::link($practiceurl, get_string('practicenow', 'local_casospracticos'), ['class' => 'btn btn-primary']);
    echo ' ';

    $backurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]);
    echo html_writer::link($backurl, get_string('backtocases', 'local_casospracticos'), ['class' => 'btn btn-secondary']);
} else {
    $backurl = new moodle_url('/local/casospracticos/index.php');
    echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();

echo $OUTPUT->footer();
