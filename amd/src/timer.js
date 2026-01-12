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
 * Timer module for timed practice mode.
 *
 * @module     local_casospracticos/timer
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    var Timer = {
        timeLeft: 0,
        autoSubmit: false,
        interval: null,

        /**
         * Initialize the timer.
         *
         * @param {number} timeleft Time left in seconds.
         * @param {boolean} autosubmit Auto-submit when time expires.
         */
        init: function(timeleft, autosubmit) {
            this.timeLeft = timeleft;
            this.autoSubmit = autosubmit || false;

            this.updateDisplay();
            this.startCountdown();

            // Warn before leaving page.
            window.addEventListener('beforeunload', function(e) {
                if (Timer.timeLeft > 0) {
                    var confirmationMessage = 'Your timed attempt is still in progress. Are you sure you want to leave?';
                    e.returnValue = confirmationMessage;
                    return confirmationMessage;
                }
            });
        },

        /**
         * Start the countdown.
         */
        startCountdown: function() {
            this.interval = setInterval(function() {
                Timer.timeLeft--;
                Timer.updateDisplay();

                // Warning when 5 minutes left.
                if (Timer.timeLeft === 300) {
                    Timer.showWarning('5 minutes remaining!');
                }

                // Warning when 1 minute left.
                if (Timer.timeLeft === 60) {
                    Timer.showWarning('1 minute remaining!');
                }

                // Time expired.
                if (Timer.timeLeft <= 0) {
                    clearInterval(Timer.interval);
                    Timer.timeExpired();
                }
            }, 1000);
        },

        /**
         * Update the timer display.
         */
        updateDisplay: function() {
            var display = $('#timer-display');
            if (!display.length) {
                return;
            }

            var hours = Math.floor(this.timeLeft / 3600);
            var minutes = Math.floor((this.timeLeft % 3600) / 60);
            var seconds = this.timeLeft % 60;

            var timeString = '';
            if (hours > 0) {
                timeString += hours + ':';
            }
            timeString += (minutes < 10 ? '0' : '') + minutes + ':';
            timeString += (seconds < 10 ? '0' : '') + seconds;

            display.text(timeString);

            // Change color when time is running out.
            var container = display.closest('.alert');
            if (this.timeLeft <= 60) {
                container.removeClass('alert-info alert-warning').addClass('alert-danger');
            } else if (this.timeLeft <= 300) {
                container.removeClass('alert-info alert-danger').addClass('alert-warning');
            }

            // Add pulsing animation when very low.
            if (this.timeLeft <= 30) {
                display.addClass('timer-pulse');
            } else {
                display.removeClass('timer-pulse');
            }
        },

        /**
         * Show a warning notification.
         *
         * @param {string} message Warning message.
         */
        showWarning: function(message) {
            require(['core/notification'], function(Notification) {
                Notification.addNotification({
                    message: message,
                    type: 'warning'
                });
            });
        },

        /**
         * Handle time expiration.
         */
        timeExpired: function() {
            $('#timer-display').text('TIME UP!').addClass('timer-expired');

            if (this.autoSubmit) {
                // Auto-submit the form.
                var form = $('#timed-practice-form');
                if (form.length) {
                    // Show notification.
                    require(['core/notification'], function(Notification) {
                        Notification.addNotification({
                            message: 'Time is up! Submitting your answers...',
                            type: 'error'
                        });
                    });

                    // Submit after 2 seconds.
                    setTimeout(function() {
                        form.submit();
                    }, 2000);
                }
            } else {
                // Just disable form inputs.
                $('input, button, select, textarea').prop('disabled', true);

                require(['core/notification'], function(Notification) {
                    Notification.addNotification({
                        message: 'Time is up! Please submit your answers.',
                        type: 'error'
                    });
                });
            }
        }
    };

    return Timer;
});
