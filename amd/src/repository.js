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
 * Repository module for practical cases AJAX calls.
 *
 * @module     local_casospracticos/repository
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {

    return {
        /**
         * Get all categories.
         *
         * @return {Promise}
         */
        getCategories: function() {
            return Ajax.call([{
                methodname: 'local_casospracticos_get_categories',
                args: {}
            }])[0];
        },

        /**
         * Get cases.
         *
         * @param {number} categoryId Category ID (0 for all).
         * @param {string} status Status filter.
         * @return {Promise}
         */
        getCases: function(categoryId, status) {
            return Ajax.call([{
                methodname: 'local_casospracticos_get_cases',
                args: {
                    categoryid: categoryId || 0,
                    status: status || ''
                }
            }])[0];
        },

        /**
         * Get a single case.
         *
         * @param {number} id Case ID.
         * @return {Promise}
         */
        getCase: function(id) {
            return Ajax.call([{
                methodname: 'local_casospracticos_get_case',
                args: {id: id}
            }])[0];
        },

        /**
         * Create a case.
         *
         * @param {Object} data Case data.
         * @return {Promise}
         */
        createCase: function(data) {
            return Ajax.call([{
                methodname: 'local_casospracticos_create_case',
                args: {
                    categoryid: data.categoryid,
                    name: data.name,
                    statement: data.statement,
                    status: data.status || 'draft',
                    difficulty: data.difficulty || 0
                }
            }])[0];
        },

        /**
         * Update a case.
         *
         * @param {Object} data Case data with id.
         * @return {Promise}
         */
        updateCase: function(data) {
            return Ajax.call([{
                methodname: 'local_casospracticos_update_case',
                args: {
                    id: data.id,
                    name: data.name || '',
                    statement: data.statement || '',
                    status: data.status || '',
                    categoryid: data.categoryid || 0
                }
            }])[0];
        },

        /**
         * Delete a case.
         *
         * @param {number} id Case ID.
         * @return {Promise}
         */
        deleteCase: function(id) {
            return Ajax.call([{
                methodname: 'local_casospracticos_delete_case',
                args: {id: id}
            }])[0];
        },

        /**
         * Get questions for a case.
         *
         * @param {number} caseId Case ID.
         * @return {Promise}
         */
        getQuestions: function(caseId) {
            return Ajax.call([{
                methodname: 'local_casospracticos_get_questions',
                args: {caseid: caseId}
            }])[0];
        },

        /**
         * Create a question.
         *
         * @param {Object} data Question data.
         * @return {Promise}
         */
        createQuestion: function(data) {
            return Ajax.call([{
                methodname: 'local_casospracticos_create_question',
                args: {
                    caseid: data.caseid,
                    questiontext: data.questiontext,
                    qtype: data.qtype,
                    defaultmark: data.defaultmark || 1.0,
                    answers: data.answers || []
                }
            }])[0];
        },

        /**
         * Update a question.
         *
         * @param {Object} data Question data with id.
         * @return {Promise}
         */
        updateQuestion: function(data) {
            return Ajax.call([{
                methodname: 'local_casospracticos_update_question',
                args: {
                    id: data.id,
                    questiontext: data.questiontext || '',
                    defaultmark: data.defaultmark || 0
                }
            }])[0];
        },

        /**
         * Delete a question.
         *
         * @param {number} id Question ID.
         * @return {Promise}
         */
        deleteQuestion: function(id) {
            return Ajax.call([{
                methodname: 'local_casospracticos_delete_question',
                args: {id: id}
            }])[0];
        },

        /**
         * Reorder questions.
         *
         * @param {number} questionId Question ID.
         * @param {number} newPosition New position.
         * @return {Promise}
         */
        reorderQuestions: function(questionId, newPosition) {
            return Ajax.call([{
                methodname: 'local_casospracticos_reorder_questions',
                args: {
                    questionid: questionId,
                    newposition: newPosition
                }
            }])[0];
        },

        /**
         * Insert case into quiz.
         *
         * @param {number} caseId Case ID.
         * @param {number} quizId Quiz ID.
         * @param {Object} options Options.
         * @return {Promise}
         */
        insertIntoQuiz: function(caseId, quizId, options) {
            return Ajax.call([{
                methodname: 'local_casospracticos_insert_into_quiz',
                args: {
                    caseid: caseId,
                    quizid: quizId,
                    randomcount: options.randomCount || 0,
                    includestatement: options.includeStatement !== false,
                    shuffle: options.shuffle || false
                }
            }])[0];
        },

        /**
         * Get available quizzes.
         *
         * @param {number} courseId Course ID.
         * @return {Promise}
         */
        getAvailableQuizzes: function(courseId) {
            return Ajax.call([{
                methodname: 'local_casospracticos_get_available_quizzes',
                args: {courseid: courseId}
            }])[0];
        },

        /**
         * Save practice responses (auto-save).
         *
         * @param {number} attemptId Attempt ID.
         * @param {Object} responses Responses data.
         * @return {Promise}
         */
        savePracticeResponses: function(attemptId, responses) {
            return Ajax.call([{
                methodname: 'local_casospracticos_save_practice_responses',
                args: {
                    attemptid: attemptId,
                    responses: JSON.stringify(responses)
                }
            }])[0];
        }
    };
});
