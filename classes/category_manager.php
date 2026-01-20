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
 * Category manager for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage practical case categories.
 */
class category_manager {

    /** @var string Table name for categories */
    const TABLE = 'local_cp_categories';

    /**
     * Get a category by ID.
     *
     * @param int $id Category ID
     * @return \stdClass|false Category object or false if not found
     */
    public static function get(int $id): \stdClass|false {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Get all categories.
     *
     * @param int $parent Parent category ID (0 for top level)
     * @param string $sort Sort field
     * @return array Array of category objects
     */
    public static function get_all(int $parent = null, string $sort = 'sortorder ASC, name ASC'): array {
        global $DB;

        $params = [];
        if ($parent !== null) {
            $params['parent'] = $parent;
        }

        return $DB->get_records(self::TABLE, $params, $sort);
    }

    /**
     * Get categories as a hierarchical tree.
     *
     * @return array Nested array of categories
     */
    public static function get_tree(): array {
        // Performance: Use cache to avoid rebuilding tree on every request.
        $cache = \cache::make('local_casospracticos', 'categorytree');
        $tree = $cache->get('tree');

        if ($tree === false) {
            $categories = self::get_all();
            $tree = self::build_tree($categories, 0);
            $cache->set('tree', $tree);
        }

        return $tree;
    }

    /**
     * Invalidate the category tree cache.
     */
    public static function invalidate_cache(): void {
        $cache = \cache::make('local_casospracticos', 'categorytree');
        $cache->delete('tree');
    }

    /**
     * Build a tree structure from flat category list.
     *
     * @param array $categories All categories
     * @param int $parentid Parent ID to start from
     * @param int $depth Current depth
     * @return array Tree structure
     */
    private static function build_tree(array $categories, int $parentid, int $depth = 0): array {
        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent == $parentid) {
                $category->depth = $depth;
                $category->children = self::build_tree($categories, $category->id, $depth + 1);
                $tree[] = $category;
            }
        }
        return $tree;
    }

    /**
     * Get categories as flat list with indentation info.
     *
     * @return array Array of categories with depth info
     */
    public static function get_flat_tree(): array {
        $tree = self::get_tree();
        return self::flatten_tree($tree);
    }

    /**
     * Flatten tree to array with depth info.
     *
     * @param array $tree Tree structure
     * @return array Flat array
     */
    private static function flatten_tree(array $tree): array {
        $flat = [];
        foreach ($tree as $node) {
            $children = $node->children ?? [];
            unset($node->children);
            $flat[] = $node;
            if (!empty($children)) {
                $flat = array_merge($flat, self::flatten_tree($children));
            }
        }
        return $flat;
    }

    /**
     * Get categories for select menu.
     *
     * @param int|null $excludeid Category ID to exclude (and its children)
     * @return array Array of id => name with indentation
     */
    public static function get_menu(int $excludeid = null): array {
        $categories = self::get_flat_tree();
        $menu = [0 => get_string('toplevel', 'local_casospracticos')];

        foreach ($categories as $category) {
            if ($excludeid !== null && $category->id == $excludeid) {
                continue;
            }
            $indent = str_repeat('— ', $category->depth);
            $menu[$category->id] = $indent . $category->name;
        }

        return $menu;
    }

    /**
     * Create a new category.
     *
     * @param object $data Category data
     * @return int New category ID
     */
    public static function create(object $data): int {
        global $DB;

        $record = new \stdClass();
        $record->name = trim($data->name);
        $record->description = $data->description ?? '';
        $record->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
        $record->parent = $data->parent ?? 0;
        $record->sortorder = $data->sortorder ?? self::get_next_sortorder($record->parent);
        $record->contextid = $data->contextid ?? \context_system::instance()->id;
        $record->timecreated = time();
        $record->timemodified = time();

        $id = $DB->insert_record(self::TABLE, $record);
        self::invalidate_cache();
        return $id;
    }

    /**
     * Update an existing category.
     *
     * @param object $data Category data with id
     * @return bool Success
     */
    public static function update(object $data): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $data->id;
        $record->name = trim($data->name);
        $record->description = $data->description ?? '';
        $record->descriptionformat = $data->descriptionformat ?? FORMAT_HTML;
        $record->parent = $data->parent ?? 0;
        if (isset($data->sortorder)) {
            $record->sortorder = $data->sortorder;
        }
        $record->timemodified = time();

        $result = $DB->update_record(self::TABLE, $record);
        self::invalidate_cache();
        return $result;
    }

    /**
     * Delete a category.
     *
     * @param int $id Category ID
     * @return bool Success
     * @throws \moodle_exception If category has children or cases
     */
    public static function delete(int $id): bool {
        global $DB;

        // Check for children.
        if ($DB->record_exists(self::TABLE, ['parent' => $id])) {
            throw new \moodle_exception('categoryhaschildren', 'local_casospracticos');
        }

        // Check for cases.
        if ($DB->record_exists('local_cp_cases', ['categoryid' => $id])) {
            throw new \moodle_exception('categoryhascases', 'local_casospracticos');
        }

        $result = $DB->delete_records(self::TABLE, ['id' => $id]);
        self::invalidate_cache();
        return $result;
    }

    /**
     * Get the next sort order for a parent.
     *
     * @param int $parent Parent category ID
     * @return int Next sort order
     */
    private static function get_next_sortorder(int $parent): int {
        global $DB;
        $max = $DB->get_field(self::TABLE, 'MAX(sortorder)', ['parent' => $parent]);
        return ($max ?? 0) + 1;
    }

    /**
     * Count cases in a category.
     *
     * @param int $categoryid Category ID
     * @param bool $recursive Include subcategories
     * @return int Number of cases
     */
    public static function count_cases(int $categoryid, bool $recursive = false): int {
        global $DB;

        $count = $DB->count_records('local_cp_cases', ['categoryid' => $categoryid]);

        if ($recursive) {
            $children = self::get_all($categoryid);
            foreach ($children as $child) {
                $count += self::count_cases($child->id, true);
            }
        }

        return $count;
    }

    /**
     * Count cases for all categories in a single query (batch operation).
     *
     * @return array Associative array [categoryid => count]
     */
    public static function count_cases_all(): array {
        global $DB;

        $sql = "SELECT categoryid, COUNT(*) AS casecount
                  FROM {local_cp_cases}
              GROUP BY categoryid";
        $counts = $DB->get_records_sql($sql);

        $result = [];
        foreach ($counts as $row) {
            $result[$row->categoryid] = (int) $row->casecount;
        }

        return $result;
    }

    /**
     * Get flat tree with case counts included (optimized single query).
     *
     * @return array Array of categories with depth and casecount
     */
    public static function get_flat_tree_with_counts(): array {
        $categories = self::get_flat_tree();
        $counts = self::count_cases_all();

        foreach ($categories as $category) {
            $category->casecount = $counts[$category->id] ?? 0;
        }

        return $categories;
    }

    /**
     * Move a category to a new parent.
     *
     * @param int $id Category ID
     * @param int $newparent New parent ID
     * @return bool Success
     */
    public static function move(int $id, int $newparent): bool {
        global $DB;

        // Prevent moving to self or descendant.
        if ($id == $newparent || self::is_descendant($newparent, $id)) {
            return false;
        }

        return $DB->set_field(self::TABLE, 'parent', $newparent, ['id' => $id]);
    }

    /**
     * Check if a category is a descendant of another.
     *
     * @param int $categoryid Category to check
     * @param int $ancestorid Potential ancestor
     * @return bool True if descendant
     */
    private static function is_descendant(int $categoryid, int $ancestorid): bool {
        global $DB;

        $category = self::get($categoryid);
        if (!$category) {
            return false;
        }

        if ($category->parent == 0) {
            return false;
        }

        if ($category->parent == $ancestorid) {
            return true;
        }

        return self::is_descendant($category->parent, $ancestorid);
    }
}
