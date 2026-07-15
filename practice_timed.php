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
require_once($CFG->dirroot . '/local/casospracticos/lib.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\stats_manager;
use local_casospracticos\practice_session_manager;
use local_casospracticos\timed_attempt_manager;
use local_casospracticos\practice_engine;

$caseid = required_param('id', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$submit = optional_param('submit', 0, PARAM_BOOL);
$timelimit = optional_param('timelimit', 30, PARAM_INT); // Default 30 minutes.

// The time limit is the assessment deadline, so it must be server-authoritative:
// a client could otherwise post an arbitrarily large value and effectively
// disable the timer. There is no per-case configured limit, so clamp the
// request value to a sane minimum/maximum range (in minutes). The clamped
// value is the only thing handed to start_attempt(); the persisted attempt
// timelimit then drives every server-side deadline/expiry check below.
$mintimelimit = 1;   // Minutes.
$maxtimelimit = 180; // Minutes (3 hours).
$timelimit = max($mintimelimit, min($maxtimelimit, $timelimit));

$context = context_system::instance();
require_login();

// Timed practice reveals answer keys and feedback, so it requires full access.
local_casospracticos_require_view_access(LOCAL_CP_ACCESS_FULL);

$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

if (!case_manager::is_visible_to_user($case, $context)) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
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
    // A GET cannot carry a sesskey. Finalize idempotently from autosaved data
    // after the manager verifies owner, status and the authoritative deadline.
    timed_attempt_manager::finalize_expired_attempt($attemptid, $USER->id);
    redirect(new moodle_url('/local/casospracticos/timed_result.php', ['attempt' => $attemptid]));
}

// Get all questions of this case with their answers (keyed by question id).
$questions = question_manager::filter_practice_questions(
    question_manager::get_by_case_with_answers($caseid)
);
$unsupportedqtypes = question_manager::unsupported_practice_qtypes($questions);
if ($unsupportedqtypes) {
    throw new moodle_exception('error:unsupportedpracticeqtype', 'local_casospracticos', '',
        implode(', ', $unsupportedqtypes));
}

// Restore the shuffled question order persisted on the attempt.
$questionorder = json_decode($attempt->questionorder ?? '', true);
if (!is_array($questionorder)) {
    $questionorder = [];
}
$orderedquestions = [];
foreach ($questionorder as $qid) {
    foreach ($questions as $q) {
        if ($q->id == $qid) {
            $orderedquestions[] = $q;
            break;
        }
    }
}
// Fallback: if the stored order is missing/stale, present all case questions.
if (empty($orderedquestions)) {
    $orderedquestions = array_values($questions);
}
$questions = $orderedquestions;

// Shuffle answer order so students don't see the correct option always in
// position A (the bank stores correct answers with sortorder=1 ~92% of the
// time). Order is cached in $SESSION keyed by attemptid so reloads, autosave
// round-trips and the submit redirect keep the same order the student picked.
$answersorderkey = 'casospracticos_aorder_timed_' . $attemptid;
question_manager::shuffle_answers_for_render($questions, $answersorderkey);

// Process submitted answers.
$results = [];
$score = 0;
$maxscore = 0;

if ($submit) {
    require_sesskey();

    // Re-check the deadline server-side before accepting the submission. Never
    // trust the client: an expired attempt must be finalized as the time-out
    // submission, not graded as a fresh on-time submit. We re-read the attempt
    // and compare timestarted + timelimit against the current time.
    $freshattempt = timed_attempt_manager::get_attempt($attemptid);
    if (!$freshattempt || $freshattempt->status !== timed_attempt_manager::STATUS_INPROGRESS) {
        // Already finalized (submitted/expired) by another request or task.
        redirect(new moodle_url('/local/casospracticos/timed_result.php', ['attempt' => $attemptid]));
    }
    $deadline = (int) $freshattempt->timestarted + (int) $freshattempt->timelimit;
    $expired = (time() > $deadline);

    // Delegate scoring to the shared practice engine so timed and untimed
    // scoring behave identically (fraction-aware marks, correct flag set only
    // for fraction >= 0.99, partial credit, etc.).
    $scored = practice_engine::score_submission($questions, $_POST);
    $results = $scored['results'];
    $score = $scored['score'];
    $maxscore = $scored['maxscore'];

    // Build the per-question response payload stored on the attempt.
    $responsedata = [];
    foreach ($results as $qid => $res) {
        $responsedata[$qid] = [
            'selected' => $res->selectedids ?? ($res->response ?? ''),
            'score' => $res->score ?? 0,
            'correct' => $res->correct ?? false,
            'requiresgrading' => $res->requiresgrading ?? false,
        ];
    }

    // Finish the timed attempt. Compute time spent from the real timestamps;
    // clamp to the time limit when the deadline was exceeded.
    $timespent = time() - (int) $freshattempt->timestarted;
    if ($expired) {
        $timespent = min($timespent, (int) $freshattempt->timelimit);
    }
    $timespent = max(0, $timespent);
    timed_attempt_manager::finish_attempt($attemptid, $score, $maxscore, $responsedata, $timespent,
        $scored['gradingstatus']);

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
    } else if ($question->qtype === 'essay') {
        $paramname = 'q' . $question->id;
        echo html_writer::tag('label', get_string('youranswer', 'local_casospracticos'), [
            'for' => $paramname,
            'class' => 'form-label',
        ]);
        echo html_writer::tag('textarea', s((string) ($savedvalue ?? '')), [
            'id' => $paramname,
            'name' => $paramname,
            'class' => 'form-control',
            'rows' => 8,
        ]);
        echo html_writer::div(get_string('essaymanualgrading', 'local_casospracticos'), 'form-text');
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
