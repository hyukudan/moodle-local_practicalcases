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
 * Quiz integration for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Class to integrate practical cases with Moodle quizzes and examsimulator.
 */
class quiz_integration {

    /** @var string Category name prefix for generated questions */
    const CATEGORY_PREFIX = 'CasoPractico_';

    /**
     * Insert a practical case into a quiz.
     *
     * @param int $caseid Case ID
     * @param int $quizid Quiz ID (quiz cm id or quiz instance id)
     * @param array $options Options: random_count, shuffle, include_statement, marks_per_question
     * @return array Result with 'success', 'message', 'questionids'
     */
    public static function insert_into_quiz(int $caseid, int $quizid, array $options = []): array {
        global $DB, $CFG;

        // Default options.
        $options = array_merge([
            'random_count' => 0,        // 0 = all questions.
            'shuffle' => false,         // Shuffle question order.
            'include_statement' => true, // Include case statement as description.
            'marks_per_question' => null, // null = use default marks.
        ], $options);

        // Load case with questions.
        $case = case_manager::get_with_questions($caseid);
        if (!$case) {
            return ['success' => false, 'message' => get_string('error:casenotfound', 'local_casospracticos')];
        }

        // Get quiz instance.
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found'];
        }

        $course = $DB->get_record('course', ['id' => $quiz->course]);
        $context = \context_course::instance($course->id);

        // Get or create question category for this case.
        $category = self::get_or_create_category($case, $context);

        // Get questions to insert.
        $questions = $case->questions;
        if (empty($questions)) {
            return ['success' => false, 'message' => get_string('noquestions', 'local_casospracticos')];
        }

        // Random selection if requested.
        if ($options['random_count'] > 0 && $options['random_count'] < count($questions)) {
            $questions = question_manager::get_random($caseid, $options['random_count']);
        }

        // Shuffle if requested.
        if ($options['shuffle']) {
            shuffle($questions);
        }

        $questionids = [];

