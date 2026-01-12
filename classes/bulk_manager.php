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

namespace local_casospracticos;

/**
 * Bulk operations manager for cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_manager {

    /**
     * Bulk delete cases.
     *
     * @param array $caseids Array of case IDs to delete.
     * @return array Result with 'success', 'deleted', 'failed'.
     */
    public static function delete_cases(array $caseids): array {
        global $DB;

        $deleted = [];
        $failed = [];

        // Validate which cases exist.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params, '', 'id');
        $existingids = array_keys($existingcases);

        // Mark non-existent cases as failed.
        foreach ($caseids as $caseid) {
            if (!in_array($caseid, $existingids)) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
            }
        }

        if (!empty($existingids)) {
            $transaction = $DB->start_delegated_transaction();
            try {
                // Get all question IDs for these cases in one query.
                list($caseinsql, $caseparams) = $DB->get_in_or_equal($existingids, SQL_PARAMS_NAMED);
                $questionids = $DB->get_fieldset_select('local_cp_questions', 'id', "caseid $caseinsql", $caseparams);

                // Delete answers in batch.
                if (!empty($questionids)) {
                    list($qinsql, $qparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
                    $DB->delete_records_select('local_cp_answers', "questionid $qinsql", $qparams);
                }

                // Delete questions in batch.
                $DB->delete_records_select('local_cp_questions', "caseid $caseinsql", $caseparams);

                // Delete reviews in batch.
                $DB->delete_records_select('local_cp_reviews', "caseid $caseinsql", $caseparams);

                // Delete usage tracking in batch.
                $DB->delete_records_select('local_cp_usage', "caseid $caseinsql", $caseparams);

                // Delete practice attempts in batch.
                $DB->delete_records_select('local_cp_practice_attempts', "caseid $caseinsql", $caseparams);

                // Delete cases in batch.
                $DB->delete_records_select('local_cp_cases', "id $caseinsql", $caseparams);

                $deleted = $existingids;

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                // If batch fails, mark all as failed.
                foreach ($existingids as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }
        }

        // Log bulk action.
        audit_logger::log_bulk(audit_logger::ACTION_BULK_DELETE, $deleted, [
            'failed' => $failed,
        ]);

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk move cases to a category.
     *
     * @param array $caseids Array of case IDs.
     * @param int $categoryid Target category ID.
     * @return array Result with 'success', 'moved', 'failed'.
     */
    public static function move_cases(array $caseids, int $categoryid): array {
        global $DB;

        // Verify category exists.
        if (!$DB->record_exists('local_cp_categories', ['id' => $categoryid])) {
            return [
                'success' => false,
                'moved' => [],
                'failed' => array_map(fn($id) => ['id' => $id, 'reason' => 'category_notfound'], $caseids),
            ];
        }

        $moved = [];
        $failed = [];

        // Validate which cases exist and get their current categories.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params, '', 'id, categoryid');
        $existingids = array_keys($existingcases);

        // Mark non-existent cases as failed.
        foreach ($caseids as $caseid) {
            if (!isset($existingcases[$caseid])) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
            } else {
                $moved[] = ['id' => $caseid, 'from' => $existingcases[$caseid]->categoryid, 'to' => $categoryid];
            }
        }

        // Batch update all valid cases.
        if (!empty($existingids)) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $now = time();
                list($updateinsql, $updateparams) = $DB->get_in_or_equal($existingids, SQL_PARAMS_NAMED);
                $updateparams['categoryid'] = $categoryid;
                $updateparams['timemodified'] = $now;
                $DB->execute(
                    "UPDATE {local_cp_cases} SET categoryid = :categoryid, timemodified = :timemodified WHERE id $updateinsql",
                    $updateparams
                );

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                // If batch fails, mark all as failed.
                $moved = [];
                foreach ($existingids as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }
        }

        // Log bulk action.
        audit_logger::log_bulk(audit_logger::ACTION_BULK_MOVE, array_column($moved, 'id'), [
            'target_category' => $categoryid,
            'failed' => $failed,
        ]);

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();
        $cache = \cache::make('local_casospracticos', 'casecounts');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'moved' => $moved,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk publish cases.
     *
     * @param array $caseids Array of case IDs.
     * @return array Result with 'success', 'published', 'failed'.
     */
    public static function publish_cases(array $caseids): array {
        global $DB;

        $published = [];
        $failed = [];
        $topublish = [];

        // Fetch all cases in one query.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params);

        // Get question counts for all cases in one query.
        $sql = "SELECT caseid, COUNT(*) as qcount
                FROM {local_cp_questions}
                WHERE caseid $insql
                GROUP BY caseid";
        $questioncounts = $DB->get_records_sql_menu($sql, $params);

        // Validate each case.
        foreach ($caseids as $caseid) {
            if (!isset($existingcases[$caseid])) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                continue;
            }

            $case = $existingcases[$caseid];

            if ($case->status === 'published') {
                $failed[] = ['id' => $caseid, 'reason' => 'already_published'];
                continue;
            }

            $qcount = $questioncounts[$caseid] ?? 0;
            if ($qcount === 0) {
                $failed[] = ['id' => $caseid, 'reason' => 'no_questions'];
                continue;
            }

            $topublish[$caseid] = $case;
        }

        // Batch update all valid cases.
        if (!empty($topublish)) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $now = time();
                $ids = array_keys($topublish);
                list($updateinsql, $updateparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $updateparams['timemodified'] = $now;
                $DB->execute(
                    "UPDATE {local_cp_cases} SET status = 'published', timemodified = :timemodified WHERE id $updateinsql",
                    $updateparams
                );

                $published = $ids;

                $transaction->allow_commit();

                // Trigger events for all published cases (after commit to ensure data integrity).
                foreach ($topublish as $case) {
                    $case->status = 'published';
                    $event = \local_casospracticos\event\case_published::create_from_case($case);
                    $event->trigger();
                }

            } catch (\Exception $e) {
                $transaction->rollback($e);
                foreach (array_keys($topublish) as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }
        }

        // Log bulk action.
        audit_logger::log_bulk(audit_logger::ACTION_BULK_PUBLISH, $published, [
            'failed' => $failed,
        ]);

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'published' => $published,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk archive cases.
     *
     * @param array $caseids Array of case IDs.
     * @return array Result with 'success', 'archived', 'failed'.
     */
    public static function archive_cases(array $caseids): array {
        global $DB;

        $archived = [];
        $failed = [];
        $toarchive = [];

        // Fetch all cases in one query.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params, '', 'id, status');

        // Validate each case.
        foreach ($caseids as $caseid) {
            if (!isset($existingcases[$caseid])) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                continue;
            }

            if ($existingcases[$caseid]->status === 'archived') {
                $failed[] = ['id' => $caseid, 'reason' => 'already_archived'];
                continue;
            }

            $toarchive[] = $caseid;
        }

        // Batch update all valid cases.
        if (!empty($toarchive)) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $now = time();
                list($updateinsql, $updateparams) = $DB->get_in_or_equal($toarchive, SQL_PARAMS_NAMED);
                $updateparams['timemodified'] = $now;
                $DB->execute(
                    "UPDATE {local_cp_cases} SET status = 'archived', timemodified = :timemodified WHERE id $updateinsql",
                    $updateparams
                );

                $archived = $toarchive;

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                foreach ($toarchive as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }
        }

        // Log bulk action.
        audit_logger::log_bulk(audit_logger::ACTION_BULK_ARCHIVE, $archived, [
            'failed' => $failed,
        ]);

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'archived' => $archived,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk change status.
     *
     * @param array $caseids Array of case IDs.
     * @param string $status New status.
     * @return array Result.
     */
    public static function change_status(array $caseids, string $status): array {
        $validstatuses = ['draft', 'pending_review', 'published', 'archived'];
        if (!in_array($status, $validstatuses)) {
            return [
                'success' => false,
                'changed' => [],
                'failed' => array_map(fn($id) => ['id' => $id, 'reason' => 'invalid_status'], $caseids),
            ];
        }

        if ($status === 'published') {
            return self::publish_cases($caseids);
        }

        if ($status === 'archived') {
            return self::archive_cases($caseids);
        }

        global $DB;

        $changed = [];
        $failed = [];

        // Validate which cases exist.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingids = $DB->get_fieldset_select('local_cp_cases', 'id', "id $insql", $params);

        // Mark non-existent cases as failed.
        foreach ($caseids as $caseid) {
            if (!in_array($caseid, $existingids)) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
            }
        }

        // Batch update all valid cases.
        if (!empty($existingids)) {
            try {
                $now = time();
                list($updateinsql, $updateparams) = $DB->get_in_or_equal($existingids, SQL_PARAMS_NAMED);
                $updateparams['status'] = $status;
                $updateparams['timemodified'] = $now;
                $DB->execute(
                    "UPDATE {local_cp_cases} SET status = :status, timemodified = :timemodified WHERE id $updateinsql",
                    $updateparams
                );

                $changed = $existingids;

            } catch (\Exception $e) {
                foreach ($existingids as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }
        }

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'changed' => $changed,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk assign tags.
     *
     * @param array $caseids Array of case IDs.
     * @param array $tags Tags to assign.
     * @param bool $replace Replace existing tags or merge.
     * @return array Result.
     */
    public static function assign_tags(array $caseids, array $tags, bool $replace = false): array {
        global $DB;

        $updated = [];
        $failed = [];

        // Validate which cases exist first.
        list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
        $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params);

        // Mark non-existent cases as failed.
        foreach ($caseids as $caseid) {
            if (!isset($existingcases[$caseid])) {
                $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
            }
        }

        // Process existing cases in a transaction.
        if (!empty($existingcases)) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $now = time();
                $caseidstoprocess = array_keys($existingcases);

                if ($replace) {
                    // Performance: When replacing, all cases get same tags - batch update.
                    $encodedtags = json_encode(array_values($tags));
                    list($upinsql, $upparams) = $DB->get_in_or_equal($caseidstoprocess, SQL_PARAMS_NAMED);
                    $upparams['tags'] = $encodedtags;
                    $upparams['now'] = $now;
                    $DB->execute(
                        "UPDATE {local_cp_cases} SET tags = :tags, timemodified = :now WHERE id " . $upinsql,
                        $upparams
                    );
                    $updated = $caseidstoprocess;
                } else {
                    // Merge requires per-case processing, but batch timemodified update.
                    foreach ($existingcases as $caseid => $case) {
                        $existingtags = $case->tags ? json_decode($case->tags, true) : [];
                        $newtags = array_unique(array_merge($existingtags, $tags));
                        $DB->set_field('local_cp_cases', 'tags', json_encode(array_values($newtags)), ['id' => $caseid]);
                        $updated[] = $caseid;
                    }
                    // Batch update timemodified for all processed cases.
                    list($upinsql, $upparams) = $DB->get_in_or_equal($updated, SQL_PARAMS_NAMED);
                    $upparams['now'] = $now;
                    $DB->execute(
                        "UPDATE {local_cp_cases} SET timemodified = :now WHERE id " . $upinsql,
                        $upparams
                    );
                }

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                // Mark all as failed on error.
                foreach (array_keys($existingcases) as $id) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
                $updated = [];
            }
        }

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();

        return [
            'success' => count($failed) === 0,
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk duplicate cases.
     *
     * @param array $caseids Array of case IDs.
     * @param int|null $targetcategory Target category (null = same category).
     * @return array Result with 'success', 'duplicated', 'failed'.
     */
    public static function duplicate_cases(array $caseids, ?int $targetcategory = null): array {
        global $DB;

        $duplicated = [];
        $failed = [];

        foreach ($caseids as $caseid) {
            try {
                $newid = case_manager::duplicate($caseid, $targetcategory);
                $duplicated[] = ['original' => $caseid, 'new' => $newid];
            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
            }
        }

        return [
            'success' => count($failed) === 0,
            'duplicated' => $duplicated,
            'failed' => $failed,
        ];
    }
}
