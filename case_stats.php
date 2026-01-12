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
 * Case statistics and analytics page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\stats_manager;

$caseid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/case_stats.php', ['id' => $caseid]));
$PAGE->set_title(get_string('statistics', 'local_casospracticos') . ': ' . format_string($case->name));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add(get_string('statistics', 'local_casospracticos'));

// Get statistics.
$stats = stats_manager::get_case_stats($caseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($case->name) . ' - ' . get_string('statistics', 'local_casospracticos'));

// Overview cards.
echo html_writer::start_div('row mb-4');

// Card: Total views.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->total_views, ['class' => 'card-title text-primary']);
echo html_writer::tag('p', get_string('totalviews', 'local_casospracticos'), ['class' => 'card-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Card: Quiz insertions.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->total_insertions, ['class' => 'card-title text-success']);
echo html_writer::tag('p', get_string('quizinsertions', 'local_casospracticos'), ['class' => 'card-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Card: Practice attempts.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->practice_attempts, ['class' => 'card-title text-info']);
echo html_writer::tag('p', get_string('practiceattempts', 'local_casospracticos'), ['class' => 'card-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Card: Average score.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
$avgclass = $stats->avg_score >= 70 ? 'text-success' : ($stats->avg_score >= 50 ? 'text-warning' : 'text-danger');
echo html_writer::tag('h3', round($stats->avg_score, 1) . '%', ['class' => 'card-title ' . $avgclass]);
echo html_writer::tag('p', get_string('averagescore', 'local_casospracticos'), ['class' => 'card-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Question performance table.
echo html_writer::tag('h4', get_string('questionperformance', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

if (!empty($stats->question_stats)) {
    $table = new html_table();
    $table->head = [
        get_string('question', 'local_casospracticos'),
        get_string('questiontype', 'local_casospracticos'),
        get_string('attempts', 'local_casospracticos'),
        get_string('correctrate', 'local_casospracticos'),
        get_string('avgpoints', 'local_casospracticos'),
    ];
    $table->attributes['class'] = 'table table-striped';

    $qtypes = local_casospracticos_get_supported_qtypes();

    foreach ($stats->question_stats as $qstat) {
        $correctclass = $qstat->correct_rate >= 70 ? 'text-success' : ($qstat->correct_rate >= 50 ? 'text-warning' : 'text-danger');
        $table->data[] = [
            html_writer::tag('span', '#' . $qstat->sortorder, ['class' => 'badge bg-primary me-2']) .
                shorten_text(strip_tags($qstat->questiontext), 60),
            $qtypes[$qstat->qtype] ?? $qstat->qtype,
            $qstat->attempts,
            html_writer::tag('span', round($qstat->correct_rate, 1) . '%', ['class' => $correctclass]),
            round($qstat->avg_points, 2) . ' / ' . $qstat->defaultmark,
        ];
    }

    echo html_writer::table($table);
} else {
    echo html_writer::div(get_string('nostatisticsyet', 'local_casospracticos'), 'alert alert-info');
}

// Quiz usage section.
echo html_writer::tag('h4', get_string('usageinquizzes', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

if (!empty($stats->quiz_usage)) {
    $table = new html_table();
    $table->head = [
        get_string('quiz', 'quiz'),
        get_string('course'),
        get_string('timesinserted', 'local_casospracticos'),
        get_string('lastused', 'local_casospracticos'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($stats->quiz_usage as $usage) {
        $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $usage->cmid]);
        $courseurl = new moodle_url('/course/view.php', ['id' => $usage->courseid]);

        $table->data[] = [
            html_writer::link($quizurl, format_string($usage->quizname)),
            html_writer::link($courseurl, format_string($usage->coursename)),
            $usage->insertions,
            $usage->lastused ? userdate($usage->lastused) : '-',
        ];
    }

    echo html_writer::table($table);
} else {
    echo html_writer::div(get_string('notusedyet', 'local_casospracticos'), 'alert alert-info');
}

// Recent practice attempts.
if (has_capability('local/casospracticos:viewaudit', $context)) {
    echo html_writer::tag('h4', get_string('recentpracticeattempts', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

    if (!empty($stats->recent_attempts)) {
        $table = new html_table();
        $table->head = [
            get_string('user'),
            get_string('date'),
            get_string('score', 'grades'),
            get_string('timetaken', 'local_casospracticos'),
        ];
        $table->attributes['class'] = 'table table-striped';

        foreach ($stats->recent_attempts as $attempt) {
            $scoreclass = $attempt->percentage >= 70 ? 'text-success' : ($attempt->percentage >= 50 ? 'text-warning' : 'text-danger');
            $timetaken = $attempt->timefinished - $attempt->timestarted;

            $table->data[] = [
                fullname($attempt),
                userdate($attempt->timefinished),
                html_writer::tag('span', round($attempt->score, 1) . ' / ' . round($attempt->maxscore, 1) .
                    ' (' . round($attempt->percentage) . '%)', ['class' => $scoreclass]),
                $timetaken > 0 ? format_time($timetaken) : '-',
            ];
        }

        echo html_writer::table($table);
    } else {
        echo html_writer::div(get_string('nopracticeattempts', 'local_casospracticos'), 'alert alert-info');
    }
}

// Score distribution chart (simple text-based).
echo html_writer::tag('h4', get_string('scoredistribution', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

if (!empty($stats->score_distribution)) {
    echo html_writer::start_div('score-distribution');
    $ranges = [
        '0-20' => get_string('range020', 'local_casospracticos'),
        '21-40' => get_string('range2140', 'local_casospracticos'),
        '41-60' => get_string('range4160', 'local_casospracticos'),
        '61-80' => get_string('range6180', 'local_casospracticos'),
        '81-100' => get_string('range81100', 'local_casospracticos'),
    ];

    $maxcount = max(array_values($stats->score_distribution)) ?: 1;

    foreach ($ranges as $key => $label) {
        $count = $stats->score_distribution[$key] ?? 0;
        $width = ($count / $maxcount) * 100;
        $barclass = strpos($key, '81') !== false ? 'bg-success' :
                   (strpos($key, '61') !== false ? 'bg-info' :
                   (strpos($key, '41') !== false ? 'bg-warning' : 'bg-danger'));

        echo html_writer::start_div('mb-2');
        echo html_writer::tag('span', $label, ['class' => 'd-inline-block', 'style' => 'width: 80px;']);
        echo html_writer::start_div('progress d-inline-block', ['style' => 'width: calc(100% - 140px); vertical-align: middle;']);
        echo html_writer::div('', 'progress-bar ' . $barclass, ['style' => 'width: ' . $width . '%']);
        echo html_writer::end_div();
        echo html_writer::tag('span', $count, ['class' => 'ms-2']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('nostatisticsyet', 'local_casospracticos'), 'alert alert-info');
}

// Back button.
echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]),
        get_string('back'),
        'get'
    ),
    'mt-4'
);

echo $OUTPUT->footer();
