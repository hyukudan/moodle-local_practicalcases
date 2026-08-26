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
 * Backup implementation for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/backup_local_plugin.class.php');

/**
 * Backup plugin class for local_casospracticos.
 */
class backup_local_casospracticos_plugin extends backup_local_plugin {

    /**
     * Define the plugin structure for backup.
     *
     * Element names prefixed with 'cp_' to avoid collisions with core
     * backup element names (category, question, answer, etc.).
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        // Whether user data is being included in this backup.
        $userinfo = $this->get_setting_value('users');

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, null, null);

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Define each element separated — prefixed with cp_ to avoid name collisions.
        $categories = new backup_nested_element('cp_categories');
        $category = new backup_nested_element('cp_category', ['id'], [
            'name', 'description', 'descriptionformat', 'parent',
            'sortorder', 'timecreated', 'timemodified'
        ]);

        // Only include the creator user id (personal data) when user data is included.
        $casefields = [
            'categoryid', 'name', 'statement', 'statementformat',
            'status', 'difficulty', 'tags', 'timecreated', 'timemodified'
        ];
        if ($userinfo) {
            $casefields[] = 'createdby';
        }

        $cases = new backup_nested_element('cp_cases');
        $case = new backup_nested_element('cp_case', ['id'], $casefields);

        $questions = new backup_nested_element('cp_questions');
        $question = new backup_nested_element('cp_question', ['id'], [
            'questiontext', 'questiontextformat', 'qtype', 'defaultmark',
            'sortorder', 'generalfeedback', 'generalfeedbackformat',
            'reasoning', 'reasoningformat', 'modelanswer', 'modelanswerformat',
            'feedbackstatus', 'feedbackverifiedat',
            'single', 'shuffleanswers', 'timecreated', 'timemodified'
        ]);

        $answers = new backup_nested_element('cp_answers');
        $answer = new backup_nested_element('cp_answer', ['id'], [
            'answer', 'answerformat', 'fraction', 'feedback',
            'feedbackformat', 'sortorder'
        ]);

        // Reviews are workflow state belonging to the case. The reviewer user id
        // is personal data and is only included when user data is included.
        $reviewfields = ['status', 'comments', 'timecreated', 'timemodified'];
        if ($userinfo) {
            array_unshift($reviewfields, 'reviewerid');
        }
        $reviews = new backup_nested_element('cp_reviews');
        $review = new backup_nested_element('cp_review', ['id'], $reviewfields);

        // Usage tracking is per-case analytics (non-user course state).
        $usages = new backup_nested_element('cp_usages');
        $usage = new backup_nested_element('cp_usage', ['id'], [
            'quizid', 'courseid', 'views', 'insertions', 'lastused', 'timecreated'
        ]);

        // Optional document deliverable definition (structural config of the case).
        $deliverables = new backup_nested_element('cp_deliverables');
        $deliverable = new backup_nested_element('cp_deliverable', ['id'], [
            'enabled', 'filetype', 'startfilename', 'rubrica', 'maxscore',
            'correctionmode', 'submissionflow', 'timecreated', 'timemodified'
        ]);

        // Build the tree.
        $pluginwrapper->add_child($categories);
        $categories->add_child($category);

        $pluginwrapper->add_child($cases);
        $cases->add_child($case);
        $case->add_child($questions);
        $questions->add_child($question);
        $question->add_child($answers);
        $answers->add_child($answer);

        $case->add_child($reviews);
        $reviews->add_child($review);

        $case->add_child($usages);
        $usages->add_child($usage);

        $case->add_child($deliverables);
        $deliverables->add_child($deliverable);

        // Add user-attempt data (practice attempts, timed attempts, sessions,
        // achievements) only when including user data.
        if ($userinfo) {
            $practiceattempts = new backup_nested_element('cp_practice_attempts');
            $attempt = new backup_nested_element('cp_attempt', ['id'], [
                'userid', 'score', 'maxscore', 'percentage', 'gradingstatus', 'status',
                'timestarted', 'timefinished', 'timecreated'
            ]);

            $responses = new backup_nested_element('cp_responses');
            $response = new backup_nested_element('cp_response', ['id'], [
                'questionid', 'response', 'score', 'iscorrect', 'requiresgrading', 'timecreated'
            ]);

            $case->add_child($practiceattempts);
            $practiceattempts->add_child($attempt);
            $attempt->add_child($responses);
            $responses->add_child($response);

            $attempt->set_source_table('local_cp_practice_attempts', ['caseid' => backup::VAR_PARENTID]);
            $response->set_source_table('local_cp_practice_responses', ['attemptid' => backup::VAR_PARENTID]);

            // Annotate user IDs for practice attempts.
            $attempt->annotate_ids('user', 'userid');

            // Timed attempts.
            $timedattempts = new backup_nested_element('cp_timed_attempts');
            $timedattempt = new backup_nested_element('cp_timed_attempt', ['id'], [
                'userid', 'token', 'timelimit', 'score', 'maxscore', 'percentage', 'gradingstatus',
                'status', 'responses', 'timestarted', 'timesubmitted', 'timecreated'
            ]);

            $case->add_child($timedattempts);
            $timedattempts->add_child($timedattempt);
            $timedattempt->set_source_table('local_cp_timed_attempts', ['caseid' => backup::VAR_PARENTID]);
            $timedattempt->annotate_ids('user', 'userid');

            // Practice sessions.
            $sessions = new backup_nested_element('cp_practice_sessions');
            $session = new backup_nested_element('cp_practice_session', ['id'], [
                'userid', 'token', 'timecreated', 'timeexpiry'
            ]);

            $case->add_child($sessions);
            $sessions->add_child($session);
            $session->set_source_table('local_cp_practice_sessions', ['caseid' => backup::VAR_PARENTID]);
            $session->annotate_ids('user', 'userid');

            // Achievements (gamification) linked to this case.
            $achievements = new backup_nested_element('cp_achievements');
            $achievement = new backup_nested_element('cp_achievement', ['id'], [
                'userid', 'achievementtype', 'timecreated'
            ]);

            $case->add_child($achievements);
            $achievements->add_child($achievement);
            $achievement->set_source_table('local_cp_achievements', ['caseid' => backup::VAR_PARENTID]);
            $achievement->annotate_ids('user', 'userid');
        }

        // Reviews and usage sources (always present).
        $review->set_source_table('local_cp_reviews', ['caseid' => backup::VAR_PARENTID]);
        $usage->set_source_table('local_cp_usage', ['caseid' => backup::VAR_PARENTID]);

        // Deliverable definition source (structural, always present if any).
        $deliverable->set_source_table('local_cp_case_deliverable', ['caseid' => backup::VAR_PARENTID]);

        // Annotate reviewer user id only when including user data.
        if ($userinfo) {
            $review->annotate_ids('user', 'reviewerid');
        }

        // Define sources.
        $category->set_source_table('local_cp_categories', [
            'contextid' => backup::VAR_CONTEXTID
        ]);

        // Get cases from categories in this context.
        $case->set_source_sql('
            SELECT c.*
            FROM {local_cp_cases} c
            JOIN {local_cp_categories} cat ON c.categoryid = cat.id
            WHERE cat.contextid = ?',
            [backup::VAR_CONTEXTID]
        );

        $question->set_source_table('local_cp_questions', ['caseid' => backup::VAR_PARENTID]);
        $answer->set_source_table('local_cp_answers', ['questionid' => backup::VAR_PARENTID]);

        // Define ID annotations (only when the user id field is present in the backup).
        if ($userinfo) {
            $case->annotate_ids('user', 'createdby');
        }

        // Define file annotations.
        $case->annotate_files('local_casospracticos', 'statement', 'id');
        // Deliverable start file (itemid = caseid, so annotated on the case element).
        $case->annotate_files('local_casospracticos', 'deliverable', 'id');
        $question->annotate_files('local_casospracticos', 'questiontext', 'id');
        $answer->annotate_files('local_casospracticos', 'answer', 'id');
        $answer->annotate_files('local_casospracticos', 'feedback', 'id');

        return $plugin;
    }
}
