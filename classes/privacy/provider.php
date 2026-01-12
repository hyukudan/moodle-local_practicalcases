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
 * Privacy provider for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_cp_cases',
            [
                'createdby' => 'privacy:metadata:local_cp_cases:createdby',
                'timecreated' => 'privacy:metadata:local_cp_cases:timecreated',
                'timemodified' => 'privacy:metadata:local_cp_cases:timemodified',
            ],
            'privacy:metadata:local_cp_cases'
        );

        $collection->add_database_table(
            'local_cp_audit_log',
            [
                'userid' => 'privacy:metadata:local_cp_audit_log:userid',
                'action' => 'privacy:metadata:local_cp_audit_log:action',
                'ipaddress' => 'privacy:metadata:local_cp_audit_log:ipaddress',
                'timecreated' => 'privacy:metadata:local_cp_audit_log:timecreated',
            ],
            'privacy:metadata:local_cp_audit_log'
        );

        $collection->add_database_table(
            'local_cp_reviews',
            [
                'reviewerid' => 'privacy:metadata:local_cp_reviews:reviewerid',
                'comments' => 'privacy:metadata:local_cp_reviews:comments',
                'status' => 'privacy:metadata:local_cp_reviews:status',
                'timecreated' => 'privacy:metadata:local_cp_reviews:timecreated',
            ],
            'privacy:metadata:local_cp_reviews'
        );

        $collection->add_database_table(
            'local_cp_practice_attempts',
            [
                'userid' => 'privacy:metadata:local_cp_practice_attempts:userid',
                'score' => 'privacy:metadata:local_cp_practice_attempts:score',
                'maxscore' => 'privacy:metadata:local_cp_practice_attempts:maxscore',
                'percentage' => 'privacy:metadata:local_cp_practice_attempts:percentage',
                'timestarted' => 'privacy:metadata:local_cp_practice_attempts:timestarted',
                'timefinished' => 'privacy:metadata:local_cp_practice_attempts:timefinished',
            ],
            'privacy:metadata:local_cp_practice_attempts'
        );

        $collection->add_database_table(
            'local_cp_practice_responses',
            [
                'response' => 'privacy:metadata:local_cp_practice_responses:response',
                'score' => 'privacy:metadata:local_cp_practice_responses:score',
                'iscorrect' => 'privacy:metadata:local_cp_practice_responses:iscorrect',
            ],
            'privacy:metadata:local_cp_practice_responses'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information.
     *
     * @param int $userid The user ID.
     * @return contextlist The contextlist.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                FROM {context} ctx
                WHERE ctx.contextlevel = :contextlevel
                AND (
                    EXISTS (SELECT 1 FROM {local_cp_cases} c WHERE c.createdby = :userid1)
                    OR EXISTS (SELECT 1 FROM {local_cp_audit_log} a WHERE a.userid = :userid2)
                    OR EXISTS (SELECT 1 FROM {local_cp_reviews} r WHERE r.reviewerid = :userid3)
                    OR EXISTS (SELECT 1 FROM {local_cp_practice_attempts} p WHERE p.userid = :userid4)
                )";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        // Users who created cases.
        $sql = "SELECT DISTINCT createdby FROM {local_cp_cases} WHERE createdby > 0";
        $userlist->add_from_sql('createdby', $sql, []);

        // Users in audit log.
        $sql = "SELECT DISTINCT userid FROM {local_cp_audit_log} WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who reviewed cases.
        $sql = "SELECT DISTINCT reviewerid FROM {local_cp_reviews} WHERE reviewerid > 0";
        $userlist->add_from_sql('reviewerid', $sql, []);

        // Users with practice attempts.
        $sql = "SELECT DISTINCT userid FROM {local_cp_practice_attempts} WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified user.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            $subcontext = [get_string('pluginname', 'local_casospracticos')];

            // Export cases created by user.
            $cases = $DB->get_records('local_cp_cases', ['createdby' => $userid]);
            if (!empty($cases)) {
                $casedata = [];
                foreach ($cases as $case) {
                    $casedata[] = (object) [
                        'id' => $case->id,
                        'name' => $case->name,
                        'status' => $case->status,
                        'timecreated' => transform::datetime($case->timecreated),
                        'timemodified' => transform::datetime($case->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('cases', 'local_casospracticos')]),
                    (object) ['cases' => $casedata]
                );
            }

            // Export audit log entries for user.
            $auditlogs = $DB->get_records('local_cp_audit_log', ['userid' => $userid], 'timecreated DESC');
            if (!empty($auditlogs)) {
                $logdata = [];
                foreach ($auditlogs as $log) {
                    $logdata[] = (object) [
                        'objecttype' => $log->objecttype,
                        'objectid' => $log->objectid,
                        'action' => $log->action,
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('auditlog', 'local_casospracticos')]),
                    (object) ['audit_entries' => $logdata]
                );
            }

            // Export reviews by user.
            $reviews = $DB->get_records('local_cp_reviews', ['reviewerid' => $userid]);
            if (!empty($reviews)) {
                $reviewdata = [];
                foreach ($reviews as $review) {
                    $case = $DB->get_record('local_cp_cases', ['id' => $review->caseid]);
                    $reviewdata[] = (object) [
                        'casename' => $case ? $case->name : 'Deleted case',
                        'status' => $review->status,
                        'comments' => $review->comments,
                        'timecreated' => transform::datetime($review->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('reviews', 'local_casospracticos')]),
                    (object) ['reviews' => $reviewdata]
                );
            }

            // Export practice attempts.
            $attempts = $DB->get_records('local_cp_practice_attempts', ['userid' => $userid], 'timecreated DESC');
            if (!empty($attempts)) {
                $attemptdata = [];
                foreach ($attempts as $attempt) {
                    $case = $DB->get_record('local_cp_cases', ['id' => $attempt->caseid]);
                    $attemptentry = (object) [
                        'casename' => $case ? $case->name : 'Deleted case',
                        'score' => $attempt->score,
                        'maxscore' => $attempt->maxscore,
                        'percentage' => $attempt->percentage,
                        'status' => $attempt->status,
                        'timestarted' => transform::datetime($attempt->timestarted),
                        'timefinished' => $attempt->timefinished ? transform::datetime($attempt->timefinished) : null,
                    ];

                    // Include responses.
                    $responses = $DB->get_records('local_cp_practice_responses', ['attemptid' => $attempt->id]);
                    if (!empty($responses)) {
                        $attemptentry->responses = [];
                        foreach ($responses as $response) {
                            $question = $DB->get_record('local_cp_questions', ['id' => $response->questionid]);
                            $attemptentry->responses[] = (object) [
                                'question' => $question ? strip_tags($question->questiontext) : 'Deleted question',
                                'response' => $response->response,
                                'score' => $response->score,
                                'iscorrect' => transform::yesno($response->iscorrect),
                            ];
                        }
                    }

                    $attemptdata[] = $attemptentry;
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('practiceattempts', 'local_casospracticos')]),
                    (object) ['attempts' => $attemptdata]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // We don't delete cases when users are deleted - they are educational content.
        // Just anonymize the createdby field.
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $DB->set_field('local_cp_cases', 'createdby', 0, []);
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            // Anonymize cases created by user (don't delete educational content).
            $DB->set_field('local_cp_cases', 'createdby', 0, ['createdby' => $userid]);

            // Anonymize audit log entries.
            $DB->set_field('local_cp_audit_log', 'userid', 0, ['userid' => $userid]);

            // Anonymize reviews.
            $DB->set_field('local_cp_reviews', 'reviewerid', 0, ['reviewerid' => $userid]);

            // Delete practice attempts and responses (personal data).
            $attempts = $DB->get_fieldset_select('local_cp_practice_attempts', 'id', 'userid = ?', [$userid]);
            if (!empty($attempts)) {
                list($insql, $params) = $DB->get_in_or_equal($attempts);
                $DB->delete_records_select('local_cp_practice_responses', "attemptid $insql", $params);
            }
            $DB->delete_records('local_cp_practice_attempts', ['userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Anonymize cases created by these users.
        $DB->execute(
            "UPDATE {local_cp_cases} SET createdby = 0 WHERE createdby " . $insql,
            $inparams
        );

        // Anonymize audit log entries.
        $DB->execute(
            "UPDATE {local_cp_audit_log} SET userid = 0 WHERE userid " . $insql,
            $inparams
        );

        // Anonymize reviews.
        $DB->execute(
            "UPDATE {local_cp_reviews} SET reviewerid = 0 WHERE reviewerid " . $insql,
            $inparams
        );

        // Delete practice attempts and responses.
        $attempts = $DB->get_fieldset_select('local_cp_practice_attempts', 'id', "userid " . $insql, $inparams);
        if (!empty($attempts)) {
            list($attinsql, $attparams) = $DB->get_in_or_equal($attempts);
            $DB->delete_records_select('local_cp_practice_responses', "attemptid " . $attinsql, $attparams);
        }
        $DB->delete_records_select('local_cp_practice_attempts', "userid " . $insql, $inparams);
    }
}
