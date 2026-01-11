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

use advanced_testcase;

/**
 * Tests for the question manager class.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_casospracticos\question_manager
 */
class question_manager_test extends advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Get the data generator.
     *
     * @return \local_casospracticos_generator
     */
    protected function get_generator(): \local_casospracticos_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_casospracticos');
    }

    /**
     * Test creating a question.
     */
    public function test_create_question(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'What is the capital of France?',
            'qtype' => 'multichoice',
        ];

        $questionid = question_manager::create($data);

        $this->assertIsInt($questionid);
        $this->assertGreaterThan(0, $questionid);

        $question = question_manager::get($questionid);
        $this->assertStringContainsString('capital of France', $question->questiontext);
        $this->assertEquals('multichoice', $question->qtype);
    }

    /**
     * Test creating a question with answers.
     */
    public function test_create_question_with_answers(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $answers = [
            ['answer' => 'Paris', 'fraction' => 1.0],
            ['answer' => 'London', 'fraction' => 0.0],
            ['answer' => 'Berlin', 'fraction' => 0.0],
            ['answer' => 'Madrid', 'fraction' => 0.0],
        ];

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'What is the capital of France?',
            'qtype' => 'multichoice',
            'answers' => $answers,
        ];

        $questionid = question_manager::create($data);

        $answercount = $DB->count_records('local_cp_answers', ['questionid' => $questionid]);
        $this->assertEquals(4, $answercount);

        // Check correct answer.
        $correctanswers = $DB->get_records('local_cp_answers', ['questionid' => $questionid, 'fraction' => 1.0]);
        $this->assertCount(1, $correctanswers);
        $correct = reset($correctanswers);
        $this->assertEquals('Paris', $correct->answer);
    }

    /**
     * Test updating a question.
     */
    public function test_update_question(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question(['questiontext' => 'Original question']);

        $data = (object)[
            'id' => $question->id,
            'questiontext' => 'Updated question',
            'defaultmark' => 2.0,
        ];

        $result = question_manager::update($data);
        $this->assertTrue($result);

        $updated = question_manager::get($question->id);
        $this->assertEquals('Updated question', $updated->questiontext);
        $this->assertEquals(2.0, $updated->defaultmark);
    }

    /**
     * Test deleting a question.
     */
    public function test_delete_question(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question();
        $generator->create_answer(['questionid' => $question->id]);
        $generator->create_answer(['questionid' => $question->id]);

        $result = question_manager::delete($question->id);
        $this->assertTrue($result);

        // Question should be deleted.
        $deleted = question_manager::get($question->id);
        $this->assertFalse($deleted);

        // Answers should also be deleted.
        $answercount = $DB->count_records('local_cp_answers', ['questionid' => $question->id]);
        $this->assertEquals(0, $answercount);
    }

    /**
     * Test getting questions for a case.
     */
    public function test_get_by_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case1 = $generator->create_case();
        $case2 = $generator->create_case();

        $generator->create_question(['caseid' => $case1->id, 'sortorder' => 1]);
        $generator->create_question(['caseid' => $case1->id, 'sortorder' => 2]);
        $generator->create_question(['caseid' => $case1->id, 'sortorder' => 3]);
        $generator->create_question(['caseid' => $case2->id]);

        $questions = question_manager::get_by_case($case1->id);

        $this->assertCount(3, $questions);
        // Should be ordered by sortorder.
        $this->assertEquals(1, $questions[0]->sortorder);
        $this->assertEquals(2, $questions[1]->sortorder);
        $this->assertEquals(3, $questions[2]->sortorder);
    }

    /**
     * Test getting questions with answers.
     */
    public function test_get_by_case_with_answers(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_complete_case(null, 2, 4);

        $questions = question_manager::get_by_case_with_answers($case->id);

        $this->assertCount(2, $questions);
        $this->assertObjectHasProperty('answers', $questions[0]);
        $this->assertCount(4, $questions[0]->answers);
    }

    /**
     * Test reordering questions.
     */
    public function test_reorder_questions(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();
        $q1 = $generator->create_question(['caseid' => $case->id, 'questiontext' => 'First', 'sortorder' => 1]);
        $q2 = $generator->create_question(['caseid' => $case->id, 'questiontext' => 'Second', 'sortorder' => 2]);
        $q3 = $generator->create_question(['caseid' => $case->id, 'questiontext' => 'Third', 'sortorder' => 3]);

        // Move Third to first position.
        $result = question_manager::reorder($q3->id, 1);
        $this->assertTrue($result);

        $questions = question_manager::get_by_case($case->id);

        $this->assertEquals('Third', $questions[0]->questiontext);
        $this->assertEquals('First', $questions[1]->questiontext);
        $this->assertEquals('Second', $questions[2]->questiontext);
    }

    /**
     * Test getting random questions from a case.
     */
    public function test_get_random(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();
        for ($i = 1; $i <= 10; $i++) {
            $generator->create_question(['caseid' => $case->id, 'questiontext' => "Question $i"]);
        }

        $random = question_manager::get_random($case->id, 5);

        $this->assertCount(5, $random);
    }

    /**
     * Test getting random questions more than available.
     */
    public function test_get_random_more_than_available(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();
        $generator->create_question(['caseid' => $case->id]);
        $generator->create_question(['caseid' => $case->id]);
        $generator->create_question(['caseid' => $case->id]);

        // Request more than available.
        $random = question_manager::get_random($case->id, 10);

        // Should return all available.
        $this->assertCount(3, $random);
    }

    /**
     * Test creating a true/false question.
     */
    public function test_create_truefalse_question(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'The Earth is flat.',
            'qtype' => 'truefalse',
            'answers' => [
                ['answer' => 'True', 'fraction' => 0.0],
                ['answer' => 'False', 'fraction' => 1.0],
            ],
        ];

        $questionid = question_manager::create($data);

        $question = question_manager::get($questionid);
        $this->assertEquals('truefalse', $question->qtype);

        $answers = $DB->get_records('local_cp_answers', ['questionid' => $questionid]);
        $this->assertCount(2, $answers);
    }

    /**
     * Test creating a short answer question.
     */
    public function test_create_shortanswer_question(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'What is 2 + 2?',
            'qtype' => 'shortanswer',
            'answers' => [
                ['answer' => '4', 'fraction' => 1.0],
                ['answer' => 'four', 'fraction' => 1.0],
            ],
        ];

        $questionid = question_manager::create($data);

        $question = question_manager::get($questionid);
        $this->assertEquals('shortanswer', $question->qtype);

        $answers = $DB->get_records('local_cp_answers', ['questionid' => $questionid]);
        $this->assertCount(2, $answers);
    }

    /**
     * Test adding an answer to existing question.
     */
    public function test_add_answer(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question();
        $initialCount = $DB->count_records('local_cp_answers', ['questionid' => $question->id]);

        $answerid = question_manager::add_answer($question->id, 'New answer', 0.5, 'Partially correct');

        $this->assertIsInt($answerid);

        $newCount = $DB->count_records('local_cp_answers', ['questionid' => $question->id]);
        $this->assertEquals($initialCount + 1, $newCount);
    }

    /**
     * Test updating an answer.
     */
    public function test_update_answer(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $answer = $generator->create_answer(['answer' => 'Original', 'fraction' => 0.0]);

        $result = question_manager::update_answer($answer->id, 'Updated', 1.0, 'Correct!');
        $this->assertTrue($result);

        global $DB;
        $updated = $DB->get_record('local_cp_answers', ['id' => $answer->id]);
        $this->assertEquals('Updated', $updated->answer);
        $this->assertEquals(1.0, $updated->fraction);
        $this->assertEquals('Correct!', $updated->feedback);
    }

    /**
     * Test deleting an answer.
     */
    public function test_delete_answer(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $answer = $generator->create_answer();

        $result = question_manager::delete_answer($answer->id);
        $this->assertTrue($result);

        $exists = $DB->record_exists('local_cp_answers', ['id' => $answer->id]);
        $this->assertFalse($exists);
    }

    /**
     * Test reordering answers.
     */
    public function test_reorder_answers(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question();
        $a1 = $generator->create_answer(['questionid' => $question->id, 'answer' => 'First', 'sortorder' => 1]);
        $a2 = $generator->create_answer(['questionid' => $question->id, 'answer' => 'Second', 'sortorder' => 2]);
        $a3 = $generator->create_answer(['questionid' => $question->id, 'answer' => 'Third', 'sortorder' => 3]);

        // Move Third to first position.
        $result = question_manager::reorder_answer($a3->id, 1);
        $this->assertTrue($result);

        $answers = $DB->get_records('local_cp_answers', ['questionid' => $question->id], 'sortorder ASC');
        $answers = array_values($answers);

        $this->assertEquals('Third', $answers[0]->answer);
        $this->assertEquals('First', $answers[1]->answer);
        $this->assertEquals('Second', $answers[2]->answer);
    }

    /**
     * Test duplicating a question.
     */
    public function test_duplicate_question(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question(['questiontext' => 'Original question']);
        $generator->create_answer(['questionid' => $question->id, 'answer' => 'Answer 1']);
        $generator->create_answer(['questionid' => $question->id, 'answer' => 'Answer 2']);

        $newid = question_manager::duplicate($question->id);

        $this->assertIsInt($newid);
        $this->assertNotEquals($question->id, $newid);

        $duplicate = question_manager::get($newid);
        $this->assertEquals($question->questiontext, $duplicate->questiontext);

        // Check answers were duplicated.
        $originalAnswers = $DB->count_records('local_cp_answers', ['questionid' => $question->id]);
        $duplicateAnswers = $DB->count_records('local_cp_answers', ['questionid' => $newid]);
        $this->assertEquals($originalAnswers, $duplicateAnswers);
    }

    /**
     * Test getting question by qtype.
     */
    public function test_get_by_qtype(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();
        $generator->create_question(['caseid' => $case->id, 'qtype' => 'multichoice']);
        $generator->create_question(['caseid' => $case->id, 'qtype' => 'multichoice']);
        $generator->create_question(['caseid' => $case->id, 'qtype' => 'truefalse']);
        $generator->create_question(['caseid' => $case->id, 'qtype' => 'shortanswer']);

        $multichoice = question_manager::get_by_case($case->id, 'multichoice');
        $truefalse = question_manager::get_by_case($case->id, 'truefalse');

        $this->assertCount(2, $multichoice);
        $this->assertCount(1, $truefalse);
    }

    /**
     * Test counting answers for a question.
     */
    public function test_count_answers(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $question = $generator->create_question();
        $generator->create_answer(['questionid' => $question->id]);
        $generator->create_answer(['questionid' => $question->id]);
        $generator->create_answer(['questionid' => $question->id]);
        $generator->create_answer(['questionid' => $question->id]);

        $count = question_manager::count_answers($question->id);

        $this->assertEquals(4, $count);
    }

    /**
     * Test validation - question must have at least 2 answers for multichoice.
     */
    public function test_validate_multichoice_minimum_answers(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'Test question',
            'qtype' => 'multichoice',
            'answers' => [
                ['answer' => 'Only one answer', 'fraction' => 1.0],
            ],
        ];

        $this->expectException(\moodle_exception::class);
        question_manager::create($data);
    }

    /**
     * Test validation - multichoice must have at least one correct answer.
     */
    public function test_validate_multichoice_correct_answer(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $data = (object)[
            'caseid' => $case->id,
            'questiontext' => 'Test question',
            'qtype' => 'multichoice',
            'answers' => [
                ['answer' => 'Wrong 1', 'fraction' => 0.0],
                ['answer' => 'Wrong 2', 'fraction' => 0.0],
            ],
        ];

        $this->expectException(\moodle_exception::class);
        question_manager::create($data);
    }
}
