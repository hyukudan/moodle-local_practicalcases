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
 * Tests for the case manager class.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_casospracticos\case_manager
 */
class case_manager_test extends advanced_testcase {

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
     * Test creating a case.
     */
    public function test_create_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category();

        $data = (object)[
            'categoryid' => $category->id,
            'name' => 'Test Case',
            'statement' => '<p>This is a test case statement.</p>',
        ];

        $caseid = case_manager::create($data);

        $this->assertIsInt($caseid);
        $this->assertGreaterThan(0, $caseid);

        $case = case_manager::get($caseid);
        $this->assertEquals('Test Case', $case->name);
        $this->assertStringContainsString('test case statement', $case->statement);
        $this->assertEquals('draft', $case->status);
    }

    /**
     * Test updating a case.
     */
    public function test_update_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case(['name' => 'Original Name']);

        $data = (object)[
            'id' => $case->id,
            'name' => 'Updated Name',
            'statement' => '<p>Updated statement.</p>',
            'status' => 'published',
        ];

        $result = case_manager::update($data);
        $this->assertTrue($result);

        $updated = case_manager::get($case->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertStringContainsString('Updated statement', $updated->statement);
        $this->assertEquals('published', $updated->status);
    }

    /**
     * Test deleting a case.
     */
    public function test_delete_case(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_complete_case(null, 3, 4);

        // Verify questions and answers exist.
        $this->assertEquals(3, $DB->count_records('local_cp_questions', ['caseid' => $case->id]));

        $result = case_manager::delete($case->id);
        $this->assertTrue($result);

        // Case should be deleted.
        $deleted = case_manager::get($case->id);
        $this->assertFalse($deleted);

        // Questions and answers should also be deleted.
        $this->assertEquals(0, $DB->count_records('local_cp_questions', ['caseid' => $case->id]));
    }

    /**
     * Test getting cases by category.
     */
    public function test_get_by_category(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $cat1 = $generator->create_category();
        $cat2 = $generator->create_category();

        $generator->create_case(['categoryid' => $cat1->id, 'name' => 'Case 1']);
        $generator->create_case(['categoryid' => $cat1->id, 'name' => 'Case 2']);
        $generator->create_case(['categoryid' => $cat2->id, 'name' => 'Case 3']);

        $cases = case_manager::get_by_category($cat1->id);

        $this->assertCount(2, $cases);
    }

    /**
     * Test filtering cases by status.
     */
    public function test_get_by_status(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $generator->create_case(['status' => 'draft']);
        $generator->create_case(['status' => 'draft']);
        $generator->create_case(['status' => 'published']);
        $generator->create_case(['status' => 'archived']);

        $drafts = case_manager::get_all(0, 'draft');
        $published = case_manager::get_all(0, 'published');

        $this->assertCount(2, $drafts);
        $this->assertCount(1, $published);
    }

    /**
     * Test counting questions in a case.
     */
    public function test_count_questions(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();
        $generator->create_question(['caseid' => $case->id]);
        $generator->create_question(['caseid' => $case->id]);
        $generator->create_question(['caseid' => $case->id]);

        $count = case_manager::count_questions($case->id);

        $this->assertEquals(3, $count);
    }

    /**
     * Test publishing a case.
     */
    public function test_publish_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case(['status' => 'draft']);

        $result = case_manager::publish($case->id);
        $this->assertTrue($result);

        $published = case_manager::get($case->id);
        $this->assertEquals('published', $published->status);
    }

    /**
     * Test archiving a case.
     */
    public function test_archive_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case(['status' => 'published']);

        $result = case_manager::archive($case->id);
        $this->assertTrue($result);

        $archived = case_manager::get($case->id);
        $this->assertEquals('archived', $archived->status);
    }

    /**
     * Test duplicating a case.
     */
    public function test_duplicate_case(): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->get_generator();

        $original = $generator->create_complete_case(['name' => 'Original Case'], 3, 4);

        $newid = case_manager::duplicate($original->id);

        $this->assertIsInt($newid);
        $this->assertNotEquals($original->id, $newid);

        $duplicate = case_manager::get($newid);
        $this->assertStringContainsString('Original Case', $duplicate->name);
        $this->assertStringContainsString('copia', strtolower($duplicate->name));
        $this->assertEquals('draft', $duplicate->status);

        // Check questions were duplicated.
        $originalQuestions = $DB->count_records('local_cp_questions', ['caseid' => $original->id]);
        $duplicateQuestions = $DB->count_records('local_cp_questions', ['caseid' => $newid]);
        $this->assertEquals($originalQuestions, $duplicateQuestions);
    }

    /**
     * Test moving a case to a different category.
     */
    public function test_move_case(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $cat1 = $generator->create_category();
        $cat2 = $generator->create_category();
        $case = $generator->create_case(['categoryid' => $cat1->id]);

        $result = case_manager::move($case->id, $cat2->id);
        $this->assertTrue($result);

        $moved = case_manager::get($case->id);
        $this->assertEquals($cat2->id, $moved->categoryid);
    }

    /**
     * Test searching cases.
     */
    public function test_search_cases(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $generator->create_case(['name' => 'Administrative Law Case']);
        $generator->create_case(['name' => 'Civil Law Case']);
        $generator->create_case(['name' => 'Criminal Law Case']);

        $results = case_manager::search('Administrative');
        $this->assertCount(1, $results);

        $results = case_manager::search('Law');
        $this->assertCount(3, $results);

        $results = case_manager::search('Nonexistent');
        $this->assertCount(0, $results);
    }

    /**
     * Test getting case with questions.
     */
    public function test_get_with_questions(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_complete_case(null, 3, 4);

        $full = case_manager::get_with_questions($case->id);

        $this->assertEquals($case->id, $full->id);
        $this->assertObjectHasProperty('questions', $full);
        $this->assertCount(3, $full->questions);
        $this->assertObjectHasProperty('answers', $full->questions[0]);
        $this->assertCount(4, $full->questions[0]->answers);
    }

    /**
     * Test setting case difficulty.
     */
    public function test_set_difficulty(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case(['difficulty' => 1]);

        $result = case_manager::set_difficulty($case->id, 5);
        $this->assertTrue($result);

        $updated = case_manager::get($case->id);
        $this->assertEquals(5, $updated->difficulty);
    }

    /**
     * Test invalid difficulty value.
     */
    public function test_set_invalid_difficulty(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_case();

        $this->expectException(\moodle_exception::class);
        case_manager::set_difficulty($case->id, 10);
    }

    /**
     * Test case statistics.
     */
    public function test_get_statistics(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $case = $generator->create_complete_case(null, 5, 4);

        $stats = case_manager::get_statistics($case->id);

        $this->assertEquals(5, $stats->questioncount);
        $this->assertEquals(20, $stats->answercount);
        $this->assertGreaterThan(0, $stats->totalmarks);
    }
}
