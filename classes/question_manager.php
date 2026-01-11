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
 * Question manager for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage questions within practical cases.
 */
class question_manager {

    /** @var string Table name for questions */
    const TABLE = 'local_cp_questions';

    /** @var string Table name for answers */
    const ANSWERS_TABLE = 'local_cp_answers';

    /** @var string Question type: multiple choice */
    const QTYPE_MULTICHOICE = 'multichoice';

    /** @var string Question type: true/false */
    const QTYPE_TRUEFALSE = 'truefalse';

    /** @var string Question type: short answer */
    const QTYPE_SHORTANSWER = 'shortanswer';

    /**
     * Get a question by ID.
     *
     * @param int $id Question ID
     * @return object|false Question object or false if not found
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Get a question with its answers.
     *
     * @param int $id Question ID
     * @return object|false Question object with answers array
     */
    public static function get_with_answers(int $id) {
        $question = self::get($id);
        if (!$question) {
            return false;
        }

        $question->answers = self::get_answers($id);
        return $question;
    }

    /**
     * Get all questions for a case.
     *
     * @param int $caseid Case ID
     * @param string $sort Sort field
     * @return array Array of question objects
     */
    public static function get_by_case(int $caseid, string $sort = 'sortorder ASC'): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['caseid' => $caseid], $sort);
    }

    /**
     * Get questions for a case with their answers.
     *
     * @param int $caseid Case ID
     * @return array Questions with answers
     */
    public static function get_by_case_with_answers(int $caseid): array {
        $questions = self::get_by_case($caseid);

        if (empty($questions)) {
            return [];
        }

        // Optimized: Load all answers in a single query instead of N+1.
        $questionids = array_keys($questions);
        $answersbyquestion = self::get_answers_for_questions($questionids);

        foreach ($questions as $question) {
            $question->answers = $answersbyquestion[$question->id] ?? [];
        }
        return $questions;
    }

    /**
     * Get random questions from a case.
     *
     * @param int $caseid Case ID
     * @param int $count Number of questions to get
     * @return array Random questions with answers
     */
    public static function get_random(int $caseid, int $count): array {
        global $DB;

        $questions = $DB->get_records(self::TABLE, ['caseid' => $caseid]);

        if (empty($questions)) {
            return [];
        }

        // Shuffle and pick random (or all if not enough).
        $keys = array_keys($questions);
        shuffle($keys);
        $selectedkeys = count($questions) <= $count ? $keys : array_slice($keys, 0, $count);

        // Optimized: Load all answers in a single query instead of N+1.
        $answersbyquestion = self::get_answers_for_questions($selectedkeys);

        $result = [];
        foreach ($selectedkeys as $key) {
            $question = $questions[$key];
            $question->answers = $answersbyquestion[$question->id] ?? [];
            $result[] = $question;
        }

        return $result;
    }

    /**
     * Create a new question.
     *
     * @param object $data Question data
     * @return int New question ID
     */
    public static function create(object $data): int {
        global $DB;

        $record = new \stdClass();
        $record->caseid = $data->caseid;
        $record->questiontext = $data->questiontext;
        $record->questiontextformat = $data->questiontextformat ?? FORMAT_HTML;
        $record->qtype = $data->qtype ?? self::QTYPE_MULTICHOICE;
        $record->defaultmark = $data->defaultmark ?? 1.0;
        $record->sortorder = $data->sortorder ?? self::get_next_sortorder($data->caseid);
        $record->generalfeedback = $data->generalfeedback ?? '';
        $record->generalfeedbackformat = $data->generalfeedbackformat ?? FORMAT_HTML;
        $record->single = $data->single ?? 1;
        $record->shuffleanswers = $data->shuffleanswers ?? 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $questionid = $DB->insert_record(self::TABLE, $record);

        // Create answers if provided.
        if (!empty($data->answers)) {
            foreach ($data->answers as $answer) {
                $answer = (object) $answer;
                $answer->questionid = $questionid;
                self::create_answer($answer);
            }
        }

        // For truefalse, create default answers if not provided.
        if ($record->qtype === self::QTYPE_TRUEFALSE && empty($data->answers)) {
            self::create_truefalse_answers($questionid, $data->correctanswer ?? true);
        }

        return $questionid;
    }

    /**
     * Update an existing question.
     *
     * @param object $data Question data with id
     * @return bool Success
     */
    public static function update(object $data): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $data->id;

        if (isset($data->questiontext)) {
            $record->questiontext = $data->questiontext;
            $record->questiontextformat = $data->questiontextformat ?? FORMAT_HTML;
        }
        if (isset($data->qtype)) {
            $record->qtype = $data->qtype;
        }
        if (isset($data->defaultmark)) {
            $record->defaultmark = $data->defaultmark;
        }
        if (isset($data->sortorder)) {
            $record->sortorder = $data->sortorder;
        }
        if (isset($data->generalfeedback)) {
            $record->generalfeedback = $data->generalfeedback;
            $record->generalfeedbackformat = $data->generalfeedbackformat ?? FORMAT_HTML;
        }
        if (isset($data->single)) {
            $record->single = $data->single;
        }
        if (isset($data->shuffleanswers)) {
            $record->shuffleanswers = $data->shuffleanswers;
        }

        $record->timemodified = time();

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Delete a question and all its answers.
     *
     * @param int $id Question ID
     * @return bool Success
     */
    public static function delete(int $id): bool {
        global $DB;

        // Delete answers first.
        $DB->delete_records(self::ANSWERS_TABLE, ['questionid' => $id]);

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Duplicate a question to another case.
     *
     * @param int $id Question ID to duplicate
     * @param int $newcaseid Target case ID
     * @return int New question ID
     */
    public static function duplicate(int $id, int $newcaseid): int {
        $question = self::get_with_answers($id);
        if (!$question) {
            throw new \moodle_exception('error:questionnotfound', 'local_casospracticos');
        }

        $newquestion = clone $question;
        unset($newquestion->id);
        $newquestion->caseid = $newcaseid;
        $newquestion->sortorder = self::get_next_sortorder($newcaseid);

        // Prepare answers for creation.
        $answers = [];
        foreach ($question->answers as $answer) {
            $newanswer = clone $answer;
            unset($newanswer->id, $newanswer->questionid);
            $answers[] = $newanswer;
        }
        $newquestion->answers = $answers;

        return self::create($newquestion);
    }

    /**
     * Move question to another position.
     *
     * @param int $id Question ID
     * @param int $newposition New sort order
     * @return bool Success
     */
    public static function reorder(int $id, int $newposition): bool {
        global $DB;

        $question = self::get($id);
        if (!$question) {
            return false;
        }

        $questions = self::get_by_case($question->caseid);
        $sortorder = 1;

        foreach ($questions as $q) {
            if ($sortorder == $newposition) {
                $DB->set_field(self::TABLE, 'sortorder', $sortorder, ['id' => $id]);
                $sortorder++;
            }

            if ($q->id != $id) {
                $DB->set_field(self::TABLE, 'sortorder', $sortorder, ['id' => $q->id]);
                $sortorder++;
            }
        }

        // If new position is at the end.
        if ($sortorder == $newposition) {
            $DB->set_field(self::TABLE, 'sortorder', $sortorder, ['id' => $id]);
        }

        return true;
    }

    // ===== Answer Management =====

    /**
     * Get answers for a question.
     *
     * @param int $questionid Question ID
     * @return array Array of answer objects
     */
    public static function get_answers(int $questionid): array {
        global $DB;
        return $DB->get_records(self::ANSWERS_TABLE, ['questionid' => $questionid], 'sortorder ASC');
    }

    /**
     * Get answers for multiple questions in a single query (batch load).
     *
     * @param array $questionids Array of question IDs
     * @return array Associative array [questionid => [answers...]]
     */
    public static function get_answers_for_questions(array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $answers = $DB->get_records_select(
            self::ANSWERS_TABLE,
            "questionid $insql",
            $params,
            'questionid, sortorder ASC'
        );

        // Group by question ID.
        $result = [];
        foreach ($questionids as $qid) {
            $result[$qid] = [];
        }
        foreach ($answers as $answer) {
            $result[$answer->questionid][] = $answer;
        }

        return $result;
    }

    /**
     * Get a single answer.
     *
     * @param int $id Answer ID
     * @return object|false Answer object
     */
    public static function get_answer(int $id) {
        global $DB;
        return $DB->get_record(self::ANSWERS_TABLE, ['id' => $id]);
    }

    /**
     * Create an answer.
     *
     * @param object $data Answer data
     * @return int New answer ID
     */
    public static function create_answer(object $data): int {
        global $DB;

        $record = new \stdClass();
        $record->questionid = $data->questionid;
        $record->answer = $data->answer;
        $record->answerformat = $data->answerformat ?? FORMAT_HTML;
        $record->fraction = $data->fraction ?? 0;
        $record->feedback = $data->feedback ?? '';
        $record->feedbackformat = $data->feedbackformat ?? FORMAT_HTML;
        $record->sortorder = $data->sortorder ?? self::get_next_answer_sortorder($data->questionid);

        return $DB->insert_record(self::ANSWERS_TABLE, $record);
    }

    /**
     * Update an answer.
     *
     * @param object $data Answer data with id
     * @return bool Success
     */
    public static function update_answer(object $data): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $data->id;

        if (isset($data->answer)) {
            $record->answer = $data->answer;
            $record->answerformat = $data->answerformat ?? FORMAT_HTML;
        }
        if (isset($data->fraction)) {
            $record->fraction = $data->fraction;
        }
        if (isset($data->feedback)) {
            $record->feedback = $data->feedback;
            $record->feedbackformat = $data->feedbackformat ?? FORMAT_HTML;
        }
        if (isset($data->sortorder)) {
            $record->sortorder = $data->sortorder;
        }

        return $DB->update_record(self::ANSWERS_TABLE, $record);
    }

    /**
     * Delete an answer.
     *
     * @param int $id Answer ID
     * @return bool Success
     */
    public static function delete_answer(int $id): bool {
        global $DB;
        return $DB->delete_records(self::ANSWERS_TABLE, ['id' => $id]);
    }

    /**
     * Create default true/false answers.
     *
     * @param int $questionid Question ID
     * @param bool $correctanswer True if "True" is correct
     */
    private static function create_truefalse_answers(int $questionid, bool $correctanswer = true): void {
        // True answer.
        $true = new \stdClass();
        $true->questionid = $questionid;
        $true->answer = get_string('true', 'qtype_truefalse');
        $true->fraction = $correctanswer ? 1.0 : 0;
        $true->sortorder = 1;
        self::create_answer($true);

        // False answer.
        $false = new \stdClass();
        $false->questionid = $questionid;
        $false->answer = get_string('false', 'qtype_truefalse');
        $false->fraction = $correctanswer ? 0 : 1.0;
        $false->sortorder = 2;
        self::create_answer($false);
    }

    /**
     * Get the next sort order for questions in a case.
     *
     * @param int $caseid Case ID
     * @return int Next sort order
     */
    private static function get_next_sortorder(int $caseid): int {
        global $DB;
        $max = $DB->get_field(self::TABLE, 'MAX(sortorder)', ['caseid' => $caseid]);
        return ($max ?? 0) + 1;
    }

    /**
     * Get the next sort order for answers in a question.
     *
     * @param int $questionid Question ID
     * @return int Next sort order
     */
    private static function get_next_answer_sortorder(int $questionid): int {
        global $DB;
        $max = $DB->get_field(self::ANSWERS_TABLE, 'MAX(sortorder)', ['questionid' => $questionid]);
        return ($max ?? 0) + 1;
    }

    /**
     * Get correct answer(s) for a question.
     *
     * @param int $questionid Question ID
     * @return array Correct answers
     */
    public static function get_correct_answers(int $questionid): array {
        global $DB;
        return $DB->get_records_select(
            self::ANSWERS_TABLE,
            'questionid = :questionid AND fraction > 0',
            ['questionid' => $questionid],
            'fraction DESC'
        );
    }

    /**
     * Validate a question has proper answers.
     *
     * @param int $questionid Question ID
     * @return array Array of error strings (empty if valid)
     */
    public static function validate_answers(int $questionid): array {
        $question = self::get($questionid);
        if (!$question) {
            return ['error:questionnotfound'];
        }

        $answers = self::get_answers($questionid);
        $errors = [];

        switch ($question->qtype) {
            case self::QTYPE_MULTICHOICE:
                if (count($answers) < 2) {
                    $errors[] = 'Multichoice questions need at least 2 answers';
                }
                $hascorrect = false;
                foreach ($answers as $answer) {
                    if ($answer->fraction > 0) {
                        $hascorrect = true;
                        break;
                    }
                }
                if (!$hascorrect) {
                    $errors[] = 'Multichoice questions need at least 1 correct answer';
                }
                break;

            case self::QTYPE_TRUEFALSE:
                if (count($answers) != 2) {
                    $errors[] = 'True/false questions must have exactly 2 answers';
                }
                break;

            case self::QTYPE_SHORTANSWER:
                if (count($answers) < 1) {
                    $errors[] = 'Short answer questions need at least 1 answer';
                }
                break;
        }

        return $errors;
    }
}
