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
 * Database upgrade steps for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_casospracticos upgrade from the given old version.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool Success.
 */
function xmldb_local_casospracticos_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026011102) {
        // Add version field to cases table.
        $table = new xmldb_table('local_cp_cases');
        $field = new xmldb_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'createdby');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add new indexes for performance.
        $index = new xmldb_index('difficulty', XMLDB_INDEX_NOTUNIQUE, ['difficulty']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('cat_status_time', XMLDB_INDEX_NOTUNIQUE, ['categoryid', 'status', 'timemodified']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Create audit log table.
        $table = new xmldb_table('local_cp_audit_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('objecttype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('changes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('objecttype_objectid', XMLDB_INDEX_NOTUNIQUE, ['objecttype', 'objectid']);
        $table->add_index('userid_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        $table->add_index('action', XMLDB_INDEX_NOTUNIQUE, ['action']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create reviews table for workflow.
        $table = new xmldb_table('local_cp_reviews');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('caseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('comments', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('caseid', XMLDB_KEY_FOREIGN, ['caseid'], 'local_cp_cases', ['id']);
        $table->add_key('reviewerid', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);

        $table->add_index('caseid_status', XMLDB_INDEX_NOTUNIQUE, ['caseid', 'status']);
        $table->add_index('reviewerid_status', XMLDB_INDEX_NOTUNIQUE, ['reviewerid', 'status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create usage tracking table.
        $table = new xmldb_table('local_cp_usage');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('caseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('views', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('insertions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastused', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('caseid', XMLDB_KEY_FOREIGN, ['caseid'], 'local_cp_cases', ['id']);

        $table->add_index('caseid_quizid', XMLDB_INDEX_UNIQUE, ['caseid', 'quizid']);
        $table->add_index('views', XMLDB_INDEX_NOTUNIQUE, ['views']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add index to categories.
        $table = new xmldb_table('local_cp_categories');
        $index = new xmldb_index('ctx_parent_sort', XMLDB_INDEX_NOTUNIQUE, ['contextid', 'parent', 'sortorder']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add index to questions.
        $table = new xmldb_table('local_cp_questions');
        $index = new xmldb_index('qtype', XMLDB_INDEX_NOTUNIQUE, ['qtype']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026011102, 'local', 'casospracticos');
    }

    return true;
}
