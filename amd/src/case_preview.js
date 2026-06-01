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
     * Create a DOM element with a CSS class and set its text content safely.
     * Using textContent prevents XSS: no raw server values are ever passed to innerHTML.
     *
     * @param {string} tag       HTML tag name.
     * @param {string} className CSS class(es) to set on the element.
     * @param {string} [text]    Optional plain-text content (set via textContent).
     * @return {HTMLElement}
     */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    /**
     * Build the preview DOM fragment for a case.
     *
     * Security note: the rich-text fields statement, questiontext and answer are
     * run through format_text() server-side (see api::format_richtext()), so they
     * arrive as cleaned, render-ready HTML and are safely assigned via innerHTML
     * to preserve their formatting.  Computed/local values (question count, answer
     * letters, the correct-answer badge) are plain and stay as textContent /
     * createTextNode.  No field that the server does NOT format_text() is ever
     * passed to innerHTML.
     *
     * @param {Object} data Case data returned by local_casospracticos_get_case.
     * @return {HTMLElement}  The panel element, ready to be appended to the DOM.
     */
    function buildPreviewDom(data) {
        var panel = el('div', 'cp-preview-panel');

        // Statement: server-format_text'd HTML -> safe to render via innerHTML.
        var stmtWrap = el('div', 'cp-preview-statement');
        stmtWrap.appendChild(el('h6', 'cp-preview-label', 'Enunciado'));
        var stmtBody = el('div', 'cp-preview-statement-body');
        stmtBody.innerHTML = data.statement || ''; // eslint-disable-line
        stmtWrap.appendChild(stmtBody);
        panel.appendChild(stmtWrap);

        // Questions.
        if (data.questions && data.questions.length > 0) {
            var qWrap = el('div', 'cp-preview-questions');
            var qLabel = el('h6', 'cp-preview-label');
            // Safe: question count is a number from the array, not from the server.
            qLabel.textContent = 'Preguntas (' + data.questions.length + ')';
            qWrap.appendChild(qLabel);

            data.questions.forEach(function(q, idx) {
                var qDiv = el('div', 'cp-preview-question');

                // Question text: "N. <questiontext>"
                var qText = el('div', 'cp-preview-qtext');
                var numStrong = document.createElement('strong');
                // idx+1 is a calculated integer, not server data.
                numStrong.textContent = (idx + 1) + '.';
                qText.appendChild(numStrong);
                qText.appendChild(document.createTextNode(' '));
                // Question text: server-format_text'd HTML -> safe via innerHTML.
                var qTextBody = document.createElement('span');
                qTextBody.innerHTML = q.questiontext || ''; // eslint-disable-line
                qText.appendChild(qTextBody);
                qDiv.appendChild(qText);

                // Answers.
                var aWrap = el('div', 'cp-preview-answers');
                if (q.answers) {
                    var letters = ['a', 'b', 'c', 'd'];
                    q.answers.forEach(function(a, ai) {
                        var isCorrect = a.fraction > 0;
                        var aDiv = el('div', isCorrect ? 'cp-preview-answer cp-correct' : 'cp-preview-answer');

                        // Letter label is a calculated value, not server data.
                        var letter = letters[ai] || String.fromCharCode(97 + ai);
                        aDiv.appendChild(el('span', 'cp-answer-letter', letter + ')'));
                        aDiv.appendChild(document.createTextNode(' '));
                        // Answer text: server-format_text'd HTML -> safe via innerHTML.
                        var aText = document.createElement('span');
                        aText.className = 'cp-answer-text';
                        aText.innerHTML = a.answer || ''; // eslint-disable-line
                        aDiv.appendChild(aText);

                        if (isCorrect) {
                            var badge = el('span', 'cp-correct-badge');
                            badge.textContent = '✓'; // ✓
                            aDiv.appendChild(document.createTextNode(' '));
                            aDiv.appendChild(badge);
                        }
                        aWrap.appendChild(aDiv);
                    });
                }
                qDiv.appendChild(aWrap);
                qWrap.appendChild(qDiv);
            });

            panel.appendChild(qWrap);
        }

        return panel;
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
            // Safe: replace loading markup with DOM-constructed preview.
            // No server value is passed to innerHTML.
            td.innerHTML = ''; // eslint-disable-line
            td.appendChild(buildPreviewDom(data));
        }).catch(function(err) {
            // Safe: err.message is set via textContent, not innerHTML.
            var errDiv = el('div', 'alert alert-danger py-2 mb-0');
            errDiv.appendChild(document.createTextNode('Error al cargar: '));
            errDiv.appendChild(document.createTextNode(
                (err && err.message) ? err.message : String(err)
            ));
            td.innerHTML = ''; // eslint-disable-line
            td.appendChild(errDiv);
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
