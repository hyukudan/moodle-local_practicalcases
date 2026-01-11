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
 * Case editor module for practical cases.
 *
 * @module     local_casospracticos/case_editor
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, Notification, Str, ModalFactory, ModalEvents) {

    /**
     * Case editor class.
     *
     * @param {number} caseId The case ID.
     */
    var CaseEditor = function(caseId) {
        this.caseId = caseId;
        this.container = $('.local-casospracticos-case-view[data-caseid="' + caseId + '"]');
        this.init();
    };

    /**
     * Initialize the editor.
     */
    CaseEditor.prototype.init = function() {
        this.bindEvents();
    };

    /**
     * Bind event handlers.
     */
    CaseEditor.prototype.bindEvents = function() {
        var self = this;

        // Drag and drop reordering of questions.
        this.container.find('.case-questions').sortable({
            items: '.question-item',
            handle: '.card-header',
            cursor: 'move',
            placeholder: 'question-placeholder card mb-3',
            update: function(event, ui) {
                self.handleQuestionReorder(ui.item);
            }
        });

        // Quick edit question text.
        this.container.on('dblclick', '.question-text', function(e) {
            if ($(this).find('textarea').length) {
                return;
            }
            self.enableInlineEdit($(this));
        });

        // Delete question confirmation.
        this.container.on('click', '.question-actions a[href*="deletequestion"]', function(e) {
            e.preventDefault();
            self.confirmDeleteQuestion($(this));
        });
    };

    /**
     * Handle question reordering via drag and drop.
     *
     * @param {jQuery} item The moved item.
     */
    CaseEditor.prototype.handleQuestionReorder = function(item) {
        var questionId = item.data('questionid');
        var newPosition = item.index() + 1;

        Ajax.call([{
            methodname: 'local_casospracticos_reorder_questions',
            args: {
                questionid: questionId,
                newposition: newPosition
            }
        }])[0].done(function(response) {
            if (response.success) {
                // Update question numbers.
                $('.question-item').each(function(index) {
                    $(this).find('.badge.bg-primary').text('#' + (index + 1));
                });
            }
        }).fail(Notification.exception);
    };

    /**
     * Enable inline editing of question text.
     *
     * @param {jQuery} element The element to edit.
     */
    CaseEditor.prototype.enableInlineEdit = function(element) {
        var self = this;
        var originalText = element.html();
        var questionId = element.closest('.question-item').data('questionid');

        var textarea = $('<textarea class="form-control" rows="3"></textarea>')
            .val(originalText.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''));

        element.html(textarea);
        textarea.focus().select();

        // Save on blur or enter.
        textarea.on('blur', function() {
            self.saveInlineEdit(element, questionId, textarea.val(), originalText);
        });

        textarea.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                textarea.blur();
            }
            if (e.key === 'Escape') {
                element.html(originalText);
            }
        });
    };

    /**
     * Save inline edit.
     *
     * @param {jQuery} element The element.
     * @param {number} questionId The question ID.
     * @param {string} newText The new text.
     * @param {string} originalText The original text.
     */
    CaseEditor.prototype.saveInlineEdit = function(element, questionId, newText, originalText) {
        if (newText.trim() === originalText.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '').trim()) {
            element.html(originalText);
            return;
        }

        Ajax.call([{
            methodname: 'local_casospracticos_update_question',
            args: {
                id: questionId,
                questiontext: newText.replace(/\n/g, '<br>')
            }
        }])[0].done(function(response) {
            if (response.success) {
                element.html(newText.replace(/\n/g, '<br>'));
                Notification.addNotification({
                    message: 'Pregunta actualizada',
                    type: 'success'
                });
            } else {
                element.html(originalText);
            }
        }).fail(function() {
            element.html(originalText);
            Notification.exception();
        });
    };

    /**
     * Confirm delete question.
     *
     * @param {jQuery} link The delete link.
     */
    CaseEditor.prototype.confirmDeleteQuestion = function(link) {
        var self = this;
        var questionItem = link.closest('.question-item');
        var questionId = questionItem.data('questionid');

        Str.get_strings([
            {key: 'deletequestion', component: 'local_casospracticos'},
            {key: 'confirm'},
            {key: 'yes'},
            {key: 'no'}
        ]).then(function(strings) {
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: strings[1],
                body: strings[0] + '?'
            });
        }).then(function(modal) {
            modal.setSaveButtonText(Str.get_string('yes'));
            modal.getRoot().on(ModalEvents.save, function() {
                self.deleteQuestion(questionId, questionItem);
            });
            modal.show();
        }).catch(Notification.exception);
    };

    /**
     * Delete a question.
     *
     * @param {number} questionId The question ID.
     * @param {jQuery} questionItem The question item element.
     */
    CaseEditor.prototype.deleteQuestion = function(questionId, questionItem) {
        Ajax.call([{
            methodname: 'local_casospracticos_delete_question',
            args: {id: questionId}
        }])[0].done(function(response) {
            if (response.success) {
                questionItem.fadeOut(300, function() {
                    $(this).remove();
                    // Update question numbers.
                    $('.question-item').each(function(index) {
                        $(this).find('.badge.bg-primary').text('#' + (index + 1));
                    });
                    // Update count.
                    var count = $('.question-item').length;
                    $('.case-questions h4 .badge').text(count);
                });
            }
        }).fail(Notification.exception);
    };

    return {
        /**
         * Initialize the case editor.
         *
         * @param {number} caseId The case ID.
         */
        init: function(caseId) {
            return new CaseEditor(caseId);
        }
    };
});
