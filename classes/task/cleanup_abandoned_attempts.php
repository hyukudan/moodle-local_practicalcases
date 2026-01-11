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

namespace local_casospracticos\task;

/**
 * Scheduled task to cleanup abandoned practice attempts.
 *
 * Deletes practice attempts that have been in_progress for more than 24 hours.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_abandoned_attempts extends \core\task\scheduled_task {

    /**
     * Return the task's name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanupabandoned', 'local_casospracticos');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        // Only run if table exists.
        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            mtrace('Practice attempts table does not exist yet. Skipping cleanup.');
            return;
        }

        // Delete in_progress attempts older than 24 hours.
        $cutoff = time() - (24 * 60 * 60);

        // First delete associated responses.
        $sql = "SELECT id FROM {local_cp_practice_attempts}
                WHERE status = 'in_progress' AND timestarted < :cutoff";
        $abandonedids = $DB->get_fieldset_sql($sql, ['cutoff' => $cutoff]);

        if (!empty($abandonedids)) {
            mtrace('Found ' . count($abandonedids) . ' abandoned practice attempts to clean up.');

            if ($DB->get_manager()->table_exists('local_cp_practice_responses')) {
                list($insql, $params) = $DB->get_in_or_equal($abandonedids);
                $deleted = $DB->delete_records_select('local_cp_practice_responses', "attemptid $insql", $params);
                mtrace("Deleted $deleted orphaned responses.");
            }

            // Now delete the attempts.
            $deleted = $DB->delete_records_select(
                'local_cp_practice_attempts',
                "status = 'in_progress' AND timestarted < :cutoff",
                ['cutoff' => $cutoff]
            );
            mtrace("Deleted $deleted abandoned attempts.");
        } else {
            mtrace('No abandoned attempts to clean up.');
        }

        // Also clean up old audit log entries (keep last 90 days).
        $auditcutoff = time() - (90 * 24 * 60 * 60);
        $deleted = $DB->delete_records_select(
            'local_cp_audit_log',
            'timecreated < :cutoff',
            ['cutoff' => $auditcutoff]
        );
        if ($deleted > 0) {
            mtrace("Deleted $deleted old audit log entries (older than 90 days).");
        }
    }
}
