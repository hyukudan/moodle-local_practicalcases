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
 * Case manager for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage practical cases.
 */
class case_manager {

    /** @var string Table name for cases */
    const TABLE = 'local_cp_cases';

    /** @var string Status: draft */
    const STATUS_DRAFT = 'draft';

    /** @var string Status: published */
    const STATUS_PUBLISHED = 'published';

    /** @var string Status: archived */
    const STATUS_ARCHIVED = 'archived';

    /**
     * Whether the current user can access unpublished cases.
     *
     * @param \context|null $context Context to evaluate capabilities in
     * @return bool
     */
    public static function can_view_unpublished(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/casospracticos:edit', $context);
    }

    /**
     * Whether a case is visible to the current user.
     *
     * Published cases are visible to all viewers. Draft/archived cases are
     * limited to editorial users.
     *
     * @param \stdClass $case Case record
     * @param \context|null $context Context to evaluate capabilities in
     * @return bool
     */
    public static function is_visible_to_user(\stdClass $case, ?\context $context = null): bool {
        if (($case->status ?? null) === self::STATUS_PUBLISHED) {
            return true;
        }

        return self::can_view_unpublished($context);
    }

    /**
     * Get a case by ID.
     *
     * @param int $id Case ID
     * @return \stdClass|false Case object or false if not found
     */
    public static function get(int $id): \stdClass|false {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Get a case with its questions.
     *
     * @param int $id Case ID
     * @return \stdClass|false Case object with questions array, or false
     */
    public static function get_with_questions(int $id): \stdClass|false {
        $case = self::get($id);
        if (!$case) {
            return false;
        }

        $case->questions = question_manager::get_by_case($id);
        return $case;
    }

    /**
     * Get cases by category.
     *
     * @param int $categoryid Category ID
     * @param string|null $status Filter by status (null for all)
     * @param string $sort Sort field
     * @return array Array of case objects
     */
    public static function get_by_category(int $categoryid, string $status = null, string $sort = 'name ASC'): array {
        global $DB;

        $params = ['categoryid' => $categoryid];
        if ($status !== null) {
            $params['status'] = $status;
        }

        // Safety limit: a category shouldn't have more than 5000 cases.
        return $DB->get_records(self::TABLE, $params, $sort, '*', 0, 5000);
    }

    /**
     * Get all cases.
     *
     * @param string|null $status Filter by status
     * @param string $sort Sort field
     * @param int $limitfrom Start from
     * @param int $limitnum Number of records
     * @return array Array of case objects
     */
    public static function get_all(string $status = null, string $sort = 'name ASC', int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        $params = [];
        $where = '';

        if ($status !== null) {
            $where = 'status = :status';
            $params['status'] = $status;
        }

        if ($where) {
            return $DB->get_records_select(self::TABLE, $where, $params, $sort, '*', $limitfrom, $limitnum);
        }

        return $DB->get_records(self::TABLE, [], $sort, '*', $limitfrom, $limitnum);
    }

    /**
     * Search cases with pagination.
     *
     * @param string $search Search term
     * @param int|null $categoryid Category filter
     * @param string|null $status Status filter
     * @param int $page Page number (0-based)
     * @param int $perpage Items per page
     * @return array Array with 'cases', 'total', 'page', 'perpage'
     */
    public static function search(string $search, int $categoryid = null, string $status = null,
                                  int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = [];
        $conditions = [];

        if (!empty($search)) {
            $search = '%' . $DB->sql_like_escape($search) . '%';
            $conditions[] = '(' . $DB->sql_like('name', ':search1', false) . ' OR ' .
                           $DB->sql_like('statement', ':search2', false) . ')';
            $params['search1'] = $search;
            $params['search2'] = $search;
        }

        if ($categoryid !== null) {
            $conditions[] = 'categoryid = :categoryid';
            $params['categoryid'] = $categoryid;
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = implode(' AND ', $conditions);
        if (empty($where)) {
            $where = '1=1';
        }

        // Count total matching records.
        $total = $DB->count_records_select(self::TABLE, $where, $params);

        // Deferred join: Phase 1 — get only IDs for the current page.
        $ids = $DB->get_records_select(self::TABLE, $where, $params, 'name ASC', 'id', $page * $perpage, $perpage);

        if (empty($ids)) {
            return ['cases' => [], 'total' => $total, 'page' => $page, 'perpage' => $perpage];
        }

        // Phase 2: Full data for just those IDs.
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED);
        $cases = $DB->get_records_select(self::TABLE, "id $insql", $inparams, 'name ASC');

        return [
            'cases' => array_values($cases),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    /**
     * Create a new case.
     *
     * @param object $data Case data
     * @return int New case ID
     */
    public static function create(object $data): int {
        global $DB, $USER;

        $record = new \stdClass();
        $record->categoryid = $data->categoryid;
        $record->name = trim($data->name);
        $record->statement = $data->statement;
        $record->statementformat = $data->statementformat ?? FORMAT_HTML;
        $record->status = self::validate_status($data->status ?? self::STATUS_DRAFT);
        $record->difficulty = $data->difficulty ?? null;
        $record->tags = self::encode_tags($data->tags ?? []);
        $record->timecreated = time();
        $record->timemodified = time();
        $record->createdby = $data->createdby ?? $USER->id;

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing case.
     *
     * @param object $data Case data with id
     * @return bool Success
     */
    public static function update(object $data): bool {
        global $DB;

        $existing = self::get((int) $data->id);
        if (!$existing) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        $record = new \stdClass();
        $record->id = $data->id;

        if (isset($data->categoryid)) {
            $record->categoryid = $data->categoryid;
        }
        if (isset($data->name)) {
            $record->name = trim($data->name);
        }
        if (isset($data->statement)) {
            $record->statement = $data->statement;
            $record->statementformat = $data->statementformat ?? FORMAT_HTML;
        }
        if (isset($data->status)) {
            $record->status = self::validate_status($data->status);
        }
        if (array_key_exists('difficulty', (array) $data)) {
            $record->difficulty = $data->difficulty;
        }
        if (isset($data->tags)) {
            $record->tags = self::encode_tags($data->tags);
        }

        $record->timemodified = time();
        $statementchanged = isset($record->statement)
            && ((string) $record->statement !== (string) $existing->statement
                || (int) $record->statementformat !== (int) $existing->statementformat);

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $DB->update_record(self::TABLE, $record);
            if ($statementchanged) {
                $DB->execute("UPDATE {local_cp_questions}
                                 SET feedbackstatus = :needsreview,
                                     feedbackverifiedat = NULL,
                                     timemodified = :timemodified
                               WHERE caseid = :caseid
                                 AND feedbackstatus = :verified", [
                    'needsreview' => 'needs_review',
                    'timemodified' => time(),
                    'caseid' => (int) $record->id,
                    'verified' => 'verified',
                ]);
            }
            $transaction->allow_commit();
            return $result;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Delete a case and all its questions and attachments.
     *
     * @param int $id Case ID
     * @return bool Success
     * @throws \dml_exception If a database error occurs
     */
    public static function delete(int $id): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            // Delete all questions (and their answers).
            $questions = question_manager::get_by_case($id);
            foreach ($questions as $question) {
                question_manager::delete($question->id);
            }

            // Delete all attachments.
            self::delete_attachments($id);

            $result = $DB->delete_records(self::TABLE, ['id' => $id]);

            $transaction->allow_commit();
            return $result;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Duplicate a case with all its questions.
     *
     * @param int $id Case ID to duplicate
     * @param int|null $newcategoryid New category (null to keep same)
     * @return int New case ID
     * @throws \moodle_exception If the case is not found
     * @throws \dml_exception If a database error occurs
     */
    public static function duplicate(int $id, int $newcategoryid = null): int {
        global $DB;

        $case = self::get_with_questions($id);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

        $transaction = $DB->start_delegated_transaction();

        try {
            // Create new case.
            $newcase = clone $case;
            unset($newcase->id, $newcase->questions);
            $newcase->name = get_string('copyof', 'moodle', $case->name);
            $newcase->status = self::STATUS_DRAFT;
            if ($newcategoryid !== null) {
                $newcase->categoryid = $newcategoryid;
            }

            $newid = self::create($newcase);

            // Duplicate questions.
            foreach ($case->questions as $question) {
                question_manager::duplicate($question->id, $newid);
            }

            $transaction->allow_commit();
            return $newid;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Validate a status value against the allowed workflow statuses.
     *
     * @param string $status Candidate status
     * @return string The validated status
     * @throws \moodle_exception If the status is not a recognised workflow status
     */
    private static function validate_status(string $status): string {
        $allowed = array_keys(workflow_manager::get_all_statuses());
        if (!in_array($status, $allowed, true)) {
            throw new \moodle_exception('error:invalidstatus', 'local_casospracticos', '', $status);
        }
        return $status;
    }

    /**
     * Change case status.
     *
     * @param int $id Case ID
     * @param string $status New status
     * @return bool Success
     */
    public static function set_status(int $id, string $status): bool {
        $data = new \stdClass();
        $data->id = $id;
        $data->status = $status;
        return self::update($data);
    }

    /**
     * Move case to another category.
     *
     * @param int $id Case ID
     * @param int $categoryid New category ID
     * @return bool Success
     */
    public static function move(int $id, int $categoryid): bool {
        $data = new \stdClass();
        $data->id = $id;
        $data->categoryid = $categoryid;
        return self::update($data);
    }

    /**
     * Count questions in a case.
     *
     * @param int $id Case ID
     * @return int Number of questions
     */
    public static function count_questions(int $id): int {
        global $DB;
        return $DB->count_records('local_cp_questions', ['caseid' => $id]);
    }

    /**
     * Get total marks for a case.
     *
     * @param int $id Case ID
     * @return float Total marks
     */
    public static function get_total_marks(int $id): float {
        global $DB;

        $sql = "SELECT COALESCE(SUM(defaultmark), 0) as total
                FROM {local_cp_questions}
                WHERE caseid = :caseid";

        $result = $DB->get_record_sql($sql, ['caseid' => $id]);

        return (float) $result->total;
    }

    /**
     * Encode tags array to JSON.
     *
     * @param array|string $tags Tags
     * @return string JSON string
     */
    private static function encode_tags($tags): string {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }
        return json_encode(array_values($tags));
    }

    /**
     * Decode tags from JSON.
     *
     * @param string $tags JSON string
     * @return array Tags array
     */
    public static function decode_tags(string $tags): array {
        if (empty($tags)) {
            return [];
        }
        return json_decode($tags, true) ?? [];
    }

    /**
     * Get case with category info.
     *
     * @param int $id Case ID
     * @return object|false Case with category data
     */
    public static function get_with_category(int $id) {
        global $DB;

        $sql = "SELECT c.*, cat.name as categoryname
                FROM {" . self::TABLE . "} c
                LEFT JOIN {local_cp_categories} cat ON cat.id = c.categoryid
                WHERE c.id = :id";

        return $DB->get_record_sql($sql, ['id' => $id]);
    }

    /**
     * Get cases with question count.
     *
     * @param int|null $categoryid Category filter
     * @param string|null $status Status filter
     * @return array Cases with questioncount field
     */
    public static function get_with_counts(int $categoryid = null, string $status = null): array {
        global $DB;

        $params = [];
        $where = '1=1';

        if ($categoryid !== null) {
            $where .= ' AND c.categoryid = :categoryid';
            $params['categoryid'] = $categoryid;
        }

        if ($status !== null) {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }

        // Use a subquery for question counts to avoid GROUP BY compatibility issues with PostgreSQL.
        // PostgreSQL requires all non-aggregated SELECT columns to appear in GROUP BY.
        $sql = "SELECT c.*, COALESCE(qc.questioncount, 0) AS questioncount
                FROM {" . self::TABLE . "} c
                LEFT JOIN (
                    SELECT caseid, COUNT(*) AS questioncount
                    FROM {local_cp_questions}
                    GROUP BY caseid
                ) qc ON qc.caseid = c.id
                WHERE {$where}
                ORDER BY c.name ASC";

        return $DB->get_records_sql($sql, $params, 0, 5000);
    }

    /**
     * Get attachments for a case.
     *
     * @param int $caseid Case ID
     * @return array Array of file objects with download URLs
     */
    public static function get_attachments(int $caseid): array {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_casospracticos',
            'case_attachments',
            $caseid,
            'filename',
            false
        );

        $attachments = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $fileinfo = local_casospracticos_get_file_icon($filename);

            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'local_casospracticos',
                'case_attachments',
                $caseid,
                $file->get_filepath(),
                $filename,
                true // Force download.
            );

            // SVG must never be served inline (XSS vector): force download for it.
            $forceinlinedownload = ($file->get_mimetype() === 'image/svg+xml');
            $viewurl = \moodle_url::make_pluginfile_url(
                $context->id,
                'local_casospracticos',
                'case_attachments',
                $caseid,
                $file->get_filepath(),
                $filename,
                $forceinlinedownload // Force download for SVG, view inline otherwise.
            );

            $attachments[] = (object)[
                'id' => $file->get_id(),
                'filename' => $filename,
                'filepath' => $file->get_filepath(),
                'filesize' => $file->get_filesize(),
                'filesizeformatted' => display_size($file->get_filesize()),
                'mimetype' => $file->get_mimetype(),
                'timecreated' => $file->get_timecreated(),
                'timemodified' => $file->get_timemodified(),
                'downloadurl' => $url->out(false),
                'viewurl' => $viewurl->out(false),
                'icon' => $fileinfo['icon'],
                'type' => $fileinfo['type'],
                'isimage' => strpos($file->get_mimetype(), 'image/') === 0,
                'isembeddable' => self::is_embeddable($file->get_mimetype()),
            ];
        }

        return $attachments;
    }

    /**
     * Check if a file type can be embedded for preview.
     *
     * @param string $mimetype The file MIME type.
     * @return bool True if embeddable.
     */
    private static function is_embeddable(string $mimetype): bool {
        // SVG is deliberately excluded: user-controlled SVG can carry scripts and
        // is an XSS vector when rendered inline. SVG attachments are forced to download.
        $embeddable = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
        ];
        return in_array($mimetype, $embeddable);
    }

    /**
     * Save attachments for a case from file manager draft area.
     *
     * @param int $caseid Case ID
     * @param int $draftitemid Draft item ID from form submission
     * @return void
     */
    public static function save_attachments(int $caseid, int $draftitemid): void {
        $context = \context_system::instance();

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'local_casospracticos',
            'case_attachments',
            $caseid,
            local_casospracticos_get_attachment_options()
        );
    }

