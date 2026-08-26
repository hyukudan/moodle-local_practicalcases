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
 * Practice scoring engine for practical cases.
 *
 * Extracted from practice.php to allow reuse in APIs, tests, and other contexts.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Stateless scoring engine for practice submissions.
 */
class practice_engine {

    /**
     * Score a full practice submission.
     *
     * @param array $questions Array of question objects (with ->answers loaded).
     * @param array $responses Raw POST data ($_POST). Keys like 'q{id}' for single
     *                         values and 'q{id}' => array for multiple-answer questions.
     *                         Matching questions use 'q{id}_{subqid}'.
     * @return array {
     *   'results'    => array  questionid => stdClass result object (same shape as before),
     *   'score'      => float  total score,
     *   'maxscore'   => float  total max,
     *   'percentage' => float|null  0-100, null while manual grading is pending
     *   'gradingstatus' => string auto|needsgrading
     * }
     */
    public static function score_submission(array $questions, array $responses): array {
        $results = [];
        $score = 0;
        $maxscore = 0;
        $hasmanual = false;

        $unsupported = question_manager::unsupported_practice_qtypes($questions);
        if ($unsupported) {
            throw new \moodle_exception('error:unsupportedpracticeqtype', 'local_casospracticos', '',
                implode(', ', $unsupported));
        }

        foreach ($questions as $question) {
            $paramname = 'q' . $question->id;

            // Manually-graded question types (essay) always auto-score 0 and are
            // graded by hand later, so they must not be counted in the auto-graded
            // maxscore denominator or they would deflate the learner's percentage.
            if ($question->qtype !== 'essay') {
                $maxscore += $question->defaultmark;
            }

            switch ($question->qtype) {
                case 'multichoice':
                    if ($question->single) {
                        $result = self::score_multichoice_single($question, $responses[$paramname] ?? 0);
                    } else {
                        $result = self::score_multichoice_multiple($question, $responses[$paramname] ?? []);
                    }
                    break;

                case 'truefalse':
                    $result = self::score_truefalse($question, $responses[$paramname] ?? null);
                    break;

                case 'shortanswer':
                    $result = self::score_shortanswer($question, $responses[$paramname] ?? '');
                    break;

                case 'essay':
                    $result = self::score_essay($question, $responses[$paramname] ?? '');
                    $hasmanual = true;
                    break;

                default:
                    // Unknown type: zero score, not correct.
                    $result = new \stdClass();
                    $result->questionid = $question->id;
                    $result->correct = false;
                    $result->feedback = '';
                    $result->selectedids = [];
                    $result->score = 0;
                    break;
            }

            $score += $result->score ?? 0;
            $results[$question->id] = $result;
        }

        $percentage = $hasmanual ? null : ($maxscore > 0 ? round(($score / $maxscore) * 100) : 0);

        return [
            'results' => $results,
            'score' => $score,
            'maxscore' => $maxscore,
            'percentage' => $percentage,
            'gradingstatus' => $hasmanual ? 'needsgrading' : 'auto',
        ];
    }

    /**
     * Score a single multichoice question (single answer, radio button).
     *
     * @param object $question Question with ->answers, ->defaultmark
     * @param mixed $response The selected answer ID (int or string-castable)
     * @return \stdClass Result object
     */
    private static function score_multichoice_single(object $question, $response): \stdClass {
        $selected = clean_param($response, PARAM_INT);

        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = $selected ? [$selected] : [];

        // Filter to only valid answer IDs belonging to this question.
        $validids = array_map('intval', array_column($question->answers, 'id'));
        $result->selectedids = array_intersect($result->selectedids, $validids);

        $questionscore = 0;
        foreach ($question->answers as $answer) {
            if (in_array((int) $answer->id, $result->selectedids, true)) {
                $questionscore += $answer->fraction * $question->defaultmark;
                if (!empty($answer->feedback)) {
                    $result->feedback .= format_text($answer->feedback, $answer->feedbackformat) . ' ';
                }
            }
        }
        $result->score = max(0, $questionscore);
        $result->correct = ($result->score >= $question->defaultmark * 0.99);

        return $result;
    }

