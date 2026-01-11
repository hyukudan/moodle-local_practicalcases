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

        foreach ($caseids as $caseid) {
            try {
                $case = $DB->get_record('local_cp_cases', ['id' => $caseid]);
                if (!$case) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                // Delete answers first.
                $questions = $DB->get_records('local_cp_questions', ['caseid' => $caseid], '', 'id');
                foreach ($questions as $question) {
                    $DB->delete_records('local_cp_answers', ['questionid' => $question->id]);
                }

                // Delete questions.
                $DB->delete_records('local_cp_questions', ['caseid' => $caseid]);

                // Delete reviews.
                $DB->delete_records('local_cp_reviews', ['caseid' => $caseid]);

                // Delete usage tracking.
                $DB->delete_records('local_cp_usage', ['caseid' => $caseid]);

                // Delete case.
                $DB->delete_records('local_cp_cases', ['id' => $caseid]);

                $deleted[] = $caseid;

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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

        foreach ($caseids as $caseid) {
            try {
                $case = $DB->get_record('local_cp_cases', ['id' => $caseid]);
                if (!$case) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                $oldcategory = $case->categoryid;
                $DB->set_field('local_cp_cases', 'categoryid', $categoryid, ['id' => $caseid]);
                $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

                $moved[] = ['id' => $caseid, 'from' => $oldcategory, 'to' => $categoryid];

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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

        foreach ($caseids as $caseid) {
            try {
                $case = $DB->get_record('local_cp_cases', ['id' => $caseid]);
                if (!$case) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                if ($case->status === 'published') {
                    $failed[] = ['id' => $caseid, 'reason' => 'already_published'];
                    continue;
                }

                // Check case has at least one question.
                $questioncount = $DB->count_records('local_cp_questions', ['caseid' => $caseid]);
                if ($questioncount === 0) {
                    $failed[] = ['id' => $caseid, 'reason' => 'no_questions'];
                    continue;
                }

                $DB->set_field('local_cp_cases', 'status', 'published', ['id' => $caseid]);
                $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

                $published[] = $caseid;

                // Trigger event.
                $case->status = 'published';
                $event = \local_casospracticos\event\case_published::create_from_case($case);
                $event->trigger();

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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

        foreach ($caseids as $caseid) {
            try {
                $case = $DB->get_record('local_cp_cases', ['id' => $caseid]);
                if (!$case) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                if ($case->status === 'archived') {
                    $failed[] = ['id' => $caseid, 'reason' => 'already_archived'];
                    continue;
                }

                $DB->set_field('local_cp_cases', 'status', 'archived', ['id' => $caseid]);
                $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

                $archived[] = $caseid;

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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

        foreach ($caseids as $caseid) {
            try {
                if (!$DB->record_exists('local_cp_cases', ['id' => $caseid])) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                $DB->set_field('local_cp_cases', 'status', $status, ['id' => $caseid]);
                $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

                $changed[] = $caseid;

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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

        foreach ($caseids as $caseid) {
            try {
                $case = $DB->get_record('local_cp_cases', ['id' => $caseid]);
                if (!$case) {
                    $failed[] = ['id' => $caseid, 'reason' => 'notfound'];
                    continue;
                }

                $existingtags = $case->tags ? json_decode($case->tags, true) : [];

                if ($replace) {
                    $newtags = $tags;
                } else {
                    $newtags = array_unique(array_merge($existingtags, $tags));
                }

                $DB->set_field('local_cp_cases', 'tags', json_encode(array_values($newtags)), ['id' => $caseid]);
                $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

                $updated[] = $caseid;

            } catch (\Exception $e) {
                $failed[] = ['id' => $caseid, 'reason' => $e->getMessage()];
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
