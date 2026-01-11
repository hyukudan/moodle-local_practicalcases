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
 * Tests for the category manager class.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_casospracticos\category_manager
 */
class category_manager_test extends advanced_testcase {

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
     * Test creating a category.
     */
    public function test_create_category(): void {
        $this->setAdminUser();

        $data = (object)[
            'name' => 'Test Category',
            'description' => 'A test category description',
        ];

        $categoryid = category_manager::create($data);

        $this->assertIsInt($categoryid);
        $this->assertGreaterThan(0, $categoryid);

        $category = category_manager::get($categoryid);
        $this->assertEquals('Test Category', $category->name);
        $this->assertEquals('A test category description', $category->description);
    }

    /**
     * Test updating a category.
     */
    public function test_update_category(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category(['name' => 'Original Name']);

        $data = (object)[
            'id' => $category->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ];

        $result = category_manager::update($data);
        $this->assertTrue($result);

        $updated = category_manager::get($category->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated description', $updated->description);
    }

    /**
     * Test deleting a category.
     */
    public function test_delete_category(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category();

        $result = category_manager::delete($category->id);
        $this->assertTrue($result);

        $deleted = category_manager::get($category->id);
        $this->assertFalse($deleted);
    }

    /**
     * Test deleting a category with cases fails.
     */
    public function test_delete_category_with_cases_fails(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category();
        $generator->create_case(['categoryid' => $category->id]);

        $this->expectException(\moodle_exception::class);
        category_manager::delete($category->id);
    }

    /**
     * Test getting all categories.
     */
    public function test_get_all_categories(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $cat1 = $generator->create_category(['name' => 'Category A', 'sortorder' => 1]);
        $cat2 = $generator->create_category(['name' => 'Category B', 'sortorder' => 2]);
        $cat3 = $generator->create_category(['name' => 'Category C', 'sortorder' => 3]);

        $categories = category_manager::get_all();

        $this->assertCount(3, $categories);
        $this->assertEquals('Category A', $categories[0]->name);
        $this->assertEquals('Category B', $categories[1]->name);
        $this->assertEquals('Category C', $categories[2]->name);
    }

    /**
     * Test getting category tree structure.
     */
    public function test_get_tree(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $parent = $generator->create_category(['name' => 'Parent']);
        $child1 = $generator->create_category(['name' => 'Child 1', 'parent' => $parent->id]);
        $child2 = $generator->create_category(['name' => 'Child 2', 'parent' => $parent->id]);
        $grandchild = $generator->create_category(['name' => 'Grandchild', 'parent' => $child1->id]);

        $tree = category_manager::get_tree();

        $this->assertCount(1, $tree); // Only root categories.
        $this->assertEquals('Parent', $tree[0]->name);
        $this->assertCount(2, $tree[0]->children);
        $this->assertCount(1, $tree[0]->children[0]->children);
    }

    /**
     * Test getting children of a category.
     */
    public function test_get_children(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $parent = $generator->create_category(['name' => 'Parent']);
        $child1 = $generator->create_category(['name' => 'Child 1', 'parent' => $parent->id]);
        $child2 = $generator->create_category(['name' => 'Child 2', 'parent' => $parent->id]);

        $children = category_manager::get_children($parent->id);

        $this->assertCount(2, $children);
    }

    /**
     * Test counting cases in a category.
     */
    public function test_count_cases(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category();
        $generator->create_case(['categoryid' => $category->id]);
        $generator->create_case(['categoryid' => $category->id]);
        $generator->create_case(['categoryid' => $category->id]);

        $count = category_manager::count_cases($category->id);

        $this->assertEquals(3, $count);
    }

    /**
     * Test counting cases in category including children.
     */
    public function test_count_cases_recursive(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);

        $generator->create_case(['categoryid' => $parent->id]);
        $generator->create_case(['categoryid' => $parent->id]);
        $generator->create_case(['categoryid' => $child->id]);
        $generator->create_case(['categoryid' => $child->id]);
        $generator->create_case(['categoryid' => $child->id]);

        $count = category_manager::count_cases($parent->id, true);

        $this->assertEquals(5, $count);
    }

    /**
     * Test reordering categories.
     */
    public function test_reorder(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $cat1 = $generator->create_category(['name' => 'First', 'sortorder' => 1]);
        $cat2 = $generator->create_category(['name' => 'Second', 'sortorder' => 2]);
        $cat3 = $generator->create_category(['name' => 'Third', 'sortorder' => 3]);

        // Move Third to first position.
        category_manager::reorder($cat3->id, 1);

        $categories = category_manager::get_all();

        $this->assertEquals('Third', $categories[0]->name);
        $this->assertEquals('First', $categories[1]->name);
        $this->assertEquals('Second', $categories[2]->name);
    }

    /**
     * Test moving a category to a different parent.
     */
    public function test_move_category(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $parent1 = $generator->create_category(['name' => 'Parent 1']);
        $parent2 = $generator->create_category(['name' => 'Parent 2']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $parent1->id]);

        $result = category_manager::move($child->id, $parent2->id);
        $this->assertTrue($result);

        $movedChild = category_manager::get($child->id);
        $this->assertEquals($parent2->id, $movedChild->parent);
    }

    /**
     * Test moving category to itself fails.
     */
    public function test_move_category_to_itself_fails(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $category = $generator->create_category();

        $this->expectException(\moodle_exception::class);
        category_manager::move($category->id, $category->id);
    }

    /**
     * Test getting category path.
     */
    public function test_get_path(): void {
        $this->setAdminUser();
        $generator = $this->get_generator();

        $parent = $generator->create_category(['name' => 'Parent']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $parent->id]);
        $grandchild = $generator->create_category(['name' => 'Grandchild', 'parent' => $child->id]);

        $path = category_manager::get_path($grandchild->id);

        $this->assertCount(3, $path);
        $this->assertEquals('Parent', $path[0]->name);
        $this->assertEquals('Child', $path[1]->name);
        $this->assertEquals('Grandchild', $path[2]->name);
    }
}
