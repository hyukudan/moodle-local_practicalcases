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
                AND EXISTS (
                    SELECT 1 FROM {local_cp_cases} c WHERE c.createdby = :userid
                )";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid' => $userid,
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

        $sql = "SELECT DISTINCT createdby FROM {local_cp_cases}";
        $userlist->add_from_sql('createdby', $sql, []);
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

            // Export cases created by user.
            $cases = $DB->get_records('local_cp_cases', ['createdby' => $userid]);

            foreach ($cases as $case) {
                $data = (object) [
                    'name' => $case->name,
                    'status' => $case->status,
                    'timecreated' => transform::datetime($case->timecreated),
                    'timemodified' => transform::datetime($case->timemodified),
                ];

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_casospracticos'), $case->id],
                    $data
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

        list($insql, $inparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);

        // Anonymize cases created by these users.
        $DB->execute(
            "UPDATE {local_cp_cases} SET createdby = 0 WHERE createdby {$insql}",
            $inparams
        );
    }
}
