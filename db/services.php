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
 * Web service definitions for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Categories.
    'local_casospracticos_get_categories' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'get_categories',
        'description' => 'Get all practical case categories',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:view',
    ],

    // Cases.
    'local_casospracticos_get_cases' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'get_cases',
        'description' => 'Get practical cases, optionally filtered by category',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:view',
    ],
    'local_casospracticos_get_case' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'get_case',
        'description' => 'Get a single practical case with all questions',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:view',
    ],
    'local_casospracticos_create_case' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'create_case',
        'description' => 'Create a new practical case',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:create',
    ],
    'local_casospracticos_update_case' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'update_case',
        'description' => 'Update an existing practical case',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:edit',
    ],
    'local_casospracticos_delete_case' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'delete_case',
        'description' => 'Delete a practical case',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:delete',
    ],

    // Questions.
    'local_casospracticos_get_questions' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'get_questions',
        'description' => 'Get questions for a case',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:view',
    ],
    'local_casospracticos_create_question' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'create_question',
        'description' => 'Create a new question in a case',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:edit',
    ],
    'local_casospracticos_update_question' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'update_question',
        'description' => 'Update a question',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:edit',
    ],
    'local_casospracticos_delete_question' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'delete_question',
        'description' => 'Delete a question',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:edit',
    ],
    'local_casospracticos_reorder_questions' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'reorder_questions',
        'description' => 'Reorder questions in a case',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:edit',
    ],

    // Quiz integration.
    'local_casospracticos_insert_into_quiz' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'insert_into_quiz',
        'description' => 'Insert a case into a quiz',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:insertquiz',
    ],
    'local_casospracticos_get_available_quizzes' => [
        'classname' => 'local_casospracticos\external\api',
        'methodname' => 'get_available_quizzes',
        'description' => 'Get available quizzes for a course',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/casospracticos:insertquiz',
    ],
];

// Define the service.
$services = [
    'Casos Prácticos API' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_casospracticos',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
