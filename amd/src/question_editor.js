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
 * Question editor module for practical cases.
 *
 * @module     local_casospracticos/question_editor
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/str', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, Notification, Templates, Str, ModalFactory, ModalEvents) {

    /**
     * Question editor class.
     *
     * @param {number} caseId The case ID.
     */
    var QuestionEditor = function(caseId) {
        this.caseId = caseId;
        this.modal = null;
        this.answerCount = 0;
        // Monotonic counter to guarantee unique answer-row ids across dynamically added rows.
        this.answerRowSeq = 0;

        // Pre-loaded localised strings, with English fallbacks until they resolve.
        this.strings = {
            qtypeMultichoice: 'Multiple choice',
            qtypeTruefalse: 'True/False',
            qtypeShortanswer: 'Short answer',
            editQuestion: 'Edit question',
            newQuestion: 'New question',
            trueValue: 'True',
            falseValue: 'False',
            correctAnswer: 'Correct',
            incorrectAnswer: 'Incorrect',
            saveFailed: 'The question could not be saved. Please try again.',
            questionTextRequired: 'The question text is required',
            minAnswers: 'At least 2 answers are required',
            minCorrectAnswer: 'At least one correct answer is required'
        };

        // Kick off string loading immediately; open() awaits this so labels and
        // the modal title are localised before the form renders.
        var self = this;
        this.stringsReady = Str.get_strings([
            {key: 'qtype_multichoice', component: 'local_casospracticos'},
            {key: 'qtype_truefalse', component: 'local_casospracticos'},
            {key: 'qtype_shortanswer', component: 'local_casospracticos'},
            {key: 'editquestion', component: 'local_casospracticos'},
            {key: 'newquestion', component: 'local_casospracticos'},
            {key: 'js:truevalue', component: 'local_casospracticos'},
            {key: 'js:falsevalue', component: 'local_casospracticos'},
            {key: 'correctanswer', component: 'local_casospracticos'},
            {key: 'incorrectanswer', component: 'local_casospracticos'},
            {key: 'js:savequestionfailed', component: 'local_casospracticos'},
            {key: 'js:questiontextrequired', component: 'local_casospracticos'},
            {key: 'js:minanswers', component: 'local_casospracticos'},
            {key: 'js:mincorrectanswer', component: 'local_casospracticos'}
        ]).then(function(strings) {
            self.strings = {
                qtypeMultichoice: strings[0],
                qtypeTruefalse: strings[1],
                qtypeShortanswer: strings[2],
                editQuestion: strings[3],
                newQuestion: strings[4],
                trueValue: strings[5],
                falseValue: strings[6],
                correctAnswer: strings[7],
                incorrectAnswer: strings[8],
                saveFailed: strings[9],
                questionTextRequired: strings[10],
                minAnswers: strings[11],
                minCorrectAnswer: strings[12]
            };
            return self.strings;
        }).catch(function() {
            // Keep English defaults on failure.
            return self.strings;
        });
    };

    /**
     * Open the question editor modal.
     *
     * @param {number|null} questionId The question ID (null for new).
     * @return {Promise}
     */
    QuestionEditor.prototype.open = function(questionId) {
        var self = this;

        // Wait for localised strings before building labels/title.
        return this.stringsReady.then(function() {
            var context = {
                caseid: self.caseId,
                uniqid: 'qe_' + Date.now(),
                qtypes: [
                    {value: 'multichoice', label: self.strings.qtypeMultichoice, selected: true},
                    {value: 'truefalse', label: self.strings.qtypeTruefalse, selected: false},
                    {value: 'shortanswer', label: self.strings.qtypeShortanswer, selected: false}
                ]
            };

            // If editing, load question data.
            var dataPromise;
            if (questionId) {
                dataPromise = self.loadQuestion(questionId).then(function(question) {
                    context.question = question;
                    // Update selected qtype.
                    context.qtypes.forEach(function(qt) {
                        qt.selected = (qt.value === question.qtype);
                    });
                    return context;
                });
            } else {
                dataPromise = Promise.resolve(context);
            }

            return dataPromise;
        }).then(function(ctx) {
            return Templates.render('local_casospracticos/question_form', ctx);
        }).then(function(html) {
            return ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: questionId ? self.strings.editQuestion : self.strings.newQuestion,
                body: html,
                large: true
            });
        }).then(function(modal) {
            self.modal = modal;
            self.bindModalEvents();
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Load question data via AJAX.
     *
     * @param {number} questionId The question ID.
     * @return {Promise}
     */
    QuestionEditor.prototype.loadQuestion = function(questionId) {
        return Ajax.call([{
            methodname: 'local_casospracticos_get_questions',
            args: {caseid: this.caseId}
        }])[0].then(function(questions) {
            for (var i = 0; i < questions.length; i++) {
                if (questions[i].id === questionId) {
                    return questions[i];
                }
            }
            throw new Error('Question not found');
        });
    };

    /**
     * Bind modal events.
     */
    QuestionEditor.prototype.bindModalEvents = function() {
        var self = this;
        var root = this.modal.getRoot();

        // Form submission.
        root.on('submit', 'form', function(e) {
            e.preventDefault();
            self.saveQuestion($(this));
        });

        // Cancel button.
        root.on('click', '[data-action="cancel"]', function() {
            self.modal.hide();
        });

        // Add answer button.
        root.on('click', '[id^="add-answer-"]', function() {
            self.addAnswerRow();
        });

        // Remove answer button.
        root.on('click', '.remove-answer', function() {
            $(this).closest('.answer-row').remove();
        });

        // Question type change - show/hide answer options.
        root.on('change', '[id^="qtype-"]', function() {
            self.handleQtypeChange($(this).val());
        });
    };

    /**
     * Add a new answer row.
     */
    QuestionEditor.prototype.addAnswerRow = function() {
        var container = this.modal.getRoot().find('[id^="answers-container-"]');

        // Supply a unique uniqid/index so the rendered row gets unique element ids
        // (answer-text-<uniqid>-<index>) instead of empty "answer-text--".
        this.answerRowSeq++;
        var context = {
            uniqid: 'qe_new_' + Date.now() + '_' + this.answerRowSeq,
            index: this.answerRowSeq
        };

        Templates.render('local_casospracticos/answer_row', context).then(function(html) {
            container.append(html);
        }).catch(Notification.exception);
    };

    /**
     * Handle question type change.
     *
     * @param {string} qtype The question type.
     */
    QuestionEditor.prototype.handleQtypeChange = function(qtype) {
        var container = this.modal.getRoot().find('.answers-section');
        var s = this.strings;

        if (qtype === 'truefalse') {
            // Replace with true/false answers.
            container.find('.answers-container').html(
                '<div class="answer-row card mb-2"><div class="card-body py-2">' +
                '<div class="row g-2">' +
                '<div class="col-md-8"><input type="text" class="form-control form-control-sm" ' +
                'name="answer_text[]" value="' + s.trueValue + '" readonly></div>' +
                '<div class="col-md-4"><select class="form-select form-select-sm" name="answer_fraction[]">' +
                '<option value="1.0">' + s.correctAnswer + '</option>' +
                '<option value="0.0" selected>' + s.incorrectAnswer + '</option></select></div>' +
                '</div></div></div>' +
                '<div class="answer-row card mb-2"><div class="card-body py-2">' +
                '<div class="row g-2">' +
                '<div class="col-md-8"><input type="text" class="form-control form-control-sm" ' +
                'name="answer_text[]" value="' + s.falseValue + '" readonly></div>' +
                '<div class="col-md-4"><select class="form-select form-select-sm" name="answer_fraction[]">' +
                '<option value="1.0">' + s.correctAnswer + '</option>' +
                '<option value="0.0" selected>' + s.incorrectAnswer + '</option></select></div>' +
                '</div></div></div>'
            );
            container.find('[id^="add-answer-"]').hide();
        } else {
            container.find('[id^="add-answer-"]').show();
        }
    };

    /**
     * Save the question.
     *
     * @param {jQuery} form The form element.
     */
    QuestionEditor.prototype.saveQuestion = function(form) {
        var self = this;
        var data = this.collectFormData(form);

        if (!this.validateForm(data)) {
            return;
        }

        var methodname = data.id ? 'local_casospracticos_update_question' : 'local_casospracticos_create_question';
        var args = data.id ? {
            id: data.id,
            questiontext: data.questiontext,
            defaultmark: data.defaultmark
        } : {
            caseid: data.caseid,
            questiontext: data.questiontext,
            qtype: data.qtype,
            defaultmark: data.defaultmark,
            answers: data.answers
        };

        Ajax.call([{
            methodname: methodname,
            args: args
        }])[0].done(function(response) {
            if (response.success) {
                self.modal.hide();
                // Reload page to show changes.
                window.location.reload();
            } else {
                Notification.addNotification({
                    message: self.strings.saveFailed,
                    type: 'error'
                });
            }
        }).fail(Notification.exception);
    };

    /**
     * Collect form data.
     *
     * @param {jQuery} form The form.
     * @return {Object} Form data.
     */
    QuestionEditor.prototype.collectFormData = function(form) {
        var data = {
            caseid: parseInt(form.find('[name="caseid"]').val(), 10),
            id: parseInt(form.find('[name="id"]').val(), 10) || null,
            qtype: form.find('[name="qtype"]').val(),
            questiontext: form.find('[name="questiontext"]').val(),
            defaultmark: parseFloat(form.find('[name="defaultmark"]').val()) || 1.0,
            shuffleanswers: parseInt(form.find('[name="shuffleanswers"]').val(), 10),
            generalfeedback: form.find('[name="generalfeedback"]').val(),
            answers: []
        };

        // Collect answers.
        form.find('.answer-row').each(function() {
            var text = $(this).find('[name="answer_text[]"]').val();
            if (text && text.trim()) {
                data.answers.push({
                    answer: text.trim(),
                    fraction: parseFloat($(this).find('[name="answer_fraction[]"]').val()) || 0,
                    feedback: $(this).find('[name="answer_feedback[]"]').val() || ''
                });
            }
        });

        return data;
    };

    /**
     * Validate form data.
     *
     * @param {Object} data Form data.
     * @return {boolean} Valid or not.
     */
    QuestionEditor.prototype.validateForm = function(data) {
        if (!data.questiontext || !data.questiontext.trim()) {
            Notification.addNotification({
                message: this.strings.questionTextRequired,
                type: 'error'
            });
            return false;
        }

        if (data.answers.length < 2 && data.qtype !== 'shortanswer') {
            Notification.addNotification({
                message: this.strings.minAnswers,
                type: 'error'
            });
            return false;
        }

        // Check at least one correct answer.
        var hasCorrect = data.answers.some(function(a) {
            return a.fraction > 0;
        });
        if (!hasCorrect && data.qtype !== 'shortanswer') {
            Notification.addNotification({
                message: this.strings.minCorrectAnswer,
                type: 'error'
            });
            return false;
        }

        return true;
    };

    return {
        /**
         * Create a new question editor instance.
         *
         * @param {number} caseId The case ID.
         * @return {QuestionEditor}
         */
        create: function(caseId) {
            return new QuestionEditor(caseId);
        },

        /**
         * Open editor for new question.
         *
         * @param {number} caseId The case ID.
         */
        newQuestion: function(caseId) {
            var editor = new QuestionEditor(caseId);
            editor.open(null);
        },

        /**
         * Open editor for existing question.
         *
         * @param {number} caseId The case ID.
         * @param {number} questionId The question ID.
         */
        editQuestion: function(caseId, questionId) {
            var editor = new QuestionEditor(caseId);
            editor.open(questionId);
        }
    };
});
