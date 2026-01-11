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
 * External API for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_casospracticos\category_manager;
use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\quiz_integration;

/**
 * External API class.
 */
class api extends external_api {

    // ==================== CATEGORIES ====================

    /**
     * Parameters for get_categories.
     */
    public static function get_categories_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get all categories.
     */
    public static function get_categories() {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:view', $context);

        $categories = category_manager::get_flat_tree();
        $result = [];

        foreach ($categories as $cat) {
            $result[] = [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description ?? '',
                'parent' => $cat->parent,
                'depth' => $cat->depth,
                'casecount' => category_manager::count_cases($cat->id),
            ];
        }

        return $result;
    }

    /**
     * Returns for get_categories.
     */
    public static function get_categories_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Category ID'),
                'name' => new external_value(PARAM_TEXT, 'Category name'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'parent' => new external_value(PARAM_INT, 'Parent ID'),
                'depth' => new external_value(PARAM_INT, 'Depth level'),
                'casecount' => new external_value(PARAM_INT, 'Number of cases'),
            ])
        );
    }

    // ==================== CASES ====================

    /**
     * Parameters for get_cases.
     */
    public static function get_cases_parameters() {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Category ID (0 for all)', VALUE_DEFAULT, 0),
            'status' => new external_value(PARAM_ALPHA, 'Status filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get cases.
     */
    public static function get_cases($categoryid = 0, $status = '') {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:view', $context);

        $params = self::validate_parameters(self::get_cases_parameters(), [
            'categoryid' => $categoryid,
            'status' => $status,
        ]);

        $statusfilter = !empty($params['status']) ? $params['status'] : null;

        if ($params['categoryid'] > 0) {
            $cases = case_manager::get_with_counts($params['categoryid'], $statusfilter);
        } else {
            $cases = case_manager::get_with_counts(null, $statusfilter);
        }

        $result = [];
        foreach ($cases as $case) {
            $result[] = [
                'id' => $case->id,
                'categoryid' => $case->categoryid,
                'name' => $case->name,
                'status' => $case->status,
                'difficulty' => $case->difficulty ?? 0,
                'questioncount' => $case->questioncount ?? 0,
                'timecreated' => $case->timecreated,
                'timemodified' => $case->timemodified,
            ];
        }

        return $result;
    }

    /**
     * Returns for get_cases.
     */
    public static function get_cases_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Case ID'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                'name' => new external_value(PARAM_TEXT, 'Case name'),
                'status' => new external_value(PARAM_ALPHA, 'Status'),
                'difficulty' => new external_value(PARAM_INT, 'Difficulty'),
                'questioncount' => new external_value(PARAM_INT, 'Number of questions'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ])
        );
    }

    /**
     * Parameters for get_case.
     */
    public static function get_case_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Case ID'),
        ]);
    }

    /**
     * Get a single case with questions.
     */
    public static function get_case($id) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:view', $context);

        $params = self::validate_parameters(self::get_case_parameters(), ['id' => $id]);

        $case = case_manager::get_with_questions($params['id']);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        $questions = [];
        foreach ($case->questions as $q) {
            $answers = question_manager::get_answers($q->id);
            $answerdata = [];
            foreach ($answers as $a) {
                $answerdata[] = [
                    'id' => $a->id,
                    'answer' => $a->answer,
                    'fraction' => (float) $a->fraction,
                    'feedback' => $a->feedback ?? '',
                ];
            }

            $questions[] = [
                'id' => $q->id,
                'questiontext' => $q->questiontext,
                'qtype' => $q->qtype,
                'defaultmark' => (float) $q->defaultmark,
                'sortorder' => $q->sortorder,
                'answers' => $answerdata,
            ];
        }

        return [
            'id' => $case->id,
            'categoryid' => $case->categoryid,
            'name' => $case->name,
            'statement' => $case->statement,
            'statementformat' => $case->statementformat,
            'status' => $case->status,
            'difficulty' => $case->difficulty ?? 0,
            'tags' => case_manager::decode_tags($case->tags),
            'questions' => $questions,
            'timecreated' => $case->timecreated,
            'timemodified' => $case->timemodified,
        ];
    }

    /**
     * Returns for get_case.
     */
    public static function get_case_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Case ID'),
            'categoryid' => new external_value(PARAM_INT, 'Category ID'),
            'name' => new external_value(PARAM_TEXT, 'Case name'),
            'statement' => new external_value(PARAM_RAW, 'Statement HTML'),
            'statementformat' => new external_value(PARAM_INT, 'Statement format'),
            'status' => new external_value(PARAM_ALPHA, 'Status'),
            'difficulty' => new external_value(PARAM_INT, 'Difficulty'),
            'tags' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Tag')
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Question ID'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                    'qtype' => new external_value(PARAM_ALPHA, 'Question type'),
                    'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'answers' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Answer ID'),
                            'answer' => new external_value(PARAM_RAW, 'Answer text'),
                            'fraction' => new external_value(PARAM_FLOAT, 'Fraction'),
                            'feedback' => new external_value(PARAM_RAW, 'Feedback'),
                        ])
                    ),
                ])
            ),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
        ]);
    }

    /**
     * Parameters for create_case.
     */
    public static function create_case_parameters() {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Category ID'),
            'name' => new external_value(PARAM_TEXT, 'Case name'),
            'statement' => new external_value(PARAM_RAW, 'Statement HTML'),
            'status' => new external_value(PARAM_ALPHA, 'Status', VALUE_DEFAULT, 'draft'),
            'difficulty' => new external_value(PARAM_INT, 'Difficulty', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create a case.
     */
    public static function create_case($categoryid, $name, $statement, $status = 'draft', $difficulty = 0) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:create', $context);

        $params = self::validate_parameters(self::create_case_parameters(), [
            'categoryid' => $categoryid,
            'name' => $name,
            'statement' => $statement,
            'status' => $status,
            'difficulty' => $difficulty,
        ]);

        $data = (object) $params;
        $data->statementformat = FORMAT_HTML;

        $id = case_manager::create($data);

        return ['id' => $id, 'success' => true];
    }

    /**
     * Returns for create_case.
     */
    public static function create_case_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New case ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for update_case.
     */
    public static function update_case_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Case ID'),
            'name' => new external_value(PARAM_TEXT, 'Case name', VALUE_DEFAULT, ''),
            'statement' => new external_value(PARAM_RAW, 'Statement', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Category ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Update a case.
     */
    public static function update_case($id, $name = '', $statement = '', $status = '', $categoryid = 0) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);

        $params = self::validate_parameters(self::update_case_parameters(), [
            'id' => $id,
            'name' => $name,
            'statement' => $statement,
            'status' => $status,
            'categoryid' => $categoryid,
        ]);

        $data = new \stdClass();
        $data->id = $params['id'];

        if (!empty($params['name'])) {
            $data->name = $params['name'];
        }
        if (!empty($params['statement'])) {
            $data->statement = $params['statement'];
            $data->statementformat = FORMAT_HTML;
        }
        if (!empty($params['status'])) {
            $data->status = $params['status'];
        }
        if (!empty($params['categoryid'])) {
            $data->categoryid = $params['categoryid'];
        }

        case_manager::update($data);

        return ['success' => true];
    }

    /**
     * Returns for update_case.
     */
    public static function update_case_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for delete_case.
     */
    public static function delete_case_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Case ID'),
        ]);
    }

    /**
     * Delete a case.
     */
    public static function delete_case($id) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:delete', $context);

        $params = self::validate_parameters(self::delete_case_parameters(), ['id' => $id]);

        case_manager::delete($params['id']);

        return ['success' => true];
    }

    /**
     * Returns for delete_case.
     */
    public static function delete_case_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    // ==================== QUESTIONS ====================

    /**
     * Parameters for get_questions.
     */
    public static function get_questions_parameters() {
        return new external_function_parameters([
            'caseid' => new external_value(PARAM_INT, 'Case ID'),
        ]);
    }

    /**
     * Get questions for a case.
     */
    public static function get_questions($caseid) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:view', $context);

        $params = self::validate_parameters(self::get_questions_parameters(), ['caseid' => $caseid]);

        $questions = question_manager::get_by_case_with_answers($params['caseid']);

        $result = [];
        foreach ($questions as $q) {
            $answers = [];
            foreach ($q->answers as $a) {
                $answers[] = [
                    'id' => $a->id,
                    'answer' => $a->answer,
                    'fraction' => (float) $a->fraction,
                    'feedback' => $a->feedback ?? '',
                ];
            }

            $result[] = [
                'id' => $q->id,
                'questiontext' => $q->questiontext,
                'qtype' => $q->qtype,
                'defaultmark' => (float) $q->defaultmark,
                'sortorder' => $q->sortorder,
                'generalfeedback' => $q->generalfeedback ?? '',
                'answers' => $answers,
            ];
        }

        return $result;
    }

    /**
     * Returns for get_questions.
     */
    public static function get_questions_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Question ID'),
                'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                'qtype' => new external_value(PARAM_ALPHA, 'Question type'),
                'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                'generalfeedback' => new external_value(PARAM_RAW, 'General feedback'),
                'answers' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Answer ID'),
                        'answer' => new external_value(PARAM_RAW, 'Answer text'),
                        'fraction' => new external_value(PARAM_FLOAT, 'Fraction'),
                        'feedback' => new external_value(PARAM_RAW, 'Feedback'),
                    ])
                ),
            ])
        );
    }

    /**
     * Parameters for create_question.
     */
    public static function create_question_parameters() {
        return new external_function_parameters([
            'caseid' => new external_value(PARAM_INT, 'Case ID'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text'),
            'qtype' => new external_value(PARAM_ALPHA, 'Question type'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 1.0),
            'answers' => new external_multiple_structure(
                new external_single_structure([
                    'answer' => new external_value(PARAM_RAW, 'Answer text'),
                    'fraction' => new external_value(PARAM_FLOAT, 'Fraction'),
                    'feedback' => new external_value(PARAM_RAW, 'Feedback', VALUE_DEFAULT, ''),
                ])
            ),
        ]);
    }

    /**
     * Create a question.
     */
    public static function create_question($caseid, $questiontext, $qtype, $defaultmark, $answers) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);

        $params = self::validate_parameters(self::create_question_parameters(), [
            'caseid' => $caseid,
            'questiontext' => $questiontext,
            'qtype' => $qtype,
            'defaultmark' => $defaultmark,
            'answers' => $answers,
        ]);

        $data = (object) [
            'caseid' => $params['caseid'],
            'questiontext' => $params['questiontext'],
            'questiontextformat' => FORMAT_HTML,
            'qtype' => $params['qtype'],
            'defaultmark' => $params['defaultmark'],
            'answers' => $params['answers'],
        ];

        $id = question_manager::create($data);

        return ['id' => $id, 'success' => true];
    }

    /**
     * Returns for create_question.
     */
    public static function create_question_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New question ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for update_question.
     */
    public static function update_question_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Question ID'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text', VALUE_DEFAULT, ''),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Update a question.
     */
    public static function update_question($id, $questiontext = '', $defaultmark = 0) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);

        $params = self::validate_parameters(self::update_question_parameters(), [
            'id' => $id,
            'questiontext' => $questiontext,
            'defaultmark' => $defaultmark,
        ]);

        $data = new \stdClass();
        $data->id = $params['id'];

        if (!empty($params['questiontext'])) {
            $data->questiontext = $params['questiontext'];
            $data->questiontextformat = FORMAT_HTML;
        }
        if (!empty($params['defaultmark'])) {
            $data->defaultmark = $params['defaultmark'];
        }

        question_manager::update($data);

        return ['success' => true];
    }

    /**
     * Returns for update_question.
     */
    public static function update_question_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for delete_question.
     */
    public static function delete_question_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Question ID'),
        ]);
    }

    /**
     * Delete a question.
     */
    public static function delete_question($id) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);

        $params = self::validate_parameters(self::delete_question_parameters(), ['id' => $id]);

        question_manager::delete($params['id']);

        return ['success' => true];
    }

    /**
     * Returns for delete_question.
     */
    public static function delete_question_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for reorder_questions.
     */
    public static function reorder_questions_parameters() {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'newposition' => new external_value(PARAM_INT, 'New position'),
        ]);
    }

    /**
     * Reorder questions.
     */
    public static function reorder_questions($questionid, $newposition) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);

        $params = self::validate_parameters(self::reorder_questions_parameters(), [
            'questionid' => $questionid,
            'newposition' => $newposition,
        ]);

        question_manager::reorder($params['questionid'], $params['newposition']);

        return ['success' => true];
    }

    /**
     * Returns for reorder_questions.
     */
    public static function reorder_questions_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    // ==================== QUIZ INTEGRATION ====================

    /**
     * Parameters for insert_into_quiz.
     */
    public static function insert_into_quiz_parameters() {
        return new external_function_parameters([
            'caseid' => new external_value(PARAM_INT, 'Case ID'),
            'quizid' => new external_value(PARAM_INT, 'Quiz ID'),
            'randomcount' => new external_value(PARAM_INT, 'Random count', VALUE_DEFAULT, 0),
            'includestatement' => new external_value(PARAM_BOOL, 'Include statement', VALUE_DEFAULT, true),
            'shuffle' => new external_value(PARAM_BOOL, 'Shuffle', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Insert into quiz.
     */
    public static function insert_into_quiz($caseid, $quizid, $randomcount = 0, $includestatement = true, $shuffle = false) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:insertquiz', $context);

        $params = self::validate_parameters(self::insert_into_quiz_parameters(), [
            'caseid' => $caseid,
            'quizid' => $quizid,
            'randomcount' => $randomcount,
            'includestatement' => $includestatement,
            'shuffle' => $shuffle,
        ]);

        $options = [
            'random_count' => $params['randomcount'],
            'include_statement' => $params['includestatement'],
            'shuffle' => $params['shuffle'],
        ];

        $result = quiz_integration::insert_into_quiz($params['caseid'], $params['quizid'], $options);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
        ];
    }

    /**
     * Returns for insert_into_quiz.
     */
    public static function insert_into_quiz_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    /**
     * Parameters for get_available_quizzes.
     */
    public static function get_available_quizzes_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get available quizzes.
     */
    public static function get_available_quizzes($courseid) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:insertquiz', $context);

        $params = self::validate_parameters(self::get_available_quizzes_parameters(), [
            'courseid' => $courseid,
        ]);

        $quizzes = quiz_integration::get_available_quizzes($params['courseid']);

        $result = [];
        foreach ($quizzes as $quiz) {
            $result[] = [
                'id' => $quiz->id,
                'name' => $quiz->name,
            ];
        }

        return $result;
    }

    /**
     * Returns for get_available_quizzes.
     */
    public static function get_available_quizzes_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Quiz ID'),
                'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            ])
        );
    }
}
