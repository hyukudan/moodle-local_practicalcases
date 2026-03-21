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
 * Inline case preview for the case listing page.
 *
 * @module     local_casospracticos/case_preview
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var cache = {};

    /**
     * Fetch case data via AJAX.
     */
    function fetchCase(caseId) {
        if (cache[caseId]) {
            return Promise.resolve(cache[caseId]);
        }
        return Ajax.call([{
            methodname: 'local_casospracticos_get_case',
            args: {id: caseId}
        }])[0].then(function(data) {
            cache[caseId] = data;
            return data;
        });
    }

    /**
     * Build the preview HTML for a case.
     */
    function buildPreviewHtml(data) {
        var html = '<div class="cp-preview-panel">';

        // Statement.
        html += '<div class="cp-preview-statement">';
        html += '<h6 class="cp-preview-label">Enunciado</h6>';
        html += '<div class="cp-preview-statement-body">' + data.statement + '</div>';
        html += '</div>';

        // Questions.
        if (data.questions && data.questions.length > 0) {
            html += '<div class="cp-preview-questions">';
            html += '<h6 class="cp-preview-label">Preguntas (' + data.questions.length + ')</h6>';

            data.questions.forEach(function(q, idx) {
                html += '<div class="cp-preview-question">';
                html += '<div class="cp-preview-qtext"><strong>' + (idx + 1) + '.</strong> ' + q.questiontext + '</div>';
                html += '<div class="cp-preview-answers">';

                if (q.answers) {
                    var letters = ['a', 'b', 'c', 'd'];
                    q.answers.forEach(function(a, ai) {
                        var isCorrect = a.fraction > 0;
                        var cls = isCorrect ? 'cp-preview-answer cp-correct' : 'cp-preview-answer';
                        html += '<div class="' + cls + '">';
                        html += '<span class="cp-answer-letter">' + (letters[ai] || String.fromCharCode(97 + ai)) + ')</span> ';
                        html += a.answer;
                        if (isCorrect) {
                            html += ' <span class="cp-correct-badge">&#10003;</span>';
                        }
                        html += '</div>';
                    });
                }

                html += '</div>'; // answers
                html += '</div>'; // question
            });

            html += '</div>'; // questions
        }

        html += '</div>'; // panel
        return html;
    }

    /**
     * Toggle preview for a case row.
     */
    function togglePreview(btn) {
        var caseId = parseInt(btn.getAttribute('data-caseid'), 10);
        var row = btn.closest('tr');
        var nextRow = row.nextElementSibling;

        // If already open, close it.
        if (nextRow && nextRow.classList.contains('cp-preview-row')) {
            nextRow.remove();
            btn.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
            return;
        }

        // Close any other open preview.
        var table = row.closest('table');
        var openRows = table.querySelectorAll('.cp-preview-row');
        openRows.forEach(function(r) { r.remove(); });
        var activeBtns = table.querySelectorAll('.cp-preview-btn.active');
        activeBtns.forEach(function(b) {
            b.classList.remove('active');
            b.setAttribute('aria-expanded', 'false');
        });

        // Show loading.
        btn.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
        var colspan = row.children.length;
        var previewRow = document.createElement('tr');
        previewRow.className = 'cp-preview-row';
        var td = document.createElement('td');
        td.colSpan = colspan;
        td.innerHTML = '<div class="cp-preview-loading"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando...</div>'; // eslint-disable-line
        previewRow.appendChild(td);
        row.after(previewRow);

        fetchCase(caseId).then(function(data) {
            td.innerHTML = buildPreviewHtml(data); // eslint-disable-line
        }).catch(function(err) {
            td.innerHTML = '<div class="alert alert-danger py-2 mb-0">Error al cargar: ' + (err.message || err) + '</div>'; // eslint-disable-line
            Notification.exception(err);
        });
    }

    return {
        init: function() {
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.cp-preview-btn');
                if (btn) {
                    e.preventDefault();
                    togglePreview(btn);
                }
            });
        }
    };
});
