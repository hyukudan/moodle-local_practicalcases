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
use local_casospracticos\bulk_manager;
use local_casospracticos\workflow_manager;
use local_casospracticos\rate_limiter;

/**
 * External API class.
 */
class api extends external_api {

    // ==================== HELPER METHODS ====================

    /**
     * Check rate limit for an operation.
     *
     * @param string $operation Operation name
     * @param string $type Operation type ('read' or 'write')
     * @throws \moodle_exception If rate limit exceeded
     */
    protected static function check_rate_limit(string $operation, string $type = 'read'): void {
        $limiter = new rate_limiter();
        $limiter->check($operation, $type);
    }

    /**
     * Check if current user can edit a specific case.
     *
     * User can edit if:
     * - They are the case creator (owner)
     * - They have the 'editall' capability
     * - They are a site admin
     *
     * @param int $caseid Case ID
     * @param \context $context The context
     * @return bool True if user can edit
     */
    protected static function can_edit_case(int $caseid, \context $context): bool {
        global $DB, $USER;

        // Site admins can always edit.
        if (is_siteadmin()) {
            return true;
        }

        // Users with editall capability can edit any case.
        if (has_capability('local/casospracticos:editall', $context)) {
            return true;
        }

        // Check if user is the owner.
        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], 'id, createdby');
        if (!$case) {
            return false;
        }

        return (int) $case->createdby === (int) $USER->id;
    }

    /**
     * Check if current user can delete a specific case.
     *
     * User can delete if:
     * - They are the case creator (owner)
     * - They have the 'deleteall' capability
     * - They are a site admin
     *
     * @param int $caseid Case ID
     * @param \context $context The context
     * @return bool True if user can delete
     */
    protected static function can_delete_case(int $caseid, \context $context): bool {
        global $DB, $USER;

        // Site admins can always delete.
        if (is_siteadmin()) {
            return true;
        }

        // Users with deleteall capability can delete any case.
        if (has_capability('local/casospracticos:deleteall', $context)) {
            return true;
        }

        // Check if user is the owner.
        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], 'id, createdby');
        if (!$case) {
            return false;
        }

        return (int) $case->createdby === (int) $USER->id;
    }

    /**
     * Get the case ID for a question.
     *
     * @param int $questionid Question ID
     * @return int|false Case ID or false if not found
     */
    protected static function get_case_id_for_question(int $questionid) {
        global $DB;
        return $DB->get_field('local_cp_questions', 'caseid', ['id' => $questionid]);
    }

    /**
     * Whether the current user may see answer-key data (fraction/feedback).
     *
     * Delegates to the single home of the rule in lib.php: entitlement to the
     * product (LOCAL_CP_ACCESS_FULL) buys the answer key. Keeping a second
     * copy of the rule here is what let the two drift apart, leaving paying
     * students unable to see any solution at all.
     *
     * @param \context $context The context
     * @return bool True if the user may see answer keys
     */
    protected static function can_view_answer_keys(\context $context): bool {
        global $CFG;
        require_once($CFG->dirroot . '/local/casospracticos/lib.php');
        return local_casospracticos_can_view_answers($context);
    }

    /**
     * Require at least $minlevel of entitlement to the case bank.
     *
     * External-function counterpart of local_casospracticos_require_view_access():
     * that one redirects to the purchase CTA on failure, which would corrupt a
     * JSON response, so this throws instead. Gating these endpoints on the
     * capability at system context locked out every paying student, since a
     * student only ever holds the role in the product course's context.
     *
     * @param int $minlevel One of LOCAL_CP_ACCESS_*.
     * @throws \moodle_exception
     */
    protected static function require_bank_access(int $minlevel): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/casospracticos/lib.php');
        if (local_casospracticos_get_view_access() < $minlevel) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }
    }

    /**
     * Run a rich-text field through format_text() so it arrives at the client
     * as cleaned, render-ready HTML.
     *
     * The client (case_preview.js) renders these fields via innerHTML, relying
     * on the cleaning performed here. We force cleaning (noclean => false) and
     * disable filters (the preview is a lightweight, unfiltered view), and treat
     * the stored content as HTML.
     *
     * @param string|null $text The raw rich-text value (may be null/empty)
     * @param \context $context The context used for cleaning/filtering
     * @return string Cleaned HTML, safe to render
     */
    protected static function format_richtext($text, \context $context): string {
        if ($text === null || $text === '') {
            return '';
        }
        return format_text($text, FORMAT_HTML, [
            'context' => $context,
            'noclean' => false,
            'filter' => false,
        ]);
    }

    /**
     * Build the answer payload for an answer record, stripping the answer key
     * (fraction / feedback) for non-privileged users.
     *
     * Rich-text fields (answer, feedback) are run through format_text() so the
     * client can safely render them as HTML.
     *
     * @param \stdClass $a Answer record
     * @param bool $includekeys Whether to include fraction/feedback
     * @param \context $context The context used to clean rich text
     * @return array Answer payload
     */
    protected static function build_answer_payload(\stdClass $a, bool $includekeys, \context $context): array {
        $payload = [
            'id' => $a->id,
            'answer' => self::format_richtext($a->answer, $context),
        ];
        if ($includekeys) {
            $payload['fraction'] = (float) $a->fraction;
            $payload['feedback'] = self::format_richtext($a->feedback ?? '', $context);
        }
        return $payload;
    }

    /**
     * Validate an incoming case status against the allowed set, and reject
     * privileged transitions (published/archived) unless the user has the
     * review capability.
     *
     * Workflow transitions (pending_review/in_review/approved/published/
     * archived) are owned by workflow_manager; this API path only allows the
     * editorial statuses draft/pending_review for normal editors.
     *
     * @param string $status Requested status
     * @param \context $context The context
     * @throws \moodle_exception If the status is invalid or not permitted
     */
    protected static function validate_status_change(string $status, \context $context): void {
        $allowed = [
            workflow_manager::STATUS_DRAFT,
            workflow_manager::STATUS_PENDING_REVIEW,
            workflow_manager::STATUS_IN_REVIEW,
            workflow_manager::STATUS_APPROVED,
            workflow_manager::STATUS_PUBLISHED,
            workflow_manager::STATUS_ARCHIVED,
        ];

        // Reject anything not in the known status set.
        if (!in_array($status, $allowed, true)) {
            throw new \moodle_exception('error:invalidstatus', 'local_casospracticos');
        }

        // Privileged statuses must go through the review workflow.
        $privileged = [
            workflow_manager::STATUS_IN_REVIEW,
            workflow_manager::STATUS_APPROVED,
            workflow_manager::STATUS_PUBLISHED,
            workflow_manager::STATUS_ARCHIVED,
        ];
        if (in_array($status, $privileged, true)
                && !has_capability('local/casospracticos:review', $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }
    }

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
        self::require_bank_access(LOCAL_CP_ACCESS_STATEMENT);
        self::check_rate_limit('get_categories', 'read');

        // Optimized: Uses single query for case counts instead of N+1.
        $statusfilter = case_manager::can_view_unpublished($context) ? null : case_manager::STATUS_PUBLISHED;
        $categories = category_manager::get_flat_tree_with_counts($statusfilter);
        $result = [];

        // Non-editorial users must not see the shape of the editorial taxonomy:
        // categories holding nothing they may open (and their descriptions).
        $showempty = case_manager::can_view_unpublished($context);

        foreach ($categories as $cat) {
            if (!$showempty && (int) $cat->casecount === 0) {
                continue;
            }
            $result[] = [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description ?? '',
                'parent' => $cat->parent,
                'depth' => $cat->depth,
                'casecount' => $cat->casecount,
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
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get cases.
     */
    public static function get_cases($categoryid = 0, $status = '') {
        $context = \context_system::instance();
        self::validate_context($context);
        self::require_bank_access(LOCAL_CP_ACCESS_STATEMENT);
        self::check_rate_limit('get_cases', 'read');

        $params = self::validate_parameters(self::get_cases_parameters(), [
            'categoryid' => $categoryid,
            'status' => $status,
        ]);

        $statusfilter = !empty($params['status']) ? $params['status'] : null;
        if (!case_manager::can_view_unpublished($context)) {
            $statusfilter = case_manager::STATUS_PUBLISHED;
        }

        $excludeblocked = !case_manager::can_view_unpublished($context);
        if ($params['categoryid'] > 0) {
            $cases = case_manager::get_with_counts($params['categoryid'], $statusfilter, $excludeblocked);
        } else {
            $cases = case_manager::get_with_counts(null, $statusfilter, $excludeblocked);
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
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Status'),
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
        self::require_bank_access(LOCAL_CP_ACCESS_FULL);
        self::check_rate_limit('get_case', 'read');

        $params = self::validate_parameters(self::get_case_parameters(), ['id' => $id]);

        $case = case_manager::get_with_questions($params['id']);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        if (!case_manager::is_visible_to_user($case, $context)) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        // Entitled viewers and editorial may receive the answer key (fraction/feedback).
        $includekeys = self::can_view_answer_keys($context);

        // Parity with case_view.php: blocked questions are pulled from circulation.
        if (!local_casospracticos_can_see_blocked_questions($context)) {
            $case->questions = question_manager::filter_practice_questions($case->questions);
        }
        $questions = [];
        foreach ($case->questions as $q) {
            $answers = question_manager::get_answers($q->id);
            $answerdata = [];
            foreach ($answers as $a) {
                $answerdata[] = self::build_answer_payload($a, $includekeys, $context);
            }

            $questions[] = [
                'id' => $q->id,
                // Cleaned HTML, render-ready: see format_richtext().
                'questiontext' => self::format_richtext($q->questiontext, $context),
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
            // Cleaned HTML, render-ready: see format_richtext().
            'statement' => self::format_richtext($case->statement, $context),
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
            'statement' => new external_value(PARAM_RAW, 'Statement, cleaned HTML (format_text)'),
            'statementformat' => new external_value(PARAM_INT, 'Statement format'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status'),
            'difficulty' => new external_value(PARAM_INT, 'Difficulty'),
            'tags' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Tag')
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Question ID'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text, cleaned HTML (format_text)'),
                    'qtype' => new external_value(PARAM_ALPHA, 'Question type'),
                    'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'answers' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Answer ID'),
                            'answer' => new external_value(PARAM_RAW, 'Answer text, cleaned HTML (format_text)'),
                            'fraction' => new external_value(PARAM_FLOAT, 'Fraction', VALUE_OPTIONAL),
                            'feedback' => new external_value(PARAM_RAW, 'Feedback, cleaned HTML (format_text)', VALUE_OPTIONAL),
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
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status', VALUE_DEFAULT, 'draft'),
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
        self::check_rate_limit('create_case', 'write');

        $params = self::validate_parameters(self::create_case_parameters(), [
            'categoryid' => $categoryid,
            'name' => $name,
            'statement' => $statement,
            'status' => $status,
            'difficulty' => $difficulty,
        ]);

        // Validate and gate the requested status: privileged statuses
        // (published/archived/etc.) require the review capability and must
        // otherwise go through the workflow endpoints.
        self::validate_status_change($params['status'], $context);

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
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status', VALUE_DEFAULT, ''),
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
        self::check_rate_limit('update_case', 'write');

        $params = self::validate_parameters(self::update_case_parameters(), [
            'id' => $id,
            'name' => $name,
            'statement' => $statement,
            'status' => $status,
            'categoryid' => $categoryid,
        ]);

        // Verify ownership or elevated permissions.
        if (!self::can_edit_case($params['id'], $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

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
            // Validate and gate the requested status: privileged statuses
            // (published/archived/etc.) require the review capability and must
            // otherwise go through the workflow endpoints.
            self::validate_status_change($params['status'], $context);
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
        self::check_rate_limit('delete_case', 'write');

        $params = self::validate_parameters(self::delete_case_parameters(), ['id' => $id]);

        // Verify ownership or elevated permissions.
        if (!self::can_delete_case($params['id'], $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

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
        self::require_bank_access(LOCAL_CP_ACCESS_FULL);
        self::check_rate_limit('get_questions', 'read');

        $params = self::validate_parameters(self::get_questions_parameters(), ['caseid' => $caseid]);

        // Enforce parent-case visibility (same as get_case): students must not
        // be able to enumerate draft/archived cases by ID.
        $case = case_manager::get($params['caseid']);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }
        if (!case_manager::is_visible_to_user($case, $context)) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        // Entitled viewers and editorial may receive the answer key (fraction/feedback).
        $includekeys = self::can_view_answer_keys($context);

        $questions = question_manager::get_by_case_with_answers($params['caseid']);
        // Parity with case_view.php: blocked questions are pulled from circulation.
        if (!local_casospracticos_can_see_blocked_questions($context)) {
            $questions = question_manager::filter_practice_questions($questions);
        }

        $result = [];
        foreach ($questions as $q) {
            $answers = [];
            foreach ($q->answers as $a) {
                $answers[] = self::build_answer_payload($a, $includekeys, $context);
            }

            $payload = [
                'id' => $q->id,
                // Cleaned HTML, render-ready: see format_richtext().
                'questiontext' => self::format_richtext($q->questiontext, $context),
                'qtype' => $q->qtype,
                'defaultmark' => (float) $q->defaultmark,
                'sortorder' => $q->sortorder,
                'answers' => $answers,
            ];
            if ($includekeys) {
                $payload['generalfeedback'] = self::format_richtext($q->generalfeedback ?? '', $context);
                $payload['reasoning'] = self::format_richtext($q->reasoning ?? '', $context);
                $payload['modelanswer'] = self::format_richtext($q->modelanswer ?? '', $context);
            }
            if (case_manager::can_view_unpublished($context)) {
                // Editorial workflow metadata, never part of a learner payload.
                $payload['feedbackstatus'] = $q->feedbackstatus ?? 'legacy';
            }
            $result[] = $payload;
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
                'questiontext' => new external_value(PARAM_RAW, 'Question text, cleaned HTML (format_text)'),
                'qtype' => new external_value(PARAM_ALPHA, 'Question type'),
                'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                'generalfeedback' => new external_value(PARAM_RAW, 'Legacy general feedback', VALUE_OPTIONAL),
                'reasoning' => new external_value(PARAM_RAW, 'Applied reasoning', VALUE_OPTIONAL),
                'modelanswer' => new external_value(PARAM_RAW, 'Student-facing model answer', VALUE_OPTIONAL),
                'feedbackstatus' => new external_value(PARAM_ALPHAEXT, 'Editorial status', VALUE_OPTIONAL),
                'answers' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Answer ID'),
                        'answer' => new external_value(PARAM_RAW, 'Answer text, cleaned HTML (format_text)'),
                        'fraction' => new external_value(PARAM_FLOAT, 'Fraction', VALUE_OPTIONAL),
                        'feedback' => new external_value(PARAM_RAW, 'Feedback, cleaned HTML (format_text)', VALUE_OPTIONAL),
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
            'generalfeedback' => new external_value(PARAM_RAW, 'Legacy general feedback', VALUE_DEFAULT, ''),
            'reasoning' => new external_value(PARAM_RAW, 'Applied reasoning', VALUE_DEFAULT, ''),
            'modelanswer' => new external_value(PARAM_RAW, 'Student-facing model answer', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create a question.
     */
    public static function create_question($caseid, $questiontext, $qtype, $defaultmark, $answers,
            $generalfeedback = '', $reasoning = '', $modelanswer = '') {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('create_question', 'write');

        $params = self::validate_parameters(self::create_question_parameters(), [
            'caseid' => $caseid,
            'questiontext' => $questiontext,
            'qtype' => $qtype,
            'defaultmark' => $defaultmark,
            'answers' => $answers,
            'generalfeedback' => $generalfeedback,
            'reasoning' => $reasoning,
            'modelanswer' => $modelanswer,
        ]);

        // Verify user can edit the parent case.
        if (!self::can_edit_case($params['caseid'], $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

        $data = (object) [
            'caseid' => $params['caseid'],
            'questiontext' => $params['questiontext'],
            'questiontextformat' => FORMAT_HTML,
            'qtype' => $params['qtype'],
            'defaultmark' => $params['defaultmark'],
            'answers' => $params['answers'],
            'generalfeedback' => $params['generalfeedback'],
            'reasoning' => $params['reasoning'],
            'modelanswer' => $params['modelanswer'],
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
            'generalfeedback' => new external_value(PARAM_RAW, 'Legacy general feedback', VALUE_DEFAULT, null, NULL_ALLOWED),
            'reasoning' => new external_value(PARAM_RAW, 'Applied reasoning', VALUE_DEFAULT, null, NULL_ALLOWED),
            'modelanswer' => new external_value(PARAM_RAW, 'Student-facing model answer', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Update a question.
     */
    public static function update_question($id, $questiontext = '', $defaultmark = 0,
            $generalfeedback = null, $reasoning = null, $modelanswer = null) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('update_question', 'write');

        $params = self::validate_parameters(self::update_question_parameters(), [
            'id' => $id,
            'questiontext' => $questiontext,
            'defaultmark' => $defaultmark,
            'generalfeedback' => $generalfeedback,
            'reasoning' => $reasoning,
            'modelanswer' => $modelanswer,
        ]);

        // Verify user can edit the parent case.
        $caseid = self::get_case_id_for_question($params['id']);
        if (!$caseid || !self::can_edit_case($caseid, $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

        $data = new \stdClass();
        $data->id = $params['id'];

        if (!empty($params['questiontext'])) {
            $data->questiontext = $params['questiontext'];
            $data->questiontextformat = FORMAT_HTML;
        }
        if (!empty($params['defaultmark'])) {
            $data->defaultmark = $params['defaultmark'];
        }
        foreach (['generalfeedback', 'reasoning', 'modelanswer'] as $field) {
            if ($params[$field] !== null) {
                $data->{$field} = $params[$field];
                $data->{$field . 'format'} = FORMAT_HTML;
            }
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
        self::check_rate_limit('delete_question', 'write');

        $params = self::validate_parameters(self::delete_question_parameters(), ['id' => $id]);

        // Verify user can edit the parent case.
        $caseid = self::get_case_id_for_question($params['id']);
        if (!$caseid || !self::can_edit_case($caseid, $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

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
        self::check_rate_limit('reorder_questions', 'write');

        $params = self::validate_parameters(self::reorder_questions_parameters(), [
            'questionid' => $questionid,
            'newposition' => $newposition,
        ]);

        // Verify user can edit the parent case.
        $caseid = self::get_case_id_for_question($params['questionid']);
        if (!$caseid || !self::can_edit_case($caseid, $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

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
        self::check_rate_limit('insert_into_quiz', 'write');

        $params = self::validate_parameters(self::insert_into_quiz_parameters(), [
            'caseid' => $caseid,
            'quizid' => $quizid,
            'randomcount' => $randomcount,
            'includestatement' => $includestatement,
            'shuffle' => $shuffle,
        ]);

        // Validate the target quiz's course/module context and require quiz
        // management capability THERE before mutating the quiz. The system-level
        // :insertquiz capability alone is not enough to mutate an arbitrary quiz.
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], 'id, course', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $quizcontext = \context_module::instance($cm->id);
        self::validate_context($quizcontext);
        require_capability('mod/quiz:manage', $quizcontext);

        // Ensure the case is visible/usable to this user.
        $case = case_manager::get($params['caseid']);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }
        if (!case_manager::is_visible_to_user($case, $context)) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

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
        self::check_rate_limit('get_available_quizzes', 'read');

        $params = self::validate_parameters(self::get_available_quizzes_parameters(), [
            'courseid' => $courseid,
        ]);

        // Validate the course context and require course access + quiz
        // management capability there, so a user cannot enumerate quizzes in
        // courses they cannot access.
        global $DB;
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        require_login($course, false);
        self::validate_context($coursecontext);
        require_capability('mod/quiz:manage', $coursecontext);

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

    // ==================== BULK OPERATIONS ====================

    /**
     * Parameters for bulk_delete.
     */
    public static function bulk_delete_parameters() {
        return new external_function_parameters([
            'caseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Case ID')
            ),
        ]);
    }

    /**
     * Bulk delete cases.
     */
    public static function bulk_delete($caseids) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:delete', $context);
        self::check_rate_limit('bulk_delete', 'write');

        $params = self::validate_parameters(self::bulk_delete_parameters(), [
            'caseids' => $caseids,
        ]);

        // Filter to only cases user can delete.
        $allowedcases = [];
        $deniedcases = [];
        foreach ($params['caseids'] as $caseid) {
            if (self::can_delete_case($caseid, $context)) {
                $allowedcases[] = $caseid;
            } else {
                $deniedcases[] = $caseid;
            }
        }

        $result = ['deleted' => [], 'failed' => $deniedcases];

        if (!empty($allowedcases)) {
            $deleteresult = bulk_manager::delete_cases($allowedcases);
            $result['deleted'] = $deleteresult['deleted'];
            $result['failed'] = array_merge($result['failed'], $deleteresult['failed']);
        }

        return [
            'success' => empty($result['failed']),
            'deleted' => $result['deleted'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Returns for bulk_delete.
     */
    public static function bulk_delete_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'deleted' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Deleted case ID')
            ),
            'failed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Failed case ID')
            ),
        ]);
    }

    /**
     * Parameters for bulk_publish.
     */
    public static function bulk_publish_parameters() {
        return new external_function_parameters([
            'caseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Case ID')
            ),
        ]);
    }

    /**
     * Bulk publish cases.
     */
    public static function bulk_publish($caseids) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('bulk_publish', 'write');

        $params = self::validate_parameters(self::bulk_publish_parameters(), [
            'caseids' => $caseids,
        ]);

        // Filter to only cases user can edit.
        $allowedcases = [];
        $deniedcases = [];
        foreach ($params['caseids'] as $caseid) {
            if (self::can_edit_case($caseid, $context)) {
                $allowedcases[] = $caseid;
            } else {
                $deniedcases[] = $caseid;
            }
        }

        $result = ['published' => [], 'failed' => $deniedcases];

        if (!empty($allowedcases)) {
            $publishresult = bulk_manager::publish_cases($allowedcases);
            $result['published'] = $publishresult['published'];
            $result['failed'] = array_merge($result['failed'], $publishresult['failed']);
        }

        return [
            'success' => empty($result['failed']),
            'published' => $result['published'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Returns for bulk_publish.
     */
    public static function bulk_publish_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'published' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Published case ID')
            ),
            'failed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Failed case ID')
            ),
        ]);
    }

    /**
     * Parameters for bulk_archive.
     */
    public static function bulk_archive_parameters() {
        return new external_function_parameters([
            'caseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Case ID')
            ),
        ]);
    }

    /**
     * Bulk archive cases.
     */
    public static function bulk_archive($caseids) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('bulk_archive', 'write');

        $params = self::validate_parameters(self::bulk_archive_parameters(), [
            'caseids' => $caseids,
        ]);

        // Filter to only cases user can edit.
        $allowedcases = [];
        $deniedcases = [];
        foreach ($params['caseids'] as $caseid) {
            if (self::can_edit_case($caseid, $context)) {
                $allowedcases[] = $caseid;
            } else {
                $deniedcases[] = $caseid;
            }
        }

        $result = ['archived' => [], 'failed' => $deniedcases];

        if (!empty($allowedcases)) {
            $archiveresult = bulk_manager::archive_cases($allowedcases);
            $result['archived'] = $archiveresult['archived'];
            $result['failed'] = array_merge($result['failed'], $archiveresult['failed']);
        }

        return [
            'success' => empty($result['failed']),
            'archived' => $result['archived'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Returns for bulk_archive.
     */
    public static function bulk_archive_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'archived' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Archived case ID')
            ),
            'failed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Failed case ID')
            ),
        ]);
    }

    /**
     * Parameters for bulk_move.
     */
    public static function bulk_move_parameters() {
        return new external_function_parameters([
            'caseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Case ID')
            ),
            'categoryid' => new external_value(PARAM_INT, 'Target category ID'),
        ]);
    }

    /**
     * Bulk move cases to category.
     */
    public static function bulk_move($caseids, $categoryid) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('bulk_move', 'write');

        $params = self::validate_parameters(self::bulk_move_parameters(), [
            'caseids' => $caseids,
            'categoryid' => $categoryid,
        ]);

        // Filter to only cases user can edit.
        $allowedcases = [];
        $deniedcases = [];
        foreach ($params['caseids'] as $caseid) {
            if (self::can_edit_case($caseid, $context)) {
                $allowedcases[] = $caseid;
            } else {
                $deniedcases[] = $caseid;
            }
        }

        $result = ['moved' => [], 'failed' => $deniedcases];

        if (!empty($allowedcases)) {
            $moveresult = bulk_manager::move_cases($allowedcases, $params['categoryid']);
            $result['moved'] = $moveresult['moved'];
            $result['failed'] = array_merge($result['failed'], $moveresult['failed']);
        }

        return [
            'success' => empty($result['failed']),
            'moved' => $result['moved'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Returns for bulk_move.
     */
    public static function bulk_move_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'moved' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Moved case ID')
            ),
            'failed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Failed case ID')
            ),
        ]);
    }

    // ==================== WORKFLOW ====================

    /**
     * Parameters for submit_for_review.
     */
    public static function submit_for_review_parameters() {
        return new external_function_parameters([
            'caseid' => new external_value(PARAM_INT, 'Case ID'),
        ]);
    }

    /**
     * Submit case for review.
     */
    public static function submit_for_review($caseid) {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:edit', $context);
        self::check_rate_limit('submit_for_review', 'write');

        $params = self::validate_parameters(self::submit_for_review_parameters(), [
            'caseid' => $caseid,
        ]);

        // Verify user can edit this case (owner or elevated permissions).
        if (!self::can_edit_case($params['caseid'], $context)) {
            throw new \moodle_exception('error:nopermission', 'local_casospracticos');
        }

        $success = workflow_manager::submit_for_review($params['caseid']);

        return ['success' => $success];
    }

    /**
     * Returns for submit_for_review.
     */
    public static function submit_for_review_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }

    /**
     * Parameters for get_pending_reviews.
     */
    public static function get_pending_reviews_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get pending reviews for current user.
     */
    public static function get_pending_reviews() {
        global $USER;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/casospracticos:review', $context);
        self::check_rate_limit('get_pending_reviews', 'read');

        $reviews = workflow_manager::get_pending_reviews($USER->id);

        $result = [];
        foreach ($reviews as $review) {
            $result[] = [
                'id' => $review->id,
                'caseid' => $review->caseid,
                'casename' => $review->casename,
                'status' => $review->status,
                'timecreated' => $review->timecreated,
            ];
        }

        return $result;
    }

    /**
     * Returns for get_pending_reviews.
     */
    public static function get_pending_reviews_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Review ID'),
                'caseid' => new external_value(PARAM_INT, 'Case ID'),
                'casename' => new external_value(PARAM_TEXT, 'Case name'),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Status'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
            ])
        );
    }

    // ==================== PRACTICE AUTO-SAVE ====================

    /**
     * Parameters for save_practice_responses.
     */
    public static function save_practice_responses_parameters() {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Timed attempt ID'),
            'responses' => new external_value(PARAM_RAW, 'JSON-encoded responses'),
        ]);
    }

    /**
     * Save practice responses for auto-save functionality.
     */
    public static function save_practice_responses($attemptid, $responses) {
        global $USER, $CFG;

        $context = \context_system::instance();
        self::validate_context($context);
        // Entitlement, not system capability: this is the autosave of a student's
        // own timed practice, loaded from practice_timed.php. Gating it on the
        // capability at system context made it fail for every student.
        self::require_bank_access(LOCAL_CP_ACCESS_FULL);
        self::check_rate_limit('save_practice_responses', 'write');

        $params = self::validate_parameters(self::save_practice_responses_parameters(), [
            'attemptid' => $attemptid,
            'responses' => $responses,
        ]);

        // Decode responses JSON.
        $responsesdata = json_decode($params['responses'], true);
        if (!is_array($responsesdata)) {
            throw new \moodle_exception('error:invaliddata', 'local_casospracticos');
        }

        // Save responses (manager verifies ownership and attempt status).
        $success = \local_casospracticos\timed_attempt_manager::save_responses(
            $params['attemptid'],
            $USER->id,
            $responsesdata
        );

        // Get time left for client sync.
        $timeleft = \local_casospracticos\timed_attempt_manager::get_time_left($params['attemptid']);

        return [
            'success' => $success,
            'timeleft' => $timeleft,
            'servertime' => time(),
        ];
    }

    /**
     * Returns for save_practice_responses.
     */
    public static function save_practice_responses_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'timeleft' => new external_value(PARAM_INT, 'Time left in seconds'),
            'servertime' => new external_value(PARAM_INT, 'Server timestamp for sync'),
        ]);
    }
}
