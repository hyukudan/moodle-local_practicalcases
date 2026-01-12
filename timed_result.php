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
 * Display results for a timed practice attempt.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\timed_attempt_manager;

$attemptid = required_param('attempt', PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

$attempt = timed_attempt_manager::get_attempt($attemptid);
if (!$attempt) {
    throw new moodle_exception('error:attemptnotfound', 'local_casospracticos');
}

// Verify attempt belongs to user.
if ($attempt->userid != $USER->id && !has_capability('local/casospracticos:viewaudit', $context)) {
    throw new moodle_exception('error:nopermission', 'local_casospracticos');
}

$case = case_manager::get($attempt->caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/timed_result.php', ['attempt' => $attemptid]));
$PAGE->set_title(get_string('timedresults', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $attempt->caseid]));
$PAGE->navbar->add(get_string('timedresults', 'local_casospracticos'));

echo $OUTPUT->header();

// Results header.
echo html_writer::start_div('timed-results');
echo html_writer::tag('h2', get_string('timedresults', 'local_casospracticos'));

// Case info.
echo html_writer::tag('h4', format_string($case->name), ['class' => 'mb-3']);

// Score summary.
$percentage = round($attempt->percentage, 1);
$passthreshold = get_config('local_casospracticos', 'passthreshold') ?: 70;
$passed = $percentage >= $passthreshold;

$alertclass = $passed ? 'alert-success' : 'alert-danger';
echo html_writer::start_div('alert ' . $alertclass);

echo html_writer::tag('h3', get_string('yourscore', 'local_casospracticos') . ': ' . $percentage . '%');
echo html_writer::tag('p', get_string('scoredetail', 'local_casospracticos', [
    'score' => round($attempt->score, 2),
    'maxscore' => round($attempt->maxscore, 2)
]));

if ($passed) {
    echo html_writer::tag('p', get_string('congratspassed', 'local_casospracticos'), ['class' => 'mb-0']);
} else {
    echo html_writer::tag('p', get_string('notpassedyet', 'local_casospracticos', $passthreshold), ['class' => 'mb-0']);
}

echo html_writer::end_div();

// Time statistics.
$minutes = floor($attempt->timespent / 60);
$seconds = $attempt->timespent % 60;
$timelimitmin = round($attempt->timelimit / 60);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('timestatistics', 'local_casospracticos'), ['class' => 'card-title']);

echo html_writer::start_tag('ul');
echo html_writer::tag('li', get_string('timelimit', 'local_casospracticos') . ': ' . $timelimitmin . ' ' . get_string('minutes'));
echo html_writer::tag('li', get_string('timespent', 'local_casospracticos') . ': ' . $minutes . 'm ' . $seconds . 's');
echo html_writer::tag('li', get_string('started', 'local_casospracticos') . ': ' . userdate($attempt->timestart));
echo html_writer::tag('li', get_string('finished', 'local_casospracticos') . ': ' . userdate($attempt->timefinished));
echo html_writer::end_tag('ul');

echo html_writer::end_div();
echo html_writer::end_div();

// Detailed results per question.
$responsedata = json_decode($attempt->responsedata, true);
$questions = question_manager::get_with_answers($attempt->caseid);

// Reorder according to attempt.
$questionorder = json_decode($attempt->questionorder, true);
$orderedquestions = [];
foreach ($questionorder as $qid) {
    foreach ($questions as $q) {
        if ($q->id == $qid) {
            $orderedquestions[] = $q;
            break;
        }
    }
}

echo html_writer::tag('h4', get_string('detailedresults', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

$qnum = 0;
foreach ($orderedquestions as $question) {
    $qnum++;
    $qresult = $responsedata[$question->id] ?? null;

    if (!$qresult) {
        continue;
    }

    $correct = $qresult['correct'] ?? false;
    $cardclass = $correct ? 'border-success' : 'border-danger';

    echo html_writer::start_div('card mb-3 ' . $cardclass);
    echo html_writer::start_div('card-body');

    $icon = $correct ? '✓' : '✗';
    $iconclass = $correct ? 'text-success' : 'text-danger';
    echo html_writer::tag('h5',
        html_writer::tag('span', $icon, ['class' => $iconclass . ' me-2']) .
        get_string('questionx', 'local_casospracticos', $qnum),
        ['class' => 'card-title']
    );

    echo html_writer::div(
        format_text($question->questiontext, $question->questiontextformat),
        'question-text mb-3'
    );

    // Show user's answers.
    echo html_writer::tag('strong', get_string('youranswer', 'local_casospracticos') . ':');
    echo html_writer::start_tag('ul', ['class' => 'mb-2']);

    if (is_array($qresult['selected'])) {
        foreach ($qresult['selected'] as $answerid) {
            foreach ($question->answers as $answer) {
                if ($answer->id == $answerid) {
                    $style = $answer->fraction > 0 ? 'color: green;' : 'color: red;';
                    echo html_writer::tag('li',
                        format_text($answer->answer, $answer->answerformat),
                        ['style' => $style]
                    );
                }
            }
        }
    } else {
        echo html_writer::tag('li', s($qresult['selected']));
    }

    echo html_writer::end_tag('ul');

    // Show correct answers if wrong.
    if (!$correct) {
        echo html_writer::tag('strong', get_string('correctanswer', 'local_casospracticos') . ':');
        echo html_writer::start_tag('ul');

        foreach ($question->answers as $answer) {
            if ($answer->fraction > 0) {
                echo html_writer::tag('li',
                    format_text($answer->answer, $answer->answerformat),
                    ['style' => 'color: green;']
                );
            }
        }

        echo html_writer::end_tag('ul');
    }

    // Score for this question.
    echo html_writer::tag('p',
        get_string('questionscore', 'local_casospracticos') . ': ' .
        round($qresult['score'], 2) . ' / ' . $question->defaultmark,
        ['class' => 'mb-0 text-muted']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Action buttons.
echo html_writer::start_div('mt-4 text-center');

$tryagainurl = new moodle_url('/local/casospracticos/practice_timed.php', ['id' => $attempt->caseid]);
echo html_writer::link($tryagainurl, get_string('tryagain', 'local_casospracticos'),
    ['class' => 'btn btn-primary me-2']);

$caseurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $attempt->caseid]);
echo html_writer::link($caseurl, get_string('backtocaseview', 'local_casospracticos'),
    ['class' => 'btn btn-secondary']);

echo html_writer::end_div();

echo html_writer::end_div(); // timed-results.

echo $OUTPUT->footer();
