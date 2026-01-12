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
 * Practice mode for practical cases - students can attempt without a quiz.
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

$caseid = required_param('id', PARAM_INT);
$submit = optional_param('submit', 0, PARAM_BOOL);
$shuffle = optional_param('shuffle', 0, PARAM_BOOL);
$sessiontoken = optional_param('token', '', PARAM_ALPHANUM);

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
$PAGE->set_url(new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid]));
$PAGE->set_title(get_string('practice', 'local_casospracticos') . ': ' . format_string($case->name));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add(get_string('practice', 'local_casospracticos'));

// Get questions with answers.
$questions = question_manager::get_with_answers($caseid);

// Handle session-based question order (secure implementation).
if ($shuffle && !$submit && empty($sessiontoken)) {
    // New session: shuffle and create secure token.
    shuffle($questions);
    $questionids = array_column($questions, 'id');
    $sessiontoken = practice_session_manager::create_session($caseid, $USER->id, $questionids);

    // Redirect to include token in URL.
    redirect(new moodle_url('/local/casospracticos/practice.php', [
        'id' => $caseid,
        'token' => $sessiontoken
    ]));

} else if (!empty($sessiontoken)) {
    // Restore order from secure session.
    $session = practice_session_manager::get_session($sessiontoken);

    if (!$session) {
        // Session expired or invalid.
        \core\notification::error(get_string('error:sessionexpired', 'local_casospracticos'));
        redirect(new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid]));
    }

    // Verify session belongs to current user.
    if (!practice_session_manager::verify_session_ownership($sessiontoken, $USER->id)) {
        throw new moodle_exception('error:invalidsession', 'local_casospracticos');
    }

    $questionorder = json_decode($session->questionorder, true);

    // Reorder questions according to session.
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
}

// Process submitted answers.
$results = [];
$score = 0;
$maxscore = 0;

if ($submit) {
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
                if (in_array($answer->id, $result->selectedids)) {
                    $questionscore += $answer->fraction * $question->defaultmark;
                    if (!empty($answer->feedback)) {
                        $result->feedback .= format_text($answer->feedback, $answer->feedbackformat) . ' ';
                    }
                }
            }
            $result->score = max(0, $questionscore);
            $result->correct = ($result->score >= $question->defaultmark * 0.99);

        } else if ($question->qtype === 'truefalse') {
            $selected = optional_param($paramname, -1, PARAM_INT);
            $result->selectedids = $selected >= 0 ? [$selected] : [];

            foreach ($question->answers as $answer) {
                if ((int) $answer->id === (int) $selected) {
                    $result->score = $answer->fraction * $question->defaultmark;
                    $result->correct = ($answer->fraction >= 0.99);
                    if (!empty($answer->feedback)) {
                        $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                    }
                }
            }

        } else if ($question->qtype === 'shortanswer') {
            $response = optional_param($paramname, '', PARAM_TEXT);
            $result->response = $response;
            $result->score = 0;

            foreach ($question->answers as $answer) {
                if (strcasecmp(trim($response), trim($answer->answer)) === 0) {
                    $result->score = $answer->fraction * $question->defaultmark;
                    $result->correct = ($answer->fraction >= 0.99);
                    if (!empty($answer->feedback)) {
                        $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                    }
                    break;
                }
            }

        } else if ($question->qtype === 'essay') {
            $response = optional_param($paramname, '', PARAM_RAW);
            $result->response = $response;
            $result->score = 0; // Essays must be graded manually.
            $result->correct = false;
            $result->feedback = get_string('essaymanualgrading', 'local_casospracticos');

        } else if ($question->qtype === 'matching') {
            $result->matches = [];
            // Get all submitted pairs.
            if (!empty($question->subquestions)) {
                foreach ($question->subquestions as $subq) {
                    $matchparam = $paramname . '_' . $subq->id;
                    $selectedmatch = optional_param($matchparam, '', PARAM_TEXT);
                    $result->matches[$subq->id] = $selectedmatch;
                }
                // Score matching.
                $correctcount = 0;
                $totalcount = count($question->subquestions);
                foreach ($question->subquestions as $subq) {
                    if (isset($result->matches[$subq->id]) &&
                        strcasecmp(trim($result->matches[$subq->id]), trim($subq->answertext)) === 0) {
                        $correctcount++;
                    }
                }
                if ($totalcount > 0) {
                    $result->score = ($correctcount / $totalcount) * $question->defaultmark;
                    $result->correct = ($correctcount == $totalcount);
                }
            }
        }

        $score += $result->score ?? 0;
        $results[$question->id] = $result;
    }

    // Save the attempt.
    $responsedata = [];
    foreach ($results as $qid => $res) {
        $responsedata[$qid] = [
            'selected' => $res->selectedids ?? ($res->response ?? ''),
            'score' => $res->score ?? 0,
            'correct' => $res->correct ?? false,
        ];
    }
    stats_manager::record_practice_attempt($caseid, $USER->id, $score, $maxscore, $responsedata);

    // Clean up session after attempt is completed.
    if (!empty($sessiontoken)) {
        practice_session_manager::delete_session($sessiontoken);
    }
}

