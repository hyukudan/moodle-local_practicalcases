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
     * Anonymizes cases created by the deleted user.
     *
     * @param \core\event\user_deleted $event The event.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $userid = $event->objectid;

        // Update createdby to admin user (id 2) for all cases.
        $DB->set_field('local_cp_cases', 'createdby', 2, ['createdby' => $userid]);

        // Invalidate cache.
        $cache = \cache::make('local_casospracticos', 'cases');
        $cache->purge();
    }

    /**
     * Handle course deletion event.
     *
     * Removes categories and cases associated with the course context.
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

        // Get all categories in this context.
        $categories = $DB->get_records('local_cp_categories', ['contextid' => $context->id]);

        foreach ($categories as $category) {
            // Get all cases in this category.
            $cases = $DB->get_records('local_cp_cases', ['categoryid' => $category->id]);

            foreach ($cases as $case) {
                // Delete all answers for questions in this case.
                $questions = $DB->get_records('local_cp_questions', ['caseid' => $case->id]);
                foreach ($questions as $question) {
                    $DB->delete_records('local_cp_answers', ['questionid' => $question->id]);
                }

                // Delete all questions.
                $DB->delete_records('local_cp_questions', ['caseid' => $case->id]);
            }

            // Delete all cases.
            $DB->delete_records('local_cp_cases', ['categoryid' => $category->id]);
        }

        // Delete all categories.
        $DB->delete_records('local_cp_categories', ['contextid' => $context->id]);

        // Invalidate caches.
        self::invalidate_all_caches();
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
