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
 * Timed practice mode for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\stats_manager;
use local_casospracticos\practice_session_manager;
use local_casospracticos\timed_attempt_manager;

$caseid = required_param('id', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$submit = optional_param('submit', 0, PARAM_BOOL);
$timelimit = optional_param('timelimit', 30, PARAM_INT); // Default 30 minutes.

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

// Only published cases can be practiced.
if ($case->status !== 'published') {
    require_capability('local/casospracticos:edit', $context);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/practice_timed.php', ['id' => $caseid]));
$PAGE->set_title(get_string('timedpractice', 'local_casospracticos') . ': ' . format_string($case->name));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add(get_string('timedpractice', 'local_casospracticos'));

// Get or create attempt.
if (!$attemptid) {
    // Start new timed attempt.
    $attemptid = timed_attempt_manager::start_attempt($caseid, $USER->id, $timelimit);
    redirect(new moodle_url('/local/casospracticos/practice_timed.php', [
        'id' => $caseid,
        'attempt' => $attemptid
    ]));
}

// Get attempt details.
$attempt = timed_attempt_manager::get_attempt($attemptid);
if (!$attempt) {
    throw new moodle_exception('error:attemptnotfound', 'local_casospracticos');
}

// Verify attempt belongs to user.
if ($attempt->userid != $USER->id) {
    throw new moodle_exception('error:nopermission', 'local_casospracticos');
}

// Check if already finished.
if ($attempt->status === 'finished') {
    redirect(new moodle_url('/local/casospracticos/timed_result.php', ['attempt' => $attemptid]));
}

// Check if time expired.
$timeleft = timed_attempt_manager::get_time_left($attemptid);
if ($timeleft <= 0 && !$submit) {
    // Auto-submit when time runs out.
    $submit = true;
}

// Get questions with answers.
$questions = question_manager::get_with_answers($caseid);

// Restore question order from attempt.
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
$questions = $orderedquestions;

// Process submitted answers.
$results = [];
$score = 0;
$maxscore = 0;

if ($submit && confirm_sesskey()) {
    foreach ($questions as $question) {
        $maxscore += $question->defaultmark;
        $paramname = 'q' . $question->id;

        $result = new stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = [];

        if ($question->qtype === 'multichoice') {
            if ($question->single) {
                $selected = optional_param($paramname, 0, PARAM_INT);
                $result->selectedids = $selected ? [$selected] : [];
            } else {
                $selected = optional_param_array($paramname, [], PARAM_INT);
                $result->selectedids = $selected;
            }

            // Calculate score for this question.
            $questionscore = 0;
            foreach ($question->answers as $answer) {
                $wasselected = in_array($answer->id, $result->selectedids);
                if ($wasselected && $answer->fraction > 0) {
                    $questionscore += $answer->fraction * $question->defaultmark;
                } else if ($wasselected && $answer->fraction < 0) {
                    $questionscore += $answer->fraction * $question->defaultmark;
                }
            }
            $questionscore = max(0, $questionscore);
            $result->score = $questionscore;
            $result->correct = ($questionscore >= $question->defaultmark * 0.99);

            // Get feedback from first selected answer.
            foreach ($question->answers as $answer) {
                if (in_array($answer->id, $result->selectedids) && !empty($answer->feedback)) {
                    $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                }
            }

        } else if ($question->qtype === 'shortanswer') {
            $response = optional_param($paramname, '', PARAM_TEXT);
            $result->response = $response;
            $result->score = 0;
            foreach ($question->answers as $answer) {
                if (trim(strtolower($response)) === trim(strtolower($answer->answer))) {
                    $result->score = $answer->fraction * $question->defaultmark;
                    $result->correct = true;
                    if (!empty($answer->feedback)) {
                        $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                    }
                    break;
                }
            }
        } else if ($question->qtype === 'truefalse') {
            $selected = optional_param($paramname, -1, PARAM_INT);
            if ($selected >= 0) {
                $result->selectedids = [$selected];
                foreach ($question->answers as $answer) {
                    if ($answer->id == $selected && $answer->fraction > 0) {
                        $result->score = $question->defaultmark;
                        $result->correct = true;
                    }
                    if ($answer->id == $selected && !empty($answer->feedback)) {
                        $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                    }
                }
            }
        }

        $score += $result->score ?? 0;
        $results[$question->id] = $result;
    }

    // Finish the timed attempt.
    $responsedata = [];
    foreach ($results as $qid => $res) {
        $responsedata[$qid] = [
            'selected' => $res->selectedids ?? ($res->response ?? ''),
            'score' => $res->score ?? 0,
            'correct' => $res->correct ?? false,
        ];
    }

    $timespent = time() - $attempt->timestart;
    timed_attempt_manager::finish_attempt($attemptid, $score, $maxscore, $responsedata, $timespent);

    // Redirect to results page.
    redirect(new moodle_url('/local/casospracticos/timed_result.php', ['attempt' => $attemptid]));
}

// Include timer JavaScript.
$PAGE->requires->js_call_amd('local_casospracticos/timer', 'init', [
    'timeleft' => $timeleft,
    'autosubmit' => true
]);

// Include auto-save JavaScript.
$PAGE->requires->js_call_amd('local_casospracticos/practice_autosave', 'init', [
    'attemptId' => $attemptid,
    'formSelector' => '#timed-practice-form'
]);

// Load saved responses to restore form state.
$savedresponses = timed_attempt_manager::get_saved_responses($attemptid);

echo $OUTPUT->header();

// Timer display.
echo html_writer::start_div('alert alert-info timed-practice-timer');
echo html_writer::tag('h4', get_string('timeleft', 'local_casospracticos'));
echo html_writer::tag('div', '', ['id' => 'timer-display', 'class' => 'timer-display']);
echo html_writer::end_div();

// Case header with statement.
echo html_writer::start_div('case-practice');
echo html_writer::tag('h3', format_string($case->name));
echo html_writer::div(format_text($case->statement, $case->statementformat), 'case-statement mb-4');

// Instructions.
if (!$submit) {
    echo html_writer::start_div('alert alert-warning');
    echo html_writer::tag('strong', get_string('timedpracticewarning', 'local_casospracticos'));
    echo html_writer::tag('p', get_string('timedpracticewarning_desc', 'local_casospracticos'));
    echo html_writer::end_div();

    // Auto-save notification.
    echo html_writer::start_div('alert alert-info d-flex align-items-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-save me-2', 'aria-hidden' => 'true']);
    echo html_writer::tag('span', get_string('autosaveenabled', 'local_casospracticos'));
    echo html_writer::end_div();

    // Show notification if responses were restored.
    if (!empty($savedresponses)) {
        echo html_writer::start_div('alert alert-success d-flex align-items-center');
        echo html_writer::tag('i', '', ['class' => 'fa fa-check-circle me-2', 'aria-hidden' => 'true']);
        echo html_writer::tag('span', get_string('responsesrestored', 'local_casospracticos'));
        echo html_writer::end_div();
    }
}

// Start form.
$formurl = new moodle_url('/local/casospracticos/practice_timed.php', [
    'id' => $caseid,
    'attempt' => $attemptid,
    'submit' => 1
]);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl, 'id' => 'timed-practice-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Questions.
$qnum = 0;
foreach ($questions as $question) {
    $qnum++;
    echo html_writer::start_div('question-container card mb-3');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h5', get_string('questionx', 'local_casospracticos', $qnum), ['class' => 'card-title']);
    echo html_writer::div(
        format_text($question->questiontext, $question->questiontextformat),
        'question-text mb-3'
    );

    // Get saved response for this question if available.
    $savedvalue = $savedresponses[$question->id] ?? null;

    if ($question->qtype === 'multichoice') {
        $paramname = 'q' . $question->id;

        if ($question->single) {
            // Single choice radio buttons.
            foreach ($question->answers as $answer) {
                $id = 'answer_' . $answer->id;
                $attrs = ['type' => 'radio', 'name' => $paramname, 'value' => $answer->id, 'id' => $id];
                // Restore saved selection.
                if ($savedvalue !== null && (string)$savedvalue === (string)$answer->id) {
                    $attrs['checked'] = 'checked';
                }
                echo html_writer::start_div('form-check');
                echo html_writer::empty_tag('input', $attrs + ['class' => 'form-check-input']);
                echo html_writer::tag('label', format_text($answer->answer, $answer->answerformat),
                    ['for' => $id, 'class' => 'form-check-label']);
                echo html_writer::end_div();
            }
        } else {
            // Multiple choice checkboxes.
            $savedarray = is_array($savedvalue) ? $savedvalue : [];
            foreach ($question->answers as $answer) {
                $id = 'answer_' . $answer->id;
                $attrs = ['type' => 'checkbox', 'name' => $paramname . '[]', 'value' => $answer->id, 'id' => $id];
                // Restore saved selections.
                if (in_array((string)$answer->id, $savedarray)) {
                    $attrs['checked'] = 'checked';
                }
                echo html_writer::start_div('form-check');
                echo html_writer::empty_tag('input', $attrs + ['class' => 'form-check-input']);
                echo html_writer::tag('label', format_text($answer->answer, $answer->answerformat),
                    ['for' => $id, 'class' => 'form-check-label']);
                echo html_writer::end_div();
            }
        }

    } else if ($question->qtype === 'shortanswer') {
        $paramname = 'q' . $question->id;
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => $paramname,
            'class' => 'form-control',
            'placeholder' => get_string('youranswer', 'local_casospracticos'),
            'value' => $savedvalue ?? ''  // Restore saved text.
        ]);

    } else if ($question->qtype === 'truefalse') {
        $paramname = 'q' . $question->id;
        foreach ($question->answers as $answer) {
            $id = 'answer_' . $answer->id;
            $attrs = ['type' => 'radio', 'name' => $paramname, 'value' => $answer->id, 'id' => $id];
            // Restore saved selection.
            if ($savedvalue !== null && (string)$savedvalue === (string)$answer->id) {
                $attrs['checked'] = 'checked';
            }
            echo html_writer::start_div('form-check');
            echo html_writer::empty_tag('input', $attrs + ['class' => 'form-check-input']);
            echo html_writer::tag('label', format_text($answer->answer, $answer->answerformat),
                ['for' => $id, 'class' => 'form-check-label']);
            echo html_writer::end_div();
        }
    }

    echo html_writer::end_div(); // card-body.
    echo html_writer::end_div(); // question-container.
}

// Submit button.
echo html_writer::start_div('text-center mt-4');
echo html_writer::tag('button', get_string('submitanswers', 'local_casospracticos'), [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // case-practice.

echo $OUTPUT->footer();
