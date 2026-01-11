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
 * Bulk actions module for practical cases.
 *
 * @module     local_casospracticos/bulk_actions
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, Notification, Str, ModalFactory, ModalEvents) {

    /**
     * BulkActions class.
     */
    var BulkActions = function() {
        this.selectedIds = [];
        this.init();
    };

    /**
     * Initialize bulk actions.
     */
    BulkActions.prototype.init = function() {
        this.bindEvents();
        this.updateBulkActionsVisibility();
    };

    /**
     * Bind event handlers.
     */
    BulkActions.prototype.bindEvents = function() {
        var self = this;

        // Individual checkbox change.
        $(document).on('change', '.case-select-checkbox', function() {
            self.updateSelection();
        });

        // Select all checkbox.
        $(document).on('change', '#select-all-cases', function() {
            var checked = $(this).is(':checked');
            $('.case-select-checkbox').prop('checked', checked);
            self.updateSelection();
        });

        // Bulk action buttons.
        $(document).on('click', '[data-bulk-action]', function(e) {
            e.preventDefault();
            var action = $(this).data('bulk-action');
            self.executeBulkAction(action);
        });
    };

    /**
     * Update selection state.
     */
    BulkActions.prototype.updateSelection = function() {
        this.selectedIds = [];
        $('.case-select-checkbox:checked').each(function() {
            this.selectedIds.push(parseInt($(this).val(), 10));
        }.bind(this));

        this.updateBulkActionsVisibility();
        this.updateSelectedCount();
    };

    /**
     * Update bulk actions visibility.
     */
    BulkActions.prototype.updateBulkActionsVisibility = function() {
        if (this.selectedIds.length > 0) {
            $('.bulk-actions-container').removeClass('d-none');
        } else {
            $('.bulk-actions-container').addClass('d-none');
        }
    };

    /**
     * Update selected count display.
     */
    BulkActions.prototype.updateSelectedCount = function() {
        $('.selected-count').text(this.selectedIds.length);
    };

    /**
     * Execute a bulk action.
     *
     * @param {string} action The action to execute.
     */
    BulkActions.prototype.executeBulkAction = function(action) {
        if (this.selectedIds.length === 0) {
            Notification.addNotification({
                message: 'No cases selected',
                type: 'warning'
            });
            return;
        }

        switch (action) {
            case 'delete':
                this.confirmDelete();
                break;
            case 'publish':
                this.confirmPublish();
                break;
            case 'archive':
                this.confirmArchive();
                break;
            case 'move':
                this.showMoveDialog();
                break;
            case 'export-pdf':
                this.exportPDF();
                break;
            case 'export-csv':
                this.exportCSV();
                break;
            default:
                console.warn('Unknown bulk action:', action);
        }
    };

    /**
     * Confirm and execute delete.
     */
    BulkActions.prototype.confirmDelete = function() {
        var self = this;

        Str.get_strings([
            {key: 'confirmdeleteselected', component: 'local_casospracticos'},
            {key: 'delete'},
            {key: 'cancel'}
        ]).then(function(strings) {
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: strings[1],
                body: strings[0].replace('{$a}', self.selectedIds.length)
            });
        }).then(function(modal) {
            modal.setSaveButtonText(Str.get_string('delete'));
            modal.getRoot().on(ModalEvents.save, function() {
                self.doDelete();
            });
            modal.show();
        }).catch(Notification.exception);
    };

    /**
     * Execute delete.
     */
    BulkActions.prototype.doDelete = function() {
        var self = this;

        Ajax.call([{
            methodname: 'local_casospracticos_bulk_delete',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            if (response.success) {
                Notification.addNotification({
                    message: response.deleted.length + ' cases deleted',
                    type: 'success'
                });
                window.location.reload();
            } else {
                Notification.addNotification({
                    message: 'Some cases could not be deleted',
                    type: 'warning'
                });
            }
        }).fail(Notification.exception);
    };

    /**
     * Confirm and execute publish.
     */
    BulkActions.prototype.confirmPublish = function() {
        var self = this;

        Str.get_strings([
            {key: 'confirmpublishselected', component: 'local_casospracticos'},
            {key: 'publish', component: 'local_casospracticos'},
            {key: 'cancel'}
        ]).then(function(strings) {
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: strings[1],
                body: strings[0].replace('{$a}', self.selectedIds.length)
            });
        }).then(function(modal) {
            modal.setSaveButtonText(Str.get_string('publish', 'local_casospracticos'));
            modal.getRoot().on(ModalEvents.save, function() {
                self.doPublish();
            });
            modal.show();
        }).catch(Notification.exception);
    };

    /**
     * Execute publish.
     */
    BulkActions.prototype.doPublish = function() {
        Ajax.call([{
            methodname: 'local_casospracticos_bulk_publish',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            if (response.success) {
                Notification.addNotification({
                    message: response.published.length + ' cases published',
                    type: 'success'
                });
                window.location.reload();
            } else {
                var msg = response.published.length + ' cases published';
                if (response.failed.length > 0) {
                    msg += ', ' + response.failed.length + ' failed';
                }
                Notification.addNotification({
                    message: msg,
                    type: 'warning'
                });
                window.location.reload();
            }
        }).fail(Notification.exception);
    };

    /**
     * Confirm and execute archive.
     */
    BulkActions.prototype.confirmArchive = function() {
        var self = this;

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: 'Archive Cases',
            body: 'Are you sure you want to archive ' + this.selectedIds.length + ' cases?'
        }).then(function(modal) {
            modal.setSaveButtonText('Archive');
            modal.getRoot().on(ModalEvents.save, function() {
                self.doArchive();
            });
            modal.show();
        }).catch(Notification.exception);
    };

    /**
     * Execute archive.
     */
    BulkActions.prototype.doArchive = function() {
        Ajax.call([{
            methodname: 'local_casospracticos_bulk_archive',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            if (response.success) {
                Notification.addNotification({
                    message: response.archived.length + ' cases archived',
                    type: 'success'
                });
                window.location.reload();
            }
        }).fail(Notification.exception);
    };

    /**
     * Show move to category dialog.
     */
    BulkActions.prototype.showMoveDialog = function() {
        var self = this;

        // Get categories for dropdown.
        Ajax.call([{
            methodname: 'local_casospracticos_get_categories',
            args: {}
        }])[0].done(function(categories) {
            var options = categories.map(function(cat) {
                return '<option value="' + cat.id + '">' + cat.name + '</option>';
            }).join('');

            var body = '<div class="form-group">' +
                '<label for="target-category">Select target category:</label>' +
                '<select class="form-control" id="target-category">' + options + '</select>' +
                '</div>';

            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: 'Move Cases',
                body: body
            }).then(function(modal) {
                modal.setSaveButtonText('Move');
                modal.getRoot().on(ModalEvents.save, function() {
                    var categoryid = modal.getRoot().find('#target-category').val();
                    self.doMove(parseInt(categoryid, 10));
                });
                modal.show();
            });
        }).fail(Notification.exception);
    };

    /**
     * Execute move.
     *
     * @param {number} categoryid Target category ID.
     */
    BulkActions.prototype.doMove = function(categoryid) {
        Ajax.call([{
            methodname: 'local_casospracticos_bulk_move',
            args: {caseids: this.selectedIds, categoryid: categoryid}
        }])[0].done(function(response) {
            if (response.success) {
                Notification.addNotification({
                    message: response.moved.length + ' cases moved',
                    type: 'success'
                });
                window.location.reload();
            }
        }).fail(Notification.exception);
    };

    /**
     * Export selected cases to PDF.
     */
    BulkActions.prototype.exportPDF = function() {
        var url = M.cfg.wwwroot + '/local/casospracticos/export.php?format=pdf&ids=' +
            this.selectedIds.join(',');
        window.location.href = url;
    };

    /**
     * Export selected cases to CSV.
     */
    BulkActions.prototype.exportCSV = function() {
        var url = M.cfg.wwwroot + '/local/casospracticos/export.php?format=csv&ids=' +
            this.selectedIds.join(',');
        window.location.href = url;
    };

    return {
        /**
         * Initialize bulk actions.
         */
        init: function() {
            return new BulkActions();
        }
    };
});