    /**
     * Score a single multichoice question (multiple answers, checkboxes).
     *
     * @param object $question Question with ->answers, ->defaultmark
     * @param mixed $response Array of selected answer IDs
     * @return \stdClass Result object
     */
    private static function score_multichoice_multiple(object $question, $response): \stdClass {
        $selected = is_array($response) ? array_map(function($v) {
            return clean_param($v, PARAM_INT);
        }, $response) : [];

        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = $selected;

        // Filter to only valid answer IDs belonging to this question.
        $validids = array_map('intval', array_column($question->answers, 'id'));
        $result->selectedids = array_intersect($result->selectedids, $validids);

        $questionscore = 0;
        foreach ($question->answers as $answer) {
            if (in_array((int) $answer->id, $result->selectedids, true)) {
                $questionscore += $answer->fraction * $question->defaultmark;
                if (!empty($answer->feedback)) {
                    $result->feedback .= format_text($answer->feedback, $answer->feedbackformat) . ' ';
                }
            }
        }
        $result->score = max(0, $questionscore);
        $result->correct = ($result->score >= $question->defaultmark * 0.99);

        return $result;
    }

    /**
     * Score a true/false question.
     *
     * @param object $question Question with ->answers, ->defaultmark
     * @param mixed $response The selected answer ID
     * @return \stdClass Result object
     */
    private static function score_truefalse(object $question, $response): \stdClass {
        // Use null instead of -1 sentinel to represent "no answer selected".
        $selected = ($response !== null && $response !== '')
            ? clean_param($response, PARAM_INT)
            : null;

        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = ($selected !== null) ? [$selected] : [];
        $result->score = 0;

        foreach ($question->answers as $answer) {
            if ((int) $answer->id === (int) $selected) {
                $result->score = max(0, $answer->fraction * $question->defaultmark);
                $result->correct = ($answer->fraction >= 0.99);
                if (!empty($answer->feedback)) {
                    $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                }
            }
        }

        return $result;
    }

    /**
     * Score a short answer question.
     *
     * @param object $question Question with ->answers, ->defaultmark
     * @param mixed $response The text response
     * @return \stdClass Result object
     */
    private static function score_shortanswer(object $question, $response): \stdClass {
        $responsetext = clean_param($response, PARAM_TEXT);

        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = [];
        $result->response = $responsetext;
        $result->score = 0;

        foreach ($question->answers as $answer) {
            if (strcasecmp(trim($responsetext), trim($answer->answer)) === 0) {
                $result->score = max(0, $answer->fraction * $question->defaultmark);
                $result->correct = ($answer->fraction >= 0.99);
                if (!empty($answer->feedback)) {
                    $result->feedback = format_text($answer->feedback, $answer->feedbackformat);
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Score an essay question (always manual grading).
     *
     * @param object $question Question object
     * @param mixed $response The text response
     * @return \stdClass Result object
     */
    private static function score_essay(object $question, $response): \stdClass {
        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = get_string('essaymanualgrading', 'local_casospracticos');
        $result->selectedids = [];
        $result->response = $response;
        $result->score = 0;
        $result->requiresgrading = true;

        return $result;
    }

    /**
     * Score a matching question.
     *
     * @param object $question Question with ->subquestions, ->defaultmark
     * @param array $responses Full POST data (we read q{id}_{subqid} keys)
     * @param string $paramname Base param name ('q' . questionid)
     * @return \stdClass Result object
     */
    private static function score_matching(object $question, array $responses, string $paramname): \stdClass {
        $result = new \stdClass();
        $result->questionid = $question->id;
        $result->correct = false;
        $result->feedback = '';
        $result->selectedids = [];
        $result->matches = [];
        $result->score = 0;

        if (!empty($question->subquestions)) {
            foreach ($question->subquestions as $subq) {
                $matchparam = $paramname . '_' . $subq->id;
                $selectedmatch = clean_param($responses[$matchparam] ?? '', PARAM_TEXT);
                $result->matches[$subq->id] = $selectedmatch;
            }

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

        return $result;
    }

    /**
     * Get the correct answer text for display purposes.
     *
     * @param object $question Question with ->answers loaded
     * @return string The correct answer text, or empty string if none found
     */
    public static function get_correct_answer_text(object $question): string {
        if (empty($question->answers)) {
            return '';
        }

        foreach ($question->answers as $answer) {
            if ($answer->fraction >= 0.99) {
                return $answer->answer;
            }
        }

        return '';
    }
}