echo $OUTPUT->header();

// Case header with statement.
echo html_writer::start_div('case-practice');

echo html_writer::tag('h2', format_string($case->name));
echo html_writer::div(
    format_text($case->statement, $case->statementformat),
    'case-statement card card-body mb-4 bg-light'
);

// Results summary if submitted.
if ($submit) {
    $percentage = $maxscore > 0 ? round(($score / $maxscore) * 100) : 0;
    $alertclass = $percentage >= 70 ? 'alert-success' : ($percentage >= 50 ? 'alert-warning' : 'alert-danger');

    echo html_writer::start_div('alert ' . $alertclass);
    echo html_writer::tag('h4', get_string('results', 'local_casospracticos'));
    echo html_writer::tag('p',
        get_string('yourscoreis', 'local_casospracticos', [
            'score' => round($score, 2),
            'max' => round($maxscore, 2),
            'percentage' => $percentage
        ])
    );
    echo html_writer::end_div();
}

// Start form.
$formurl = new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid, 'submit' => 1]);
if (!empty($sessiontoken)) {
    $formurl->param('token', $sessiontoken);
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Questions.
$qnum = 0;
foreach ($questions as $question) {
    $qnum++;
    $result = $results[$question->id] ?? null;

    $cardclass = 'card mb-3';
    if ($result) {
        $cardclass .= $result->correct ? ' border-success' : ' border-danger';
    }

    echo html_writer::start_div($cardclass);

    // Question header.
    $headerclass = 'card-header';
    if ($result) {
        $headerclass .= $result->correct ? ' bg-success text-white' : ' bg-danger text-white';
    }
    echo html_writer::start_div($headerclass);
    echo html_writer::tag('strong', get_string('question', 'local_casospracticos') . ' ' . $qnum);
    echo ' (' . $question->defaultmark . ' ' . get_string('points', 'grades') . ')';
    if ($result) {
        echo ' - ' . round($result->score, 2) . ' ' . get_string('points', 'grades');
    }
    echo html_writer::end_div();

    // Question body.
    echo html_writer::start_div('card-body');
    echo html_writer::div(format_text($question->questiontext, $question->questiontextformat), 'question-text mb-3');

    $paramname = 'q' . $question->id;

    if ($question->qtype === 'multichoice') {
        $inputtype = $question->single ? 'radio' : 'checkbox';
        $inputname = $question->single ? $paramname : $paramname . '[]';

        foreach ($question->answers as $answer) {
            $isselected = $result && in_array($answer->id, $result->selectedids);
            $iscorrect = $answer->fraction >= 0.99;

            $divclass = 'form-check';
            if ($result) {
                if ($isselected && $iscorrect) {
                    $divclass .= ' text-success';
                } else if ($isselected && !$iscorrect) {
                    $divclass .= ' text-danger';
                } else if (!$isselected && $iscorrect) {
                    $divclass .= ' text-warning';
                }
            }

            echo html_writer::start_div($divclass);

            $attrs = [
                'type' => $inputtype,
                'name' => $inputname,
                'value' => $answer->id,
                'id' => 'a' . $answer->id,
                'class' => 'form-check-input',
            ];
            if ($isselected) {
                $attrs['checked'] = 'checked';
            }
            if ($submit) {
                $attrs['disabled'] = 'disabled';
            }

            echo html_writer::empty_tag('input', $attrs);

            $labeltext = format_text($answer->answer, $answer->answerformat);
            if ($result && $iscorrect) {
                $labeltext .= ' ' . $OUTPUT->pix_icon('i/valid', get_string('correct'));
            }
            echo html_writer::tag('label', $labeltext, ['for' => 'a' . $answer->id, 'class' => 'form-check-label']);

            // Show feedback if submitted and this answer was selected.
            if ($result && $isselected && !empty($answer->feedback)) {
                echo html_writer::div(
                    $OUTPUT->pix_icon('i/info', '') . ' ' . format_text($answer->feedback, $answer->feedbackformat),
                    'answer-feedback small text-muted mt-1 ms-4'
                );
            }

            echo html_writer::end_div();
        }

    } else if ($question->qtype === 'truefalse') {
        foreach ($question->answers as $answer) {
            $isselected = $result && in_array($answer->id, $result->selectedids);
            $iscorrect = $answer->fraction >= 0.99;

            $divclass = 'form-check';
            if ($result) {
                if ($isselected && $iscorrect) {
                    $divclass .= ' text-success';
                } else if ($isselected && !$iscorrect) {
                    $divclass .= ' text-danger';
                } else if (!$isselected && $iscorrect) {
                    $divclass .= ' text-warning';
                }
            }

            echo html_writer::start_div($divclass);

            $attrs = [
                'type' => 'radio',
                'name' => $paramname,
                'value' => $answer->id,
                'id' => 'a' . $answer->id,
                'class' => 'form-check-input',
            ];
            if ($isselected) {
                $attrs['checked'] = 'checked';
            }
            if ($submit) {
                $attrs['disabled'] = 'disabled';
            }

            echo html_writer::empty_tag('input', $attrs);

            $labeltext = format_text($answer->answer, $answer->answerformat);
            if ($result && $iscorrect) {
                $labeltext .= ' ' . $OUTPUT->pix_icon('i/valid', get_string('correct'));
            }
            echo html_writer::tag('label', $labeltext, ['for' => 'a' . $answer->id, 'class' => 'form-check-label']);

            echo html_writer::end_div();
        }

    } else if ($question->qtype === 'shortanswer') {
        $value = $result->response ?? '';
        $attrs = [
            'type' => 'text',
            'name' => $paramname,
            'class' => 'form-control',
            'value' => $value,
        ];
        if ($submit) {
            $attrs['disabled'] = 'disabled';
            if ($result->correct) {
                $attrs['class'] .= ' is-valid';
            } else {
                $attrs['class'] .= ' is-invalid';
            }
        }
        echo html_writer::empty_tag('input', $attrs);

        if ($result && !$result->correct) {
            // Show correct answer.
            foreach ($question->answers as $answer) {
                if ($answer->fraction >= 0.99) {
                    echo html_writer::div(
                        get_string('correctansweris', 'qtype_shortanswer') . ': ' . s($answer->answer),
                        'text-success mt-2'
                    );
                    break;
                }
            }
        }
    }

    // Show general feedback if submitted.
    if ($result && !empty($question->generalfeedback)) {
        echo html_writer::div(
            html_writer::tag('strong', get_string('generalfeedback', 'local_casospracticos') . ': ') .
            format_text($question->generalfeedback, $question->generalfeedbackformat),
            'alert alert-info mt-3'
        );
    }

    // Show specific feedback from result.
    if ($result && !empty($result->feedback)) {
        echo html_writer::div($result->feedback, 'text-info mt-2');
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

// Submit button or retry.
echo html_writer::start_div('mt-4 mb-4');
if (!$submit) {
    echo html_writer::tag('button', get_string('submit'), [
        'type' => 'submit',
        'class' => 'btn btn-primary btn-lg',
    ]);
} else {
    $retryurl = new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid, 'shuffle' => $shuffle]);
    echo html_writer::link($retryurl, get_string('retry', 'local_casospracticos'), [
        'class' => 'btn btn-primary btn-lg',
    ]);
}

$backurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]);
echo ' ';
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary btn-lg']);

