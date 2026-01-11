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

namespace local_casospracticos\search;

/**
 * Search area for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_search extends \core_search\base {

    /**
     * Returns the document associated with this case.
     *
     * @param \stdClass $record
     * @param array $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        $context = \context_system::instance();

        // Get category for additional context.
        global $DB;
        $category = $DB->get_record('local_cp_categories', ['id' => $record->categoryid]);

        $doc = \core_search\document_factory::instance(
            $record->id,
            $this->componentname,
            $this->areaname
        );

        $doc->set('title', content_to_text($record->name, false));
        $doc->set('content', content_to_text($record->statement, $record->statementformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', SITEID);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Add category name as description.
        if ($category) {
            $doc->set('description1', $category->name);
        }

        // Add status and difficulty as description2.
        $doc->set('description2', get_string('status_' . $record->status, 'local_casospracticos') .
            ' | ' . get_string('difficulty', 'local_casospracticos') . ': ' . $record->difficulty);

        return $doc;
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Return the context info required to index files for a document.
     *
     * @param \core_search\document $doc
     * @return array
     */
    public function get_search_fileareas() {
        return ['statement'];
    }

    /**
     * Return context of the document.
     *
     * @param \core_search\document $doc
     * @return \context
     */
    public function get_doc_context($doc) {
        return \context_system::instance();
    }

    /**
     * Returns URL to document.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        return new \moodle_url('/local/casospracticos/case_view.php', ['id' => $doc->get('itemid')]);
    }

    /**
     * Returns URL to context.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        return new \moodle_url('/local/casospracticos/index.php');
    }

    /**
     * Check access to document.
     *
     * @param int $id Document ID.
     * @return int
     */
    public function check_access($id) {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['id' => $id]);
        if (!$case) {
            return \core_search\manager::ACCESS_DELETED;
        }

        $context = \context_system::instance();
        if (!has_capability('local/casospracticos:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Only published cases are searchable for regular users.
        if ($case->status !== 'published') {
            if (!has_capability('local/casospracticos:edit', $context)) {
                return \core_search\manager::ACCESS_DENIED;
            }
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns recordset of all cases for indexing.
     *
     * @param int $modifiedfrom Modified from timestamp.
     * @param \context $context Context to restrict.
     * @return \moodle_recordset|null
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        $sql = "SELECT c.*
                  FROM {local_cp_cases} c
                 WHERE c.timemodified >= ?
              ORDER BY c.timemodified ASC";

        return $DB->get_recordset_sql($sql, [$modifiedfrom]);
    }

    /**
     * Returns the area name.
     *
     * @return string
     */
    public static function get_search_area_name() {
        return get_string('search:case', 'local_casospracticos');
    }

    /**
     * Returns the component name.
     *
     * @return string
     */
    public function get_component_name() {
        return 'local_casospracticos';
    }

    /**
     * Returns the visible name for this area.
     *
     * @param bool $lazyload
     * @return string
     */
    public function get_visible_name($lazyload = false) {
        return get_string('search:case', 'local_casospracticos');
    }
}
