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
 * Behat data generator for local_casospracticos.
 *
 * @package    local_casospracticos
 * @category   test
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;
use local_casospracticos\question_manager;

/**
 * Behat generator class for local_casospracticos.
 */
class behat_local_casospracticos_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'categories' => [
                'singular' => 'category',
                'datagenerator' => 'category',
                'required' => ['name'],
            ],
            'cases' => [
                'singular' => 'case',
                'datagenerator' => 'case',
                'required' => ['name', 'category'],
            ],
            'questions' => [
                'singular' => 'question',
                'datagenerator' => 'question',
                'required' => ['case', 'questiontext'],
            ],
            'answers' => [
                'singular' => 'answer',
                'datagenerator' => 'answer',
                'required' => ['question', 'answer'],
            ],
        ];
    }

    /**
     * Create a category.
     *
     * @param array $data
     * @return stdClass
     */
    protected function process_category(array $data): stdClass {
        global $DB;

        $record = new stdClass();
        $record->name = $data['name'];
        $record->description = $data['description'] ?? '';
        $record->descriptionformat = FORMAT_HTML;
        $record->parent = 0;

        if (!empty($data['parent'])) {
            $parent = $DB->get_record('local_cp_categories', ['name' => $data['parent']]);
            if ($parent) {
                $record->parent = $parent->id;
            }
        }

        $record->id = category_manager::create($record);
        return $record;
    }

    /**
     * Create a case.
     *
     * @param array $data
     * @return stdClass
     */
    protected function process_case(array $data): stdClass {
        global $DB, $USER;

        $category = $DB->get_record('local_cp_categories', ['name' => $data['category']], '*', MUST_EXIST);

        $record = new stdClass();
        $record->categoryid = $category->id;
        $record->name = $data['name'];
        $record->statement = $data['statement'] ?? 'Test statement';
        $record->statementformat = FORMAT_HTML;
        $record->status = $data['status'] ?? 'draft';
        $record->difficulty = $data['difficulty'] ?? null;
        $record->tags = [];
        $record->createdby = $USER->id;

        $record->id = case_manager::create($record);
        return $record;
    }

    /**
     * Create a question.
     *
     * @param array $data
     * @return stdClass
     */
    protected function process_question(array $data): stdClass {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['name' => $data['case']], '*', MUST_EXIST);

        $record = new stdClass();
        $record->caseid = $case->id;
        $record->questiontext = $data['questiontext'];
        $record->questiontextformat = FORMAT_HTML;
        $record->qtype = $data['qtype'] ?? 'multichoice';
        $record->defaultmark = $data['defaultmark'] ?? 1.0;
        $record->single = $data['single'] ?? 1;
        $record->shuffleanswers = $data['shuffleanswers'] ?? 1;
        $record->generalfeedback = $data['generalfeedback'] ?? '';

        // Add default answers for multichoice.
        if ($record->qtype === 'multichoice' && empty($data['answers'])) {
            $record->answers = [
                ['answer' => 'Correct answer', 'fraction' => 1.0],
                ['answer' => 'Wrong answer', 'fraction' => 0],
            ];
        }

        $record->id = question_manager::create($record);
        return $record;
    }

    /**
     * Create an answer.
     *
     * @param array $data
     * @return stdClass
     */
    protected function process_answer(array $data): stdClass {
        global $DB;

        $question = $DB->get_record('local_cp_questions', ['questiontext' => $data['question']], '*', MUST_EXIST);

        $record = new stdClass();
        $record->questionid = $question->id;
        $record->answer = $data['answer'];
        $record->answerformat = FORMAT_HTML;
        $record->fraction = $data['fraction'] ?? 0;
        $record->feedback = $data['feedback'] ?? '';
        $record->feedbackformat = FORMAT_HTML;

        $record->id = question_manager::create_answer($record);
        return $record;
    }
}
