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
 * Event observer class for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle user deletion event.
     *
     * Removes/anonymizes all personal data of the deleted user across every
     * plugin table, consistent with the privacy provider policy:
     *  - Educational/workflow content (cases, reviews) is retained but
     *    de-attributed (owner/reviewer set to 0).
     *  - Audit log rows are de-attributed and the IP address and change
     *    payload are scrubbed.
     *  - Personal attempt/answer/session/achievement data is deleted.
     *
     * @param \core\event\user_deleted $event The event.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $userid = $event->objectid;

        $transaction = $DB->start_delegated_transaction();
        try {
            // Anonymize authored content (educational, retained) to system/no owner.
            $DB->set_field('local_cp_cases', 'createdby', 0, ['createdby' => $userid]);

            // Anonymize review attribution (workflow content, retained).
            $DB->set_field('local_cp_reviews', 'reviewerid', 0, ['reviewerid' => $userid]);

            // Anonymize audit log: scrub userid, IP address and change payload
            // (changes can hold user-identifying old/new values).
            $DB->set_field('local_cp_audit_log', 'ipaddress', null, ['userid' => $userid]);
            $DB->set_field('local_cp_audit_log', 'changes', null, ['userid' => $userid]);
            $DB->set_field('local_cp_audit_log', 'userid', 0, ['userid' => $userid]);

            // Delete personal practice attempts and their responses.
            $attempts = $DB->get_fieldset_select('local_cp_practice_attempts', 'id', 'userid = ?', [$userid]);
            if (!empty($attempts)) {
                list($insql, $params) = $DB->get_in_or_equal($attempts);
                $DB->delete_records_select('local_cp_practice_responses', "attemptid $insql", $params);
            }
            $DB->delete_records('local_cp_practice_attempts', ['userid' => $userid]);

            // Delete timed attempts, practice sessions and achievements (personal data).
            $DB->delete_records('local_cp_timed_attempts', ['userid' => $userid]);
            $DB->delete_records('local_cp_practice_sessions', ['userid' => $userid]);
            $DB->delete_records('local_cp_achievements', ['userid' => $userid]);

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        // Invalidate caches.
        self::invalidate_all_caches();
    }

    /**
     * Handle course deletion event.
     *
     * Removes categories and cases associated with the course context, plus
     * every dependent row, in correct dependency order (children before
     * parents) so no orphaned rows or FK/order violations remain.
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        $courseid = $event->objectid;
        $context = \context_course::instance($courseid, IGNORE_MISSING);

        if (!$context) {
            return;
        }

        $contextid = $context->id;

        // Resolve the affected case ids up front so we can scope dependent deletes
        // and audit-log cleanup even after the parent rows are gone.
        $caseids = $DB->get_fieldset_sql(
            "SELECT c.id
               FROM {local_cp_cases} c
               JOIN {local_cp_categories} cat ON cat.id = c.categoryid
              WHERE cat.contextid = ?",
            [$contextid]
        );
        $questionids = $DB->get_fieldset_sql(
            "SELECT q.id
               FROM {local_cp_questions} q
               JOIN {local_cp_cases} c ON c.id = q.caseid
               JOIN {local_cp_categories} cat ON cat.id = c.categoryid
              WHERE cat.contextid = ?",
            [$contextid]
        );
        $categoryids = $DB->get_fieldset_select('local_cp_categories', 'id', 'contextid = ?', [$contextid]);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Subquery selecting the cases in this context (reused below).
            $casesubsql = "SELECT c.id FROM {local_cp_cases} c
                           JOIN {local_cp_categories} cat ON cat.id = c.categoryid
                           WHERE cat.contextid = ?";

            // 1. Practice responses (depend on practice attempts and questions).
            $DB->execute(
                "DELETE FROM {local_cp_practice_responses}
                  WHERE attemptid IN (
                     SELECT pa.id FROM {local_cp_practice_attempts} pa
                      WHERE pa.caseid IN ($casesubsql)
                  )",
                [$contextid]
            );

            // 2. Practice attempts (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_practice_attempts}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 3. Timed attempts (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_timed_attempts}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 4. Practice sessions (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_practice_sessions}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 5. Achievements linked to these cases (caseid is nullable).
            $DB->execute(
                "DELETE FROM {local_cp_achievements}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 6. Reviews (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_reviews}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 7. Usage tracking (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_usage}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 8. Answers (depend on questions).
            $DB->execute(
                "DELETE FROM {local_cp_answers}
                  WHERE questionid IN (
                     SELECT q.id FROM {local_cp_questions} q
                      WHERE q.caseid IN ($casesubsql)
                  )",
                [$contextid]
            );

            // 9. Questions (depend on cases).
            $DB->execute(
                "DELETE FROM {local_cp_questions}
                  WHERE caseid IN ($casesubsql)",
                [$contextid]
            );

            // 10. Audit log rows referencing the deleted objects (loose object ref).
            self::delete_audit_rows_for('case', $caseids);
            self::delete_audit_rows_for('question', $questionids);
            self::delete_audit_rows_for('category', $categoryids);

            // 11. Cases (depend on categories).
            $DB->execute(
                "DELETE FROM {local_cp_cases}
                  WHERE categoryid IN (
                     SELECT cat.id FROM {local_cp_categories} cat
                      WHERE cat.contextid = ?
                  )",
                [$contextid]
            );

            // 12. Categories.
            $DB->delete_records('local_cp_categories', ['contextid' => $contextid]);

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        // Invalidate caches.
        self::invalidate_all_caches();
    }

    /**
     * Delete audit-log rows for a set of object ids of a given object type.
     *
     * @param string $objecttype The audit log object type (case, question, category, answer).
     * @param array $objectids The object ids to purge from the audit log.
     */
    protected static function delete_audit_rows_for(string $objecttype, array $objectids) {
        global $DB;

        if (empty($objectids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($objectids);
        $params[] = $objecttype;
        $DB->delete_records_select(
            'local_cp_audit_log',
            "objectid $insql AND objecttype = ?",
            $params
        );
    }

    /**
     * Invalidate all plugin caches.
     */
    public static function invalidate_all_caches() {
        $caches = ['categorytree', 'casecounts', 'cases', 'questions'];
        foreach ($caches as $cachename) {
            $cache = \cache::make('local_casospracticos', $cachename);
            $cache->purge();
        }
    }
}