// Link to view all attempts.
$attemptsurl = new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $caseid]);
echo ' ';
echo html_writer::link($attemptsurl, get_string('viewmyattempts', 'local_casospracticos'), ['class' => 'btn btn-outline-info btn-lg']);

echo html_writer::end_div();

echo html_writer::end_tag('form');

// Show previous attempts summary.
global $DB;
if ($DB->get_manager()->table_exists('local_cp_practice_attempts')) {
    $myattempts = $DB->get_records('local_cp_practice_attempts', [
        'caseid' => $caseid,
        'userid' => $USER->id,
        'status' => 'finished'
    ], 'timefinished DESC', '*', 0, 5);

    if (!empty($myattempts)) {
        echo html_writer::tag('h4', get_string('yourrecentattempts', 'local_casospracticos'), ['class' => 'mt-4 mb-3']);

        $table = new html_table();
        $table->head = [
            get_string('attempt', 'local_casospracticos'),
            get_string('date'),
            get_string('score', 'grades'),
            get_string('actions'),
        ];
        $table->attributes['class'] = 'table table-sm';

        $attemptnum = count($myattempts);
        foreach ($myattempts as $attempt) {
            $scoreclass = $attempt->percentage >= 70 ? 'text-success' :
                         ($attempt->percentage >= 50 ? 'text-warning' : 'text-danger');
            $reviewurl = new moodle_url('/local/casospracticos/review_attempt.php', ['id' => $attempt->id]);

            $table->data[] = [
                $attemptnum--,
                userdate($attempt->timefinished, get_string('strftimedatetime', 'langconfig')),
                html_writer::tag('span', round($attempt->percentage) . '%', ['class' => $scoreclass]),
                html_writer::link($reviewurl, get_string('review', 'local_casospracticos'), ['class' => 'btn btn-sm btn-outline-primary']),
            ];
        }

        echo html_writer::table($table);
    }
}

echo html_writer::end_div(); // case-practice

echo $OUTPUT->footer();
