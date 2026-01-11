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
 * Scheduled task to cleanup old audit log entries.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to clean up old audit log entries based on retention setting.
 */
class cleanup_audit_logs extends \core\task\scheduled_task {

    /**
     * Return the task's name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:cleanupauditlogs', 'local_casospracticos');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        // Get retention period from settings (default 90 days).
        $retentiondays = get_config('local_casospracticos', 'auditlogretention');
        if ($retentiondays === false || $retentiondays <= 0) {
            $retentiondays = 90;
        }

        // Calculate cutoff timestamp.
        $cutoff = time() - ($retentiondays * DAYSECS);

        // Count records to delete for logging.
        $count = $DB->count_records_select('local_cp_audit_log', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        if ($count > 0) {
            // Delete old records in batches to avoid memory issues.
            $batchsize = 10000;
            $deleted = 0;

            do {
                $ids = $DB->get_fieldset_select(
                    'local_cp_audit_log',
                    'id',
                    'timecreated < :cutoff',
                    ['cutoff' => $cutoff],
                    'id ASC',
                    0,
                    $batchsize
                );

                if (!empty($ids)) {
                    list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                    $DB->delete_records_select('local_cp_audit_log', "id $insql", $params);
                    $deleted += count($ids);
                }
            } while (!empty($ids) && count($ids) === $batchsize);

            mtrace("Cleaned up {$deleted} audit log entries older than {$retentiondays} days.");
        } else {
            mtrace("No audit log entries to clean up.");
        }
    }
}