    /**
     * Delete all attachments for a case.
     *
     * @param int $caseid Case ID
     * @return void
     */
    public static function delete_attachments(int $caseid): void {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $fs->delete_area_files(
            $context->id,
            'local_casospracticos',
            'case_attachments',
            $caseid
        );
    }

    /**
     * Count attachments for a case.
     *
     * @param int $caseid Case ID
     * @return int Number of attachments
     */
    public static function count_attachments(int $caseid): int {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_casospracticos',
            'case_attachments',
            $caseid,
            'filename',
            false
        );

        return count($files);
    }

    /**
     * Get the raw deliverable definition row for a case (no enabled filter).
     *
     * Unlike local_casospracticos_get_case_deliverable() (which only returns
     * enabled rows and is used on the student-facing path), this returns the
     * row regardless of the enabled flag so the deliverable editor can load and
     * re-save a currently-disabled configuration.
     *
     * @param int $caseid Case ID
     * @return \stdClass|false The deliverable row, or false if none exists.
     */
    public static function get_deliverable_raw(int $caseid) {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_case_deliverable')) {
            return false;
        }

        return $DB->get_record('local_cp_case_deliverable', ['caseid' => (int) $caseid]);
    }

    /**
     * Create or update the deliverable definition for a case (upsert by caseid).
     *
     * The caseid column carries a unique key, so at most one row exists per
     * case. Sets timecreated on insert and timemodified on every write. The
     * correctionmode is validated against the allowed set.
     *
     * Decision C: when an existing row transitions manual -> auto AND is enabled,
     * any attempts that submitted a file but were never auto-graded (because the
     * case was in manual mode) are re-queued for the Python autograder. Queueing
     * is de-duplicated so toggling the mode repeatedly does not pile up tasks.
     *
     * @param \stdClass $record Deliverable data. Must contain caseid.
     * @return int The deliverable row id.
     * @throws \moodle_exception If correctionmode is invalid.
     */
    public static function save_deliverable(\stdClass $record): int {
        global $DB;

        $mode = $record->correctionmode ?? 'auto';
        if (!in_array($mode, ['auto', 'manual'], true)) {
            throw new \moodle_exception('error:invalidcorrectionmode', 'local_casospracticos', '', $mode);
        }
        $record->correctionmode = $mode;

        $now = time();
        $caseid = (int) $record->caseid;
        $existing = $DB->get_record('local_cp_case_deliverable', ['caseid' => $caseid]);

        if ($existing) {
            $record->id = $existing->id;
            $record->timemodified = $now;
            // Never rewrite timecreated on update.
            unset($record->timecreated);
            $DB->update_record('local_cp_case_deliverable', $record);
            $id = (int) $existing->id;

            // Decision C: manual -> auto (enabled) re-queues stuck deliverables.
            $wasmanual = (($existing->correctionmode ?? 'auto') === 'manual');
            $nowauto = ($mode === 'auto');
            $enabled = !empty($record->enabled);
            if ($wasmanual && $nowauto && $enabled) {
                self::requeue_stuck_deliverables($caseid);
            }
        } else {
            $record->timecreated = $now;
            $record->timemodified = $now;
            $id = (int) $DB->insert_record('local_cp_case_deliverable', $record);
        }

        return $id;
    }

    /**
     * Re-queue the auto-grader for attempts stuck without an auto grade.
     *
     * Targets mod_casospracticos attempts of the given case that uploaded a file
     * but were never machine-graded (manual mode) and have no manual grade yet.
     * De-duplicated via queue_adhoc_task(..., true). Guarded so the local plugin
     * degrades gracefully if the module is absent.
     *
     * @param int $caseid Case ID.
     * @return void
     */
    protected static function requeue_stuck_deliverables(int $caseid): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('casospracticos_attempts')) {
            return;
        }
        if (!class_exists('\mod_casospracticos\task\grade_deliverable')) {
            return;
        }

        $stuck = $DB->get_records_select(
            'casospracticos_attempts',
            "caseid = ? AND deliverablefilesubmitted = 1 AND deliverablemanualscore IS NULL
                 AND deliverablestatus IN ('submitted', 'error')",
            [$caseid],
            '',
            'id'
        );

        foreach ($stuck as $row) {
            $task = new \mod_casospracticos\task\grade_deliverable();
            $task->set_custom_data(['attemptid' => (int) $row->id]);
            // Second arg = check-for-existing: dedupes identical pending tasks.
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Get draft item ID for editing existing attachments.
     *
     * @param int $caseid Case ID
     * @return int Draft item ID
     */
    public static function get_attachments_draft_itemid(int $caseid): int {
        $context = \context_system::instance();
        $draftitemid = 0;

        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_casospracticos',
            'case_attachments',
            $caseid,
            local_casospracticos_get_attachment_options()
        );

        return $draftitemid;
    }
}
