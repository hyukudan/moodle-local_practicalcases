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
 * Review a practice attempt - shows questions with user's answers and feedback.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;

$attemptid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();

// Get the attempt.
$attempt = $DB->get_record('local_cp_practice_attempts', ['id' => $attemptid], '*', MUST_EXIST);

// Check permission - user can only see their own attempts unless they have explicit review capability.
// We use a two-level check: viewaudit allows seeing all logs, but for viewing other users' practice
// attempts specifically, we require the more restrictive 'review' capability which is meant for
// reviewing cases submitted for approval. This ensures proper separation of concerns.
if ($attempt->userid != $USER->id) {
    // Must have either the general review capability or be a site admin.
    $canreview = has_capability('local/casospracticos:review', $context) ||
                 has_capability('local/casospracticos:viewaudit', $context) ||
                 is_siteadmin();
    if (!$canreview) {
        throw new moodle_exception('error:nopermission', 'local_casospracticos');
    }
}

$case = case_manager::get($attempt->caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/review_attempt.php', ['id' => $attemptid]));
$PAGE->set_title(get_string('reviewattempt', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]));
$PAGE->navbar->add(get_string('reviewattempt', 'local_casospracticos'));

// Get user responses for this attempt.
$responses = $DB->get_records('local_cp_practice_responses', ['attemptid' => $attemptid], '', 'questionid, response, score, iscorrect');

// Get questions with answers.
$questions = question_manager::get_with_answers($attempt->caseid);

echo $OUTPUT->header();

// Attempt header.
echo html_writer::start_div('attempt-review');

echo html_writer::tag('h2', format_string($case->name));

// Attempt info card.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

$scoreclass = $attempt->percentage >= 70 ? 'text-success' :
             ($attempt->percentage >= 50 ? 'text-warning' : 'text-danger');

echo html_writer::start_div('row');
echo html_writer::start_div('col-md-3');
echo html_writer::tag('strong', get_string('score', 'grades') . ': ');
echo html_writer::tag('span', round($attempt->score, 2) . ' / ' . round($attempt->maxscore, 2), ['class' => $scoreclass]);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::tag('strong', get_string('percentage', 'grades') . ': ');
echo html_writer::tag('span', round($attempt->percentage) . '%', ['class' => $scoreclass]);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::tag('strong', get_string('started', 'local_casospracticos') . ': ');
echo userdate($attempt->timestarted, get_string('strftimedatetime', 'langconfig'));
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::tag('strong', get_string('completed', 'local_casospracticos') . ': ');
echo userdate($attempt->timefinished, get_string('strftimedatetime', 'langconfig'));
echo html_writer::end_div();
echo html_writer::end_div(); // row

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Case statement.
echo html_writer::div(
    format_text($case->statement, $case->statementformat),
    'case-statement card card-body mb-4 bg-light'
);

// Questions with responses.
$qnum = 0;
foreach ($questions as $question) {
    $qnum++;
    $response = $responses[$question->id] ?? null;

    $cardclass = 'card mb-3';
    if ($response) {
        $cardclass .= $response->iscorrect ? ' border-success' : ' border-danger';
    }

    echo html_writer::start_div($cardclass);

    // Question header.
    $headerclass = 'card-header';
    if ($response) {
        $headerclass .= $response->iscorrect ? ' bg-success text-white' : ' bg-danger text-white';
    }
    echo html_writer::start_div($headerclass);
    echo html_writer::tag('strong', get_string('question', 'local_casospracticos') . ' ' . $qnum);
    echo ' (' . $question->defaultmark . ' ' . get_string('points', 'grades') . ')';
    if ($response) {
        echo ' - ' . get_string('yourmark', 'local_casospracticos') . ': ' . round($response->score, 2);
    }
    echo html_writer::end_div();

    // Question body.
    echo html_writer::start_div('card-body');
    echo html_writer::div(format_text($question->questiontext, $question->questiontextformat), 'question-text mb-3');

    // Parse user's response.
    $userselection = [];
    if ($response) {
        $decoded = json_decode($response->response, true);
        if (is_array($decoded)) {
            $userselection = $decoded;
        } else if ($response->response) {
            $userselection = [$response->response];
        }
    }

    // Show answers.
    if ($question->qtype === 'multichoice' || $question->qtype === 'truefalse') {
        foreach ($question->answers as $answer) {
            $isselected = in_array($answer->id, $userselection);
            $iscorrect = $answer->fraction >= 0.99;

            $divclass = 'p-2 mb-1 rounded';
            if ($isselected && $iscorrect) {
                $divclass .= ' bg-success text-white';
                $icon = $OUTPUT->pix_icon('i/valid', get_string('correct'));
            } else if ($isselected && !$iscorrect) {
                $divclass .= ' bg-danger text-white';
                $icon = $OUTPUT->pix_icon('i/invalid', get_string('incorrect'));
            } else if (!$isselected && $iscorrect) {
                $divclass .= ' bg-warning';
                $icon = $OUTPUT->pix_icon('i/valid', get_string('correctanswer', 'local_casospracticos'));
            } else {
                $divclass .= ' bg-light';
                $icon = '';
            }

            echo html_writer::start_div($divclass);
            echo $icon . ' ' . format_text($answer->answer, $answer->answerformat);

            if ($isselected) {
                echo ' ' . html_writer::tag('span', get_string('youranswer', 'local_casospracticos'),
                    ['class' => 'badge bg-dark ms-2']);
            }
            if ($iscorrect) {
                echo ' ' . html_writer::tag('span', get_string('correctanswer', 'local_casospracticos'),
                    ['class' => 'badge bg-success ms-2']);
            }

            // Show feedback for this answer if user selected it.
            if ($isselected && !empty($answer->feedback)) {
                echo html_writer::div(
                    $OUTPUT->pix_icon('i/info', '') . ' ' . format_text($answer->feedback, $answer->feedbackformat),
                    'mt-2 small'
                );
            }

            echo html_writer::end_div();
        }

    } else if ($question->qtype === 'shortanswer') {
        $usertext = $userselection[0] ?? '';

        echo html_writer::div(
            html_writer::tag('strong', get_string('youranswer', 'local_casospracticos') . ': ') . s($usertext),
            'mb-2'
        );

        // Show correct answer.
        foreach ($question->answers as $answer) {
            if ($answer->fraction >= 0.99) {
                echo html_writer::div(
                    html_writer::tag('strong', get_string('correctanswer', 'local_casospracticos') . ': ') .
                    format_text($answer->answer, $answer->answerformat),
                    'text-success'
                );
                break;
            }
        }
    }

    // General feedback.
    if (!empty($question->generalfeedback)) {
        echo html_writer::div(
            html_writer::tag('strong', get_string('generalfeedback', 'local_casospracticos') . ': ') .
            format_text($question->generalfeedback, $question->generalfeedbackformat),
            'alert alert-info mt-3'
        );
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

// Navigation buttons.
echo html_writer::start_div('mt-4 mb-4');

$practiceurl = new moodle_url('/local/casospracticos/practice.php', ['id' => $case->id]);
echo html_writer::link($practiceurl, get_string('tryagain', 'local_casospracticos'), ['class' => 'btn btn-primary']);

echo ' ';

$attemptsurl = new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $case->id]);
echo html_writer::link($attemptsurl, get_string('viewmyattempts', 'local_casospracticos'), ['class' => 'btn btn-outline-info']);

echo ' ';

$backurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);
echo html_writer::link($backurl, get_string('backtocases', 'local_casospracticos'), ['class' => 'btn btn-secondary']);

echo html_writer::end_div();

echo html_writer::end_div(); // attempt-review

echo $OUTPUT->footer();
