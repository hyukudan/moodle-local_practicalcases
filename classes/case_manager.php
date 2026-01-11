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
     * Get a case by ID.
     *
     * @param int $id Case ID
     * @return object|false Case object or false if not found
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Get a case with its questions.
     *
     * @param int $id Case ID
     * @return object|false Case object with questions array, or false
     */
    public static function get_with_questions(int $id) {
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

        return $DB->get_records(self::TABLE, $params, $sort);
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
     * Search cases.
     *
     * @param string $search Search term
     * @param int|null $categoryid Category filter
     * @param string|null $status Status filter
     * @return array Array of matching cases
     */
    public static function search(string $search, int $categoryid = null, string $status = null): array {
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
            return self::get_all();
        }

        return $DB->get_records_select(self::TABLE, $where, $params, 'name ASC');
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
        $record->status = $data->status ?? self::STATUS_DRAFT;
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
            $record->status = $data->status;
        }
        if (array_key_exists('difficulty', (array) $data)) {
            $record->difficulty = $data->difficulty;
        }
        if (isset($data->tags)) {
            $record->tags = self::encode_tags($data->tags);
        }

        $record->timemodified = time();

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Delete a case and all its questions.
     *
     * @param int $id Case ID
     * @return bool Success
     */
    public static function delete(int $id): bool {
        global $DB;

        // Delete all questions (and their answers).
        $questions = question_manager::get_by_case($id);
        foreach ($questions as $question) {
            question_manager::delete($question->id);
        }

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Duplicate a case with all its questions.
     *
     * @param int $id Case ID to duplicate
     * @param int|null $newcategoryid New category (null to keep same)
     * @return int New case ID
     */
    public static function duplicate(int $id, int $newcategoryid = null): int {
        $case = self::get_with_questions($id);
        if (!$case) {
            throw new \moodle_exception('error:casenotfound', 'local_casospracticos');
        }

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

        return $newid;
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
        return (float) $DB->get_field('local_cp_questions', 'SUM(defaultmark)', ['caseid' => $id]) ?? 0;
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

        $sql = "SELECT c.*, COUNT(q.id) as questioncount
                FROM {" . self::TABLE . "} c
                LEFT JOIN {local_cp_questions} q ON q.caseid = c.id
                WHERE {$where}
                GROUP BY c.id
                ORDER BY c.name ASC";

        return $DB->get_records_sql($sql, $params);
    }
}
