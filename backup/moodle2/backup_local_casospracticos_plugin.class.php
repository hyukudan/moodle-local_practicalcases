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
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, null, null);

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Define each element separated.
        $categories = new backup_nested_element('casospracticos_categories');
        $category = new backup_nested_element('category', ['id'], [
            'name', 'description', 'descriptionformat', 'parent',
            'sortorder', 'timecreated', 'timemodified'
        ]);

        $cases = new backup_nested_element('casospracticos_cases');
        $case = new backup_nested_element('case', ['id'], [
            'categoryid', 'name', 'statement', 'statementformat',
            'status', 'difficulty', 'tags', 'timecreated', 'timemodified', 'createdby'
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'questiontext', 'questiontextformat', 'qtype', 'defaultmark',
            'sortorder', 'generalfeedback', 'generalfeedbackformat',
            'single', 'shuffleanswers', 'timecreated', 'timemodified'
        ]);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], [
            'answer', 'answerformat', 'fraction', 'feedback',
            'feedbackformat', 'sortorder'
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

        // Define ID annotations.
        $category->annotate_ids('user', 'createdby');
        $case->annotate_ids('user', 'createdby');

        // Define file annotations.
        $case->annotate_files('local_casospracticos', 'statement', 'id');
        $question->annotate_files('local_casospracticos', 'questiontext', 'id');
        $answer->annotate_files('local_casospracticos', 'answer', 'id');
        $answer->annotate_files('local_casospracticos', 'feedback', 'id');

        return $plugin;
    }
}
