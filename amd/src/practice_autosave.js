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
 * Practice auto-save module for timed practice mode.
 *
 * Auto-saves responses every 30 seconds to prevent data loss if:
 * - Browser crashes
 * - Network disconnection
 * - Accidental page close
 *
 * @module     local_casospracticos/practice_autosave
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_casospracticos/repository', 'core/notification', 'core/str'], function(Repository, Notification, Str) {

    /** @var {number} Auto-save interval in milliseconds (30 seconds) */
    var AUTOSAVE_INTERVAL = 30000;

    /** @var {number} intervalId for the auto-save timer */
    var intervalId = null;

    /** @var {number} attemptId The current attempt ID */
    var attemptId = null;

    /** @var {string} formSelector CSS selector for the practice form */
    var formSelector = null;

    /** @var {Object} lastSavedResponses Track what was last saved to avoid redundant saves */
    var lastSavedResponses = null;

    /** @var {HTMLElement} statusIndicator Status indicator element */
    var statusIndicator = null;

    /**
     * Initialize the auto-save functionality.
     *
     * @param {Object} config Configuration object
     * @param {number} config.attemptId The timed attempt ID
     * @param {string} config.formSelector CSS selector for the form
     */
    var init = function(config) {
        attemptId = config.attemptId;
        formSelector = config.formSelector || 'form';

        // Create status indicator.
        createStatusIndicator();

        // Start auto-save interval.
        startAutoSave();

        // Also save on form input changes (debounced).
        var form = document.querySelector(formSelector);
        if (form) {
            form.addEventListener('change', debounce(function() {
                saveResponses();
            }, 2000));
        }

        // Save before page unload.
        window.addEventListener('beforeunload', function() {
            saveResponsesSync();
        });

        // Save when visibility changes (tab switch).
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                saveResponses();
            }
        });
    };

    /**
     * Create the status indicator element.
     */
    var createStatusIndicator = function() {
        statusIndicator = document.createElement('div');
        statusIndicator.id = 'autosave-status';
        statusIndicator.className = 'autosave-status';
        statusIndicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; padding: 8px 16px; ' +
            'border-radius: 4px; font-size: 12px; z-index: 9999; transition: opacity 0.3s; opacity: 0;';
        document.body.appendChild(statusIndicator);
    };

    /**
     * Show status message.
     *
     * @param {string} message Message to show
     * @param {string} type Type: 'saving', 'saved', 'error'
     */
    var showStatus = function(message, type) {
        if (!statusIndicator) {
            return;
        }

        var bgColor = '#6c757d'; // Default gray.
        if (type === 'saved') {
            bgColor = '#28a745'; // Green.
        } else if (type === 'error') {
            bgColor = '#dc3545'; // Red.
        } else if (type === 'saving') {
            bgColor = '#007bff'; // Blue.
        }

        statusIndicator.style.backgroundColor = bgColor;
        statusIndicator.style.color = '#fff';
        statusIndicator.textContent = message;
        statusIndicator.style.opacity = '1';

        // Hide after 3 seconds for success/error messages.
        if (type !== 'saving') {
            setTimeout(function() {
                statusIndicator.style.opacity = '0';
            }, 3000);
        }
    };

    /**
     * Start the auto-save interval.
     */
    var startAutoSave = function() {
        if (intervalId) {
            clearInterval(intervalId);
        }

        intervalId = setInterval(function() {
            saveResponses();
        }, AUTOSAVE_INTERVAL);
    };

    /**
     * Stop the auto-save interval.
     */
    var stopAutoSave = function() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    /**
     * Collect current responses from the form.
     *
     * @return {Object} Responses keyed by question ID
     */
    var collectResponses = function() {
        var form = document.querySelector(formSelector);
        if (!form) {
            return {};
        }

        var responses = {};
        var formData = new FormData(form);

        // Process form data into a structured format.
        for (var pair of formData.entries()) {
            var name = pair[0];
            var value = pair[1];

            // Skip sesskey.
            if (name === 'sesskey') {
                continue;
            }

            // Handle question responses (q123, q123[], etc.).
            var match = name.match(/^q(\d+)(\[\])?$/);
            if (match) {
                var questionId = match[1];
                var isArray = match[2] === '[]';

                if (isArray) {
                    if (!responses[questionId]) {
                        responses[questionId] = [];
                    }
                    responses[questionId].push(value);
                } else {
                    responses[questionId] = value;
                }
            }

            // Handle matching questions (q123_456).
            var matchingMatch = name.match(/^q(\d+)_(\d+)$/);
            if (matchingMatch) {
                var qid = matchingMatch[1];
                var subqid = matchingMatch[2];

                if (!responses[qid]) {
                    responses[qid] = {};
                }
                responses[qid][subqid] = value;
            }
        }

        return responses;
    };

    /**
     * Check if responses have changed since last save.
     *
     * @param {Object} currentResponses Current responses
     * @return {boolean} True if changed
     */
    var hasChanged = function(currentResponses) {
        if (lastSavedResponses === null) {
            return true;
        }
        return JSON.stringify(currentResponses) !== JSON.stringify(lastSavedResponses);
    };

    /**
     * Save responses via AJAX.
     */
    var saveResponses = function() {
        var responses = collectResponses();

        // Skip if no changes.
        if (!hasChanged(responses)) {
            return;
        }

        // Skip if empty.
        if (Object.keys(responses).length === 0) {
            return;
        }

        Str.get_string('saving', 'admin').then(function(str) {
            showStatus(str + '...', 'saving');
        }).catch(function() {
            showStatus('Saving...', 'saving');
        });

        Repository.savePracticeResponses(attemptId, responses)
            .then(function(result) {
                if (result.success) {
                    lastSavedResponses = responses;
                    Str.get_string('changessaved', 'moodle').then(function(str) {
                        showStatus(str, 'saved');
                    }).catch(function() {
                        showStatus('Saved', 'saved');
                    });
                } else {
                    showStatus('Auto-save failed', 'error');
                }
            })
            .catch(function() {
                showStatus('Auto-save error', 'error');
            });
    };

    /**
     * Save responses synchronously (for beforeunload).
     */
    var saveResponsesSync = function() {
        var responses = collectResponses();

        if (!hasChanged(responses) || Object.keys(responses).length === 0) {
            return;
        }

        // Use sendBeacon for reliable async save during page unload.
        if (navigator.sendBeacon) {
            var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey;
            var data = JSON.stringify([{
                index: 0,
                methodname: 'local_casospracticos_save_practice_responses',
                args: {
                    attemptid: attemptId,
                    responses: JSON.stringify(responses)
                }
            }]);

            navigator.sendBeacon(url, data);
        }
    };

    /**
     * Debounce function to limit call frequency.
     *
     * @param {Function} func Function to debounce
     * @param {number} wait Wait time in ms
     * @return {Function} Debounced function
     */
    var debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    };

    return {
        init: init,
        saveResponses: saveResponses,
        stopAutoSave: stopAutoSave
    };
});
