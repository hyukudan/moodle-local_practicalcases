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
 * Audit logger for tracking all changes.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_logger {

    /** @var string Object type for cases. */
    const TYPE_CASE = 'case';

    /** @var string Object type for questions. */
    const TYPE_QUESTION = 'question';

    /** @var string Object type for categories. */
    const TYPE_CATEGORY = 'category';

    /** @var string Object type for answers. */
    const TYPE_ANSWER = 'answer';

    /** @var string Action: create. */
    const ACTION_CREATE = 'create';

    /** @var string Action: update. */
    const ACTION_UPDATE = 'update';

    /** @var string Action: delete. */
    const ACTION_DELETE = 'delete';

    /** @var string Action: publish. */
    const ACTION_PUBLISH = 'publish';

    /** @var string Action: archive. */
    const ACTION_ARCHIVE = 'archive';

    /** @var string Action: submit for review. */
    const ACTION_SUBMIT_REVIEW = 'submit_review';

    /** @var string Action: approve. */
    const ACTION_APPROVE = 'approve';

    /** @var string Action: reject. */
    const ACTION_REJECT = 'reject';

    /** @var string Action: bulk delete. */
    const ACTION_BULK_DELETE = 'bulk_delete';

    /** @var string Action: bulk move. */
    const ACTION_BULK_MOVE = 'bulk_move';

    /** @var string Action: bulk publish. */
    const ACTION_BULK_PUBLISH = 'bulk_publish';

    /** @var string Action: bulk archive. */
    const ACTION_BULK_ARCHIVE = 'bulk_archive';

    /** @var string Action: import. */
    const ACTION_IMPORT = 'import';

    /** @var string Action: export. */
    const ACTION_EXPORT = 'export';

    /**
     * Log an action.
     *
     * @param string $objecttype Type of object (case, question, category, answer).
     * @param int $objectid ID of the object.
     * @param string $action Action performed.
     * @param array|null $changes Array of changes (old/new values).
     * @return int The log entry ID.
     */
    public static function log(string $objecttype, int $objectid, string $action, ?array $changes = null): int {
        global $DB, $USER;

        $record = new \stdClass();
        $record->objecttype = $objecttype;
        $record->objectid = $objectid;
        $record->action = $action;
        $record->userid = $USER->id ?? 0;
        $record->changes = $changes ? json_encode($changes) : null;
        $record->ipaddress = getremoteaddr();
        $record->timecreated = time();

        return $DB->insert_record('local_cp_audit_log', $record);
    }

    /**
     * Log a case action.
     *
     * @param int $caseid Case ID.
     * @param string $action Action performed.
     * @param array|null $changes Changes made.
     * @return int Log entry ID.
     */
    public static function log_case(int $caseid, string $action, ?array $changes = null): int {
        return self::log(self::TYPE_CASE, $caseid, $action, $changes);
    }

    /**
     * Log a question action.
     *
     * @param int $questionid Question ID.
     * @param string $action Action performed.
     * @param array|null $changes Changes made.
     * @return int Log entry ID.
     */
    public static function log_question(int $questionid, string $action, ?array $changes = null): int {
        return self::log(self::TYPE_QUESTION, $questionid, $action, $changes);
    }

    /**
     * Log a category action.
     *
     * @param int $categoryid Category ID.
     * @param string $action Action performed.
     * @param array|null $changes Changes made.
     * @return int Log entry ID.
     */
    public static function log_category(int $categoryid, string $action, ?array $changes = null): int {
        return self::log(self::TYPE_CATEGORY, $categoryid, $action, $changes);
    }

    /**
     * Log a bulk action.
     *
     * @param string $action Bulk action type.
     * @param array $objectids Array of affected object IDs.
     * @param array|null $extradata Additional data.
     * @return int Log entry ID.
     */
    public static function log_bulk(string $action, array $objectids, ?array $extradata = null): int {
        $changes = [
            'affected_ids' => $objectids,
            'count' => count($objectids),
        ];
        if ($extradata) {
            $changes = array_merge($changes, $extradata);
        }
        // Use 0 as objectid for bulk operations.
        return self::log(self::TYPE_CASE, 0, $action, $changes);
    }

    /**
     * Get audit log entries for an object.
     *
     * @param string $objecttype Object type.
     * @param int $objectid Object ID.
     * @param int $limit Maximum entries to return.
     * @return array Array of log entries.
     */
    public static function get_logs(string $objecttype, int $objectid, int $limit = 50): array {
        global $DB;

        $logs = $DB->get_records('local_cp_audit_log', [
            'objecttype' => $objecttype,
            'objectid' => $objectid,
        ], 'timecreated DESC', '*', 0, $limit);

        // Enrich with user data.
        foreach ($logs as $log) {
            $log->changes_decoded = $log->changes ? json_decode($log->changes, true) : null;
            if ($log->userid) {
                $log->user = $DB->get_record('user', ['id' => $log->userid], 'id, firstname, lastname, email');
            }
        }

        return $logs;
    }

    /**
     * Get all audit logs with filtering.
     *
     * @param array $filters Filters (objecttype, action, userid, from, to).
     * @param int $page Page number.
     * @param int $perpage Items per page.
     * @return array Array with 'logs' and 'total'.
     */
    public static function get_all_logs(array $filters = [], int $page = 0, int $perpage = 50): array {
        global $DB;

        $where = [];
        $params = [];

        if (!empty($filters['objecttype'])) {
            $where[] = 'objecttype = :objecttype';
            $params['objecttype'] = $filters['objecttype'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['userid'])) {
            $where[] = 'userid = :userid';
            $params['userid'] = $filters['userid'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'timecreated >= :fromtime';
            $params['fromtime'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'timecreated <= :totime';
            $params['totime'] = $filters['to'];
        }

        $wheresql = $where ? implode(' AND ', $where) : '1=1';

        $total = $DB->count_records_select('local_cp_audit_log', $wheresql, $params);

        $logs = $DB->get_records_select(
            'local_cp_audit_log',
            $wheresql,
            $params,
            'timecreated DESC',
            '*',
            $page * $perpage,
            $perpage
        );

        // Enrich with user data.
        foreach ($logs as $log) {
            $log->changes_decoded = $log->changes ? json_decode($log->changes, true) : null;
            if ($log->userid) {
                $log->user = $DB->get_record('user', ['id' => $log->userid], 'id, firstname, lastname, email');
            }
        }

        return [
            'logs' => array_values($logs),
            'total' => $total,
        ];
    }

    /**
     * Get action label for display.
     *
     * @param string $action Action code.
     * @return string Localized action label.
     */
    public static function get_action_label(string $action): string {
        $key = 'audit:action_' . $action;
        if (get_string_manager()->string_exists($key, 'local_casospracticos')) {
            return get_string($key, 'local_casospracticos');
        }
        return ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Calculate diff between old and new object.
     *
     * @param object $old Old object state.
     * @param object $new New object state.
     * @param array $fields Fields to compare.
     * @return array Array of changes.
     */
    public static function calculate_diff(object $old, object $new, array $fields): array {
        $changes = [];
        foreach ($fields as $field) {
            $oldval = $old->$field ?? null;
            $newval = $new->$field ?? null;
            if ($oldval !== $newval) {
                $changes[$field] = [
                    'old' => $oldval,
                    'new' => $newval,
                ];
            }
        }
        return $changes;
    }

    /**
     * Purge old audit logs.
     *
     * @param int $olderthan Timestamp - delete logs older than this.
     * @return int Number of deleted records.
     */
    public static function purge(int $olderthan): int {
        global $DB;

        return $DB->delete_records_select('local_cp_audit_log', 'timecreated < ?', [$olderthan]);
    }
}
