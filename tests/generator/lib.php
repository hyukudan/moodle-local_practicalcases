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
 * Test data generator for practical cases plugin.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator class for local_casospracticos.
 */
class local_casospracticos_generator extends component_generator_base {

    /** @var int Counter for unique category names. */
    protected $categorycount = 0;

    /** @var int Counter for unique case names. */
    protected $casecount = 0;

    /** @var int Counter for unique question names. */
    protected $questioncount = 0;

    /**
     * Reset generator counters.
     */
    public function reset() {
        $this->categorycount = 0;
        $this->casecount = 0;
        $this->questioncount = 0;
        parent::reset();
    }

    /**
     * Create a category.
     *
     * @param array|stdClass $record Category data.
     * @return stdClass The created category.
     */
    public function create_category($record = null) {
        global $DB;

        $this->categorycount++;
        $i = $this->categorycount;

        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test Category ' . $i;
        }
        if (!isset($record->description)) {
            $record->description = 'Description for category ' . $i;
        }
        if (!isset($record->descriptionformat)) {
            $record->descriptionformat = FORMAT_HTML;
        }
        if (!isset($record->parent)) {
            $record->parent = 0;
        }
        if (!isset($record->sortorder)) {
            $record->sortorder = $i;
        }
        if (!isset($record->contextid)) {
            $record->contextid = context_system::instance()->id;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('local_cp_categories', $record);

        return $record;
    }

    /**
     * Create a case.
     *
     * @param array|stdClass $record Case data.
     * @return stdClass The created case.
     */
    public function create_case($record = null) {
        global $DB, $USER;

        $this->casecount++;
        $i = $this->casecount;

        $record = (object)(array)$record;

        if (!isset($record->categoryid)) {
            // Create a default category if not provided.
            $category = $this->create_category();
            $record->categoryid = $category->id;
        }
        if (!isset($record->name)) {
            $record->name = 'Test Case ' . $i;
        }
        if (!isset($record->statement)) {
            $record->statement = '<p>This is the statement for test case ' . $i . '. ' .
                'It contains a detailed description of a practical scenario that ' .
                'students need to analyze and answer questions about.</p>';
        }
        if (!isset($record->statementformat)) {
            $record->statementformat = FORMAT_HTML;
        }
        if (!isset($record->status)) {
            $record->status = 'draft';
        }
        if (!isset($record->difficulty)) {
            $record->difficulty = rand(1, 5);
        }
        if (!isset($record->tags)) {
            $record->tags = '';
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }
        if (!isset($record->createdby)) {
            $record->createdby = $USER->id;
        }

        $record->id = $DB->insert_record('local_cp_cases', $record);

        return $record;
    }

    /**
     * Create a question for a case.
     *
     * @param array|stdClass $record Question data.
     * @return stdClass The created question.
     */
    public function create_question($record = null) {
        global $DB;

        $this->questioncount++;
        $i = $this->questioncount;

        $record = (object)(array)$record;

        if (!isset($record->caseid)) {
            // Create a default case if not provided.
            $case = $this->create_case();
            $record->caseid = $case->id;
        }
        if (!isset($record->questiontext)) {
            $record->questiontext = 'Test question ' . $i . ': What is the correct answer?';
        }
        if (!isset($record->questiontextformat)) {
            $record->questiontextformat = FORMAT_HTML;
        }
        if (!isset($record->qtype)) {
            $record->qtype = 'multichoice';
        }
        if (!isset($record->defaultmark)) {
            $record->defaultmark = 1.0;
        }
        if (!isset($record->sortorder)) {
            $record->sortorder = $i;
        }
        if (!isset($record->generalfeedback)) {
            $record->generalfeedback = '';
        }
        if (!isset($record->generalfeedbackformat)) {
            $record->generalfeedbackformat = FORMAT_HTML;
        }
        if (!isset($record->single)) {
            $record->single = 1;
        }
        if (!isset($record->shuffleanswers)) {
            $record->shuffleanswers = 1;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        $record->id = $DB->insert_record('local_cp_questions', $record);

        return $record;
    }

    /**
     * Create an answer for a question.
     *
     * @param array|stdClass $record Answer data.
     * @return stdClass The created answer.
     */
    public function create_answer($record = null) {
        global $DB;

        $record = (object)(array)$record;

        if (!isset($record->questionid)) {
            // Create a default question if not provided.
            $question = $this->create_question();
            $record->questionid = $question->id;
        }
        if (!isset($record->answer)) {
            $record->answer = 'Test answer';
        }
        if (!isset($record->answerformat)) {
            $record->answerformat = FORMAT_HTML;
        }
        if (!isset($record->fraction)) {
            $record->fraction = 0.0;
        }
        if (!isset($record->feedback)) {
            $record->feedback = '';
        }
        if (!isset($record->feedbackformat)) {
            $record->feedbackformat = FORMAT_HTML;
        }
        if (!isset($record->sortorder)) {
            $record->sortorder = 1;
        }

        $record->id = $DB->insert_record('local_cp_answers', $record);

        return $record;
    }

    /**
     * Create a complete case with questions and answers.
     *
     * @param array|stdClass $record Case data.
     * @param int $numquestions Number of questions to create.
     * @param int $numanswers Number of answers per question.
     * @return stdClass The created case with questions and answers.
     */
    public function create_complete_case($record = null, $numquestions = 3, $numanswers = 4) {
        $case = $this->create_case($record);
        $case->questions = [];

        for ($q = 0; $q < $numquestions; $q++) {
            $question = $this->create_question([
                'caseid' => $case->id,
                'sortorder' => $q + 1,
            ]);
            $question->answers = [];

            for ($a = 0; $a < $numanswers; $a++) {
                $answer = $this->create_answer([
                    'questionid' => $question->id,
                    'answer' => 'Answer option ' . ($a + 1),
                    'fraction' => ($a === 0) ? 1.0 : 0.0, // First answer is correct.
                    'sortorder' => $a + 1,
                ]);
                $question->answers[] = $answer;
            }

            $case->questions[] = $question;
        }

        return $case;
    }

    /**
     * Create a category tree.
     *
     * @param int $depth Depth of the tree.
     * @param int $children Number of children per node.
     * @return array Array of created categories.
     */
    public function create_category_tree($depth = 2, $children = 2) {
        $categories = [];
        $this->create_category_tree_recursive(0, $depth, $children, $categories);
        return $categories;
    }

    /**
     * Recursive helper for creating category trees.
     *
     * @param int $parentid Parent category ID.
     * @param int $depth Remaining depth.
     * @param int $children Number of children per node.
     * @param array $categories Array to store created categories.
     */
    protected function create_category_tree_recursive($parentid, $depth, $children, &$categories) {
        if ($depth <= 0) {
            return;
        }

        for ($i = 0; $i < $children; $i++) {
            $category = $this->create_category([
                'parent' => $parentid,
            ]);
            $categories[] = $category;

            $this->create_category_tree_recursive($category->id, $depth - 1, $children, $categories);
        }
    }
}
