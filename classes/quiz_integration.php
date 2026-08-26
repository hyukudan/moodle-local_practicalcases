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

        // Questions added to a quiz live in the quiz's module context (that is the
        // context quiz_add_quiz_question() uses for question_references). Resolve the
        // quiz cm so we can build/fetch the top question category there.
        if (!isset($quiz->cmid)) {
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
            $quiz->cmid = $cm->id;
        }
        $context = \context_module::instance($quiz->cmid);

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

        // Ensure quiz library is available (defensive: callers may not have loaded it).
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $questionids = [];

        try {
            // Start transaction.
            $transaction = $DB->start_delegated_transaction();

            // Determine the page to insert into. We group the whole case (statement
            // + its questions) onto a single new page so the case reads as a block,
            // instead of forcing one page per question. Page 0 would append using the
            // quiz's questionsperpage; we want the case kept together, so we target the
            // page after the current last one.
            $maxpage = (int) ($DB->get_field('quiz_slots', 'MAX(page)', ['quizid' => $quiz->id]) ?? 0);
            $targetpage = $maxpage + 1;

            // Insert case statement as description question first.
            if ($options['include_statement']) {
                $descid = self::create_description_question($case, $category);
                $questionids[] = $descid;
                self::add_question_to_quiz($quiz, $descid, 0, $targetpage);
            }

            // Create and add each question (all onto the same case page).
            foreach ($questions as $cpquestion) {
                $qid = self::create_moodle_question($cpquestion, $category, $options['marks_per_question']);
                $questionids[] = $qid;

                $mark = $options['marks_per_question'] ?? (float) $cpquestion->defaultmark;
                self::add_question_to_quiz($quiz, $qid, $mark, $targetpage);
            }

            // Recompute grades via the quiz API (sumgrades) and clear preview attempts,
            // instead of writing quiz.sumgrades directly.
            $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
            quiz_delete_previews($quizobj->get_quiz());
            $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

            $transaction->allow_commit();

            return [
                'success' => true,
                'message' => get_string('insertsuccessful', 'local_casospracticos'),
                'questionids' => $questionids,
            ];

        } catch (\Throwable $e) {
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

        // Questions live in the examsimulator's module context (CONTEXT_MODULE),
        // which is what question_get_top_category() requires.
        $cm = get_coursemodule_from_instance('examsimulator', $exam->id, $exam->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

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

        } catch (\Throwable $e) {
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
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

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

        // Get (or create) the top-level category for this context using the core
        // helper. This sets all NOT NULL columns (info, infoformat, ...) correctly;
        // hand-building it omitted info/infoformat and crashed the insert.
        // Note: question_get_top_category() requires a CONTEXT_MODULE context.
        $topcategory = question_get_top_category($context->id, true);
        if (!$topcategory) {
            throw new \coding_exception(
                'Cannot resolve top question category for context ' . $context->id .
                ' (expected a module context).'
            );
        }

        // Create category for this case.
        $category = new \stdClass();
        $category->name = $catname;
        $category->contextid = $context->id;
        $category->parent = $topcategory->id;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->info = get_string('casestatement', 'local_casospracticos') . ': ' . $case->name;
        $category->infoformat = FORMAT_HTML;
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
        $form = self::base_form(
            $category,
            'Caso: ' . $case->name,
            $case->statement,
            $case->statementformat,
            0,        // Description questions carry no mark.
            '',
            FORMAT_HTML,
            0         // No penalty.
        );

        return self::save_qtype_question('description', $form);
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
        $qtype = self::map_qtype($cpquestion->qtype);
        $answers = question_manager::get_answers($cpquestion->id);
        $mark = $overridemark ?? (float) $cpquestion->defaultmark;

        switch ($qtype) {
            case 'multichoice':
                $form = self::build_multichoice_form($cpquestion, $category, $answers, $mark);
                break;

            case 'truefalse':
                $form = self::build_truefalse_form($cpquestion, $category, $answers, $mark);
                break;

            case 'shortanswer':
                $form = self::build_shortanswer_form($cpquestion, $category, $answers, $mark);
                break;

            default:
                throw new \coding_exception('Unsupported qtype: ' . $qtype);
        }

        return self::save_qtype_question($qtype, $form);
    }

    /**
     * Build an editor-style array as expected by qtype editing forms.
     *
     * @param string $text HTML/text content
     * @param int $format Moodle text format constant
     * @return array Editor array (text/format/itemid)
     */
    private static function editor(string $text = '', int $format = FORMAT_HTML): array {
        return [
            'text' => $text,
            'format' => $format,
            'itemid' => 0,
        ];
    }

    /**
     * Build the common editing-form-shaped data shared by every qtype.
     *
     * This intentionally does NOT set removed/internal columns (version, stamp,
     * hidden, category as a bare id, timestamps, createdby): the qtype
     * save_question() API sets those itself. The category MUST be the Moodle
     * combo string "categoryid,contextid".
     *
     * @param object $category Question category record
     * @param string $name Proposed question name
     * @param string $questiontext Question text HTML
     * @param int $questiontextformat Text format
     * @param float $defaultmark Default mark
     * @param string $generalfeedback General feedback HTML
     * @param int $generalfeedbackformat Feedback format
     * @param float $penalty Penalty (0..1)
     * @return \stdClass Form-shaped data
     */
    private static function base_form(
        object $category,
        string $name,
        string $questiontext,
        int $questiontextformat,
        float $defaultmark,
        string $generalfeedback = '',
        int $generalfeedbackformat = FORMAT_HTML,
        float $penalty = 0.3333333
    ): \stdClass {
        $form = new \stdClass();
        $form->category = "{$category->id},{$category->contextid}";
        $cleanname = trim(strip_tags($name));
        $form->name = shorten_text($cleanname !== '' ? $cleanname : '-', 255);
        $form->questiontext = self::editor($questiontext, $questiontextformat);
        $form->generalfeedback = self::editor($generalfeedback, $generalfeedbackformat);
        $form->defaultmark = $defaultmark;
        $form->penalty = $penalty;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $form->idnumber = null;
        return $form;
    }

    /**
     * Persist a question through its qtype API, which creates the question,
     * question_bank_entries and question_versions rows for us.
     *
     * @param string $qtype Moodle qtype name
     * @param \stdClass $form Editing-form-shaped data
     * @return int The new question ID
     */
    private static function save_qtype_question(string $qtype, \stdClass $form): int {
        $question = new \stdClass();
        $question->qtype = $qtype;

        $saved = \question_bank::get_qtype($qtype)->save_question($question, $form);
        return (int) $saved->id;
    }

    /**
     * Build editing-form data for a multichoice question.
     *
     * @param object $cpquestion Practical case question
     * @param object $category Question category
     * @param array $answers Answer records
     * @param float $mark Default mark
     * @return \stdClass Form-shaped data
     */
    private static function build_multichoice_form(object $cpquestion, object $category, array $answers, float $mark): \stdClass {
        $form = self::base_form(
            $category,
            $cpquestion->questiontext,
            $cpquestion->questiontext,
            $cpquestion->questiontextformat,
            $mark,
            feedback_view_builder::compose_for_quiz($cpquestion),
            FORMAT_HTML,
            0.3333333
        );

        $form->single = !empty($cpquestion->single) ? 1 : 0;
        $form->shuffleanswers = $cpquestion->shuffleanswers ?? 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->layout = 0;

        $form->correctfeedback = self::editor('');
        $form->partiallycorrectfeedback = self::editor('');
        $form->incorrectfeedback = self::editor('');
        $form->shownumcorrect = 0;

        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];

        foreach (array_values($answers) as $i => $answer) {
            $form->answer[$i] = self::editor($answer->answer, $answer->answerformat ?? FORMAT_HTML);
            $form->fraction[$i] = (float) $answer->fraction;
            $form->feedback[$i] = self::editor($answer->feedback ?? '', $answer->feedbackformat ?? FORMAT_HTML);
        }

        return $form;
    }

    /**
     * Build editing-form data for a truefalse question.
     *
     * qtype_truefalse builds its own true/false answer rows; it expects
     * correctanswer plus feedbacktrue/feedbackfalse rather than an answer[] array.
     *
     * @param object $cpquestion Practical case question
     * @param object $category Question category
     * @param array $answers Answer records
     * @param float $mark Default mark
     * @return \stdClass Form-shaped data
     */
    private static function build_truefalse_form(object $cpquestion, object $category, array $answers, float $mark): \stdClass {
        $form = self::base_form(
            $category,
            $cpquestion->questiontext,
            $cpquestion->questiontext,
            $cpquestion->questiontextformat,
            $mark,
            feedback_view_builder::compose_for_quiz($cpquestion),
            FORMAT_HTML,
            1
        );

        $correcttrue = false;
        $truefeedback = '';
        $falsefeedback = '';

        foreach ($answers as $answer) {
            $text = strtolower(strip_tags($answer->answer));
            $istrue = str_contains($text, 'true') || str_contains($text, 'verdadero');

            if ($istrue) {
                $correcttrue = ((float) $answer->fraction) > 0;
                $truefeedback = $answer->feedback ?? '';
            } else {
                $falsefeedback = $answer->feedback ?? '';
            }
        }

        $form->correctanswer = $correcttrue ? 1 : 0;
        $form->feedbacktrue = self::editor($truefeedback);
        $form->feedbackfalse = self::editor($falsefeedback);
        $form->showstandardinstruction = 0;

        return $form;
    }

    /**
     * Build editing-form data for a shortanswer question.
     *
     * Shortanswer uses plain string answer[] values (not editor arrays).
     *
     * @param object $cpquestion Practical case question
     * @param object $category Question category
     * @param array $answers Answer records
     * @param float $mark Default mark
     * @return \stdClass Form-shaped data
     */
    private static function build_shortanswer_form(object $cpquestion, object $category, array $answers, float $mark): \stdClass {
        $form = self::base_form(
            $category,
            $cpquestion->questiontext,
            $cpquestion->questiontext,
            $cpquestion->questiontextformat,
            $mark,
            feedback_view_builder::compose_for_quiz($cpquestion),
            FORMAT_HTML,
            0.3333333
        );

        $form->usecase = 0;
        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];

        foreach (array_values($answers) as $i => $answer) {
            $form->answer[$i] = trim(strip_tags($answer->answer));
            $form->fraction[$i] = (float) $answer->fraction;
            $form->feedback[$i] = self::editor($answer->feedback ?? '', $answer->feedbackformat ?? FORMAT_HTML);
        }

        return $form;
    }

    /**
     * Add an existing question to a quiz using the core quiz API.
     *
     * Delegates to quiz_add_quiz_question(), which creates the quiz_slots row and
     * the question_references entry (with version = null = latest ready version)
     * correctly. We do NOT write quiz_slots / question_references directly.
     *
     * @param object $quiz Quiz record (must include id and course)
     * @param int $questionid Question ID
     * @param float|null $mark Maximum mark for this slot (null = qtype default)
     * @param int $page Target page (0 = append respecting questionsperpage)
     * @return void Throws on failure; no return value (quiz_add_quiz_question()
     *              returns null on the success path, so we treat no-exception as
     *              success rather than constraining the return type).
     */
    private static function add_question_to_quiz(object $quiz, int $questionid, ?float $mark = null, int $page = 0): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        quiz_add_quiz_question($questionid, $quiz, $page, $mark);
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