        try {
            // Start transaction.
            $transaction = $DB->start_delegated_transaction();

            // Insert case statement as description question first.
            if ($options['include_statement']) {
                $descid = self::create_description_question($case, $category);
                $questionids[] = $descid;
                self::add_question_to_quiz($quiz, $descid, 0);
            }

            // Create and add each question.
            foreach ($questions as $cpquestion) {
                $qid = self::create_moodle_question($cpquestion, $category, $options['marks_per_question']);
                $questionids[] = $qid;

                $mark = $options['marks_per_question'] ?? $cpquestion->defaultmark;
                self::add_question_to_quiz($quiz, $qid, $mark);
            }

            $transaction->allow_commit();

            return [
                'success' => true,
                'message' => get_string('insertsuccessful', 'local_casospracticos'),
                'questionids' => $questionids,
            ];

        } catch (\Exception $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            return [
                'success' => false,
                'message' => get_string('inserterror', 'local_casospracticos', $e->getMessage()),
            ];
        }
    }

    /**
     * Insert a practical case into examsimulator.
     *
     * @param int $caseid Case ID
     * @param int $examid Examsimulator instance ID
     * @param array $options Options
     * @return array Result
     */
    public static function insert_into_examsimulator(int $caseid, int $examid, array $options = []): array {
        global $DB;

        // Default options.
        $options = array_merge([
            'random_count' => 0,
            'include_statement' => true,
        ], $options);

        // Load case with questions.
        $case = case_manager::get_with_questions($caseid);
        if (!$case) {
            return ['success' => false, 'message' => get_string('error:casenotfound', 'local_casospracticos')];
        }

        // Get examsimulator instance.
        $exam = $DB->get_record('examsimulator', ['id' => $examid]);
        if (!$exam) {
            return ['success' => false, 'message' => 'Examsimulator not found'];
        }

        $course = $DB->get_record('course', ['id' => $exam->course]);
        $context = \context_course::instance($course->id);

        // Get or create question category.
        $category = self::get_or_create_category($case, $context);

        // Get questions.
        $questions = $case->questions;
        if ($options['random_count'] > 0 && $options['random_count'] < count($questions)) {
            $questions = question_manager::get_random($caseid, $options['random_count']);
        }

        $questionids = [];

        try {
            $transaction = $DB->start_delegated_transaction();

            // Create description if needed.
            if ($options['include_statement']) {
                $descid = self::create_description_question($case, $category);
                $questionids[] = $descid;
            }

            // Create questions.
            foreach ($questions as $cpquestion) {
                $qid = self::create_moodle_question($cpquestion, $category);
                $questionids[] = $qid;
            }

            // Update examsimulator to use our category.
            // Note: This assumes examsimulator uses questioncategoryid field.
            $DB->set_field('examsimulator', 'questioncategoryid', $category->id, ['id' => $examid]);

            $transaction->allow_commit();

            return [
                'success' => true,
                'message' => get_string('insertsuccessful', 'local_casospracticos'),
                'questionids' => $questionids,
                'categoryid' => $category->id,
            ];

        } catch (\Exception $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            return [
                'success' => false,
                'message' => get_string('inserterror', 'local_casospracticos', $e->getMessage()),
            ];
        }
    }

    /**
     * Get or create a question category for a case.
     *
     * @param object $case Case object
     * @param \context $context Context
     * @return object Category record
     */
    private static function get_or_create_category(object $case, \context $context): object {
        global $DB;

        $catname = self::CATEGORY_PREFIX . $case->id . '_' . clean_param($case->name, PARAM_ALPHANUMEXT);
        $catname = substr($catname, 0, 250); // Limit length.

        // Check if category exists.
        $category = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'name' => $catname,
        ]);

        if ($category) {
            return $category;
        }

        // Get parent category (top level for this context).
        $parent = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'parent' => 0,
        ]);

        if (!$parent) {
            // Create top level category.
            $parent = new \stdClass();
            $parent->name = 'top';
            $parent->contextid = $context->id;
            $parent->parent = 0;
            $parent->sortorder = 0;
            $parent->stamp = make_unique_id_code();
            $parent->id = $DB->insert_record('question_categories', $parent);
        }

        // Create category for this case.
        $category = new \stdClass();
        $category->name = $catname;
        $category->contextid = $context->id;
        $category->parent = $parent->id;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->info = get_string('casestatement', 'local_casospracticos') . ': ' . $case->name;
        $category->infoformat = FORMAT_PLAIN;
        $category->id = $DB->insert_record('question_categories', $category);

        return $category;
    }

    /**
     * Create a description question with the case statement.
     *
     * @param object $case Case object
     * @param object $category Question category
     * @return int Question ID
     */
    private static function create_description_question(object $case, object $category): int {
        global $DB, $USER;

        $question = new \stdClass();
        $question->category = $category->id;
        $question->parent = 0;
        $question->name = substr('Caso: ' . $case->name, 0, 250);
        $question->questiontext = $case->statement;
        $question->questiontextformat = $case->statementformat;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 0;
        $question->penalty = 0;
        $question->qtype = 'description';
        $question->length = 0;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        $question->id = $DB->insert_record('question', $question);

        // Create question_bank_entry.
        self::create_question_bank_entry($question, $category);

        return $question->id;
    }

    /**
     * Create a Moodle question from a practical case question.
     *
     * @param object $cpquestion Practical case question
     * @param object $category Question category
     * @param float|null $overridemark Override default mark
     * @return int Question ID
     */
    private static function create_moodle_question(object $cpquestion, object $category, ?float $overridemark = null): int {
        global $DB, $USER;

        $mark = $overridemark ?? $cpquestion->defaultmark;

        // Base question record.
        $question = new \stdClass();
        $question->category = $category->id;
        $question->parent = 0;
        $question->name = substr(strip_tags($cpquestion->questiontext), 0, 250);
        $question->questiontext = $cpquestion->questiontext;
        $question->questiontextformat = $cpquestion->questiontextformat;
        $question->generalfeedback = $cpquestion->generalfeedback ?? '';
        $question->generalfeedbackformat = $cpquestion->generalfeedbackformat ?? FORMAT_HTML;
        $question->defaultmark = $mark;
        $question->penalty = 0.3333333;
        $question->qtype = self::map_qtype($cpquestion->qtype);
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        $question->id = $DB->insert_record('question', $question);

        // Create question_bank_entry.
        self::create_question_bank_entry($question, $category);

        // Create question type specific data.
        $answers = question_manager::get_answers($cpquestion->id);
        self::create_qtype_data($question, $cpquestion, $answers);

        return $question->id;
    }

    /**
     * Create question bank entry for Moodle 4.x+.
     *
     * @param object $question Question record
     * @param object $category Category record
     */
    private static function create_question_bank_entry(object $question, object $category): void {
        global $DB;

        // Check if question_bank_entries table exists (Moodle 4.0+).
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('question_bank_entries')) {
            return;
        }

        // Create bank entry.
        $entry = new \stdClass();
        $entry->questioncategoryid = $category->id;
        $entry->idnumber = null;
        $entry->ownerid = $question->createdby;
        $entry->id = $DB->insert_record('question_bank_entries', $entry);

        // Create version.
        if ($dbman->table_exists('question_versions')) {
            $version = new \stdClass();
            $version->questionbankentryid = $entry->id;
            $version->version = 1;
            $version->questionid = $question->id;
            $version->status = 'ready';
            $DB->insert_record('question_versions', $version);
        }
    }

    /**
     * Create question type specific data.
     *
     * @param object $question Moodle question
     * @param object $cpquestion Case practical question
     * @param array $answers Answers
     */
    private static function create_qtype_data(object $question, object $cpquestion, array $answers): void {
        global $DB;

        switch ($cpquestion->qtype) {
            case 'multichoice':
                self::create_multichoice_data($question, $cpquestion, $answers);
                break;

            case 'truefalse':
                self::create_truefalse_data($question, $answers);
                break;

            case 'shortanswer':
                self::create_shortanswer_data($question, $answers);
                break;
        }
    }

    /**
     * Create multichoice question data.
     */
    private static function create_multichoice_data(object $question, object $cpquestion, array $answers): void {
        global $DB;

        // Create answers.
        $answerids = [];
        foreach ($answers as $answer) {
            $moodleanswer = new \stdClass();
            $moodleanswer->question = $question->id;
            $moodleanswer->answer = $answer->answer;
            $moodleanswer->answerformat = $answer->answerformat;
            $moodleanswer->fraction = $answer->fraction;
            $moodleanswer->feedback = $answer->feedback ?? '';
            $moodleanswer->feedbackformat = $answer->feedbackformat ?? FORMAT_HTML;
            $answerids[] = $DB->insert_record('question_answers', $moodleanswer);
        }

        // Create multichoice options.
        $options = new \stdClass();
        $options->questionid = $question->id;
        $options->layout = 0; // Vertical.
        $options->single = $cpquestion->single ?? 1;
        $options->shuffleanswers = $cpquestion->shuffleanswers ?? 1;
        $options->correctfeedback = '';
        $options->correctfeedbackformat = FORMAT_HTML;
        $options->partiallycorrectfeedback = '';
        $options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $options->incorrectfeedback = '';
        $options->incorrectfeedbackformat = FORMAT_HTML;
        $options->answernumbering = 'abc';
        $options->shownumcorrect = 0;
        $options->showstandardinstruction = 0;

        $DB->insert_record('qtype_multichoice_options', $options);
    }

    /**
     * Create truefalse question data.
     */
    private static function create_truefalse_data(object $question, array $answers): void {
        global $DB;

        $trueanswer = null;
        $falseanswer = null;

        foreach ($answers as $answer) {
            $moodleanswer = new \stdClass();
            $moodleanswer->question = $question->id;
            $moodleanswer->answer = $answer->answer;
            $moodleanswer->answerformat = FORMAT_PLAIN;
            $moodleanswer->fraction = $answer->fraction;
            $moodleanswer->feedback = $answer->feedback ?? '';
            $moodleanswer->feedbackformat = $answer->feedbackformat ?? FORMAT_HTML;
            $answerid = $DB->insert_record('question_answers', $moodleanswer);

            // Determine if this is the true or false answer.
            $answertext = strtolower(strip_tags($answer->answer));
            if (strpos($answertext, 'true') !== false || strpos($answertext, 'verdadero') !== false) {
                $trueanswer = $answerid;
            } else {
                $falseanswer = $answerid;
            }
        }

        // Create truefalse record.
        $options = new \stdClass();
        $options->question = $question->id;
        $options->trueanswer = $trueanswer;
        $options->falseanswer = $falseanswer;
        $DB->insert_record('question_truefalse', $options);
    }

    /**
     * Create shortanswer question data.
     */
    private static function create_shortanswer_data(object $question, array $answers): void {
        global $DB;

        // Create answers.
        foreach ($answers as $answer) {
            $moodleanswer = new \stdClass();
            $moodleanswer->question = $question->id;
            $moodleanswer->answer = strip_tags($answer->answer); // Short answers are plain text.
            $moodleanswer->answerformat = FORMAT_PLAIN;
            $moodleanswer->fraction = $answer->fraction;
            $moodleanswer->feedback = $answer->feedback ?? '';
            $moodleanswer->feedbackformat = $answer->feedbackformat ?? FORMAT_HTML;
            $DB->insert_record('question_answers', $moodleanswer);
        }

        // Create shortanswer options.
        $options = new \stdClass();
        $options->questionid = $question->id;
        $options->usecase = 0; // Case insensitive.
        $DB->insert_record('qtype_shortanswer_options', $options);
    }

    /**
     * Add a question to a quiz.
     *
     * @param object $quiz Quiz record
     * @param int $questionid Question ID
     * @param float $mark Mark for this question
     */
    private static function add_question_to_quiz(object $quiz, int $questionid, float $mark): void {
        global $DB;

        // Get current max slot.
        $maxslot = $DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quiz->id]) ?? 0;
        $newslot = $maxslot + 1;

        // Get max page.
        $maxpage = $DB->get_field('quiz_slots', 'MAX(page)', ['quizid' => $quiz->id]) ?? 0;

        // Add slot.
        $slot = new \stdClass();
        $slot->quizid = $quiz->id;
        $slot->slot = $newslot;
        $slot->page = $maxpage + 1;
        $slot->requireprevious = 0;
        $slot->maxmark = $mark;

        // Moodle 4.x uses questionid directly or through question_references.
        $dbman = $DB->get_manager();
        if ($dbman->field_exists('quiz_slots', 'questionid')) {
            $slot->questionid = $questionid;
        }

        $slotid = $DB->insert_record('quiz_slots', $slot);

        // Create question_reference for Moodle 4.0+.
        if ($dbman->table_exists('question_references')) {
            $question = $DB->get_record('question', ['id' => $questionid]);
            $version = $DB->get_record('question_versions', ['questionid' => $questionid]);

            if ($version) {
                $ref = new \stdClass();
                $ref->usingcontextid = \context_module::instance(
                    $DB->get_field('course_modules', 'id', ['instance' => $quiz->id, 'module' =>
                        $DB->get_field('modules', 'id', ['name' => 'quiz'])])
                )->id;
                $ref->component = 'mod_quiz';
                $ref->questionarea = 'slot';
                $ref->itemid = $slotid;
                $ref->questionbankentryid = $version->questionbankentryid;
                $ref->version = null; // Always use latest.
                $DB->insert_record('question_references', $ref);
            }
        }

        // Update quiz sumgrades.
        $sumgrades = $DB->get_field_sql(
            "SELECT SUM(maxmark) FROM {quiz_slots} WHERE quizid = ?",
            [$quiz->id]
        );
        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quiz->id]);
    }

    /**
     * Map our question type to Moodle qtype.
     *
     * @param string $qtype Our qtype
     * @return string Moodle qtype
     */
    private static function map_qtype(string $qtype): string {
        $map = [
            'multichoice' => 'multichoice',
            'truefalse' => 'truefalse',
            'shortanswer' => 'shortanswer',
        ];
        return $map[$qtype] ?? 'multichoice';
    }

    /**
     * Get available quizzes for a course.
     *
     * @param int $courseid Course ID
     * @return array Array of quiz records
     */
    public static function get_available_quizzes(int $courseid): array {
        global $DB;
        return $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name, intro');
    }

    /**
     * Get available examsimulators for a course.
     *
     * @param int $courseid Course ID
     * @return array Array of examsimulator records
     */
    public static function get_available_examsimulators(int $courseid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('examsimulator')) {
            return [];
        }

        return $DB->get_records('examsimulator', ['course' => $courseid], 'name ASC', 'id, name, intro');
    }
}
