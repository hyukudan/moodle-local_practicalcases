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
     * Security note: all server-supplied values (statement, questiontext, answer,
     * question count) are inserted via textContent / createTextNode, never via
     * innerHTML or string concatenation.  This eliminates stored-XSS even though
     * get_case() returns PARAM_RAW fields without format_text() sanitisation.
     * TODO: the server-side fix is to run format_text() in the external API so
     * that rich-text fields arrive as clean HTML; at that point the statement and
     * questiontext nodes may safely switch to innerHTML.
     *
     * @param {Object} data Case data returned by local_casospracticos_get_case.
     * @return {HTMLElement}  The panel element, ready to be appended to the DOM.
     */
    function buildPreviewDom(data) {
        var panel = el('div', 'cp-preview-panel');

        // Statement.
        var stmtWrap = el('div', 'cp-preview-statement');
        stmtWrap.appendChild(el('h6', 'cp-preview-label', 'Enunciado'));
        var stmtBody = el('div', 'cp-preview-statement-body');
        stmtBody.textContent = data.statement || '';
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
                qText.appendChild(document.createTextNode(q.questiontext || ''));
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
                        // Answer text: server value — set via createTextNode (safe).
                        aDiv.appendChild(document.createTextNode(a.answer || ''));

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
