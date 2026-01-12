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
        this.strings = {};
        this.init();
    };

    /**
     * Initialize bulk actions.
     */
    BulkActions.prototype.init = function() {
        var self = this;

        // Pre-load strings.
        Str.get_strings([
            {key: 'confirmdeleteselected', component: 'local_casospracticos'},
            {key: 'confirmpublishselected', component: 'local_casospracticos'},
            {key: 'confirmarchiveselected', component: 'local_casospracticos'},
            {key: 'delete'},
            {key: 'cancel'},
            {key: 'publish', component: 'local_casospracticos'},
            {key: 'archive', component: 'local_casospracticos'},
            {key: 'move', component: 'local_casospracticos'},
            {key: 'nocasesselected', component: 'local_casospracticos'},
            {key: 'casesdeleted', component: 'local_casospracticos'},
            {key: 'casespublished', component: 'local_casospracticos'},
            {key: 'casesarchived', component: 'local_casospracticos'},
            {key: 'casesmoved', component: 'local_casospracticos'},
            {key: 'selecttargetcategory', component: 'local_casospracticos'}
        ]).then(function(strings) {
            self.strings = {
                confirmDelete: strings[0],
                confirmPublish: strings[1],
                confirmArchive: strings[2],
                delete: strings[3],
                cancel: strings[4],
                publish: strings[5],
                archive: strings[6],
                move: strings[7],
                noCasesSelected: strings[8],
                casesDeleted: strings[9],
                casesPublished: strings[10],
                casesArchived: strings[11],
                casesMoved: strings[12],
                selectTargetCategory: strings[13]
            };
            self.bindEvents();
            self.updateBulkActionsVisibility();
        }).catch(Notification.exception);
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
        var self = this;
        $('.case-select-checkbox:checked').each(function() {
            self.selectedIds.push(parseInt($(this).val(), 10));
        });

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
     * Show loading indicator.
     */
    BulkActions.prototype.showLoading = function() {
        $('[data-bulk-action]').prop('disabled', true);
        $('.bulk-actions-container').addClass('loading');
    };

    /**
     * Hide loading indicator.
     */
    BulkActions.prototype.hideLoading = function() {
        $('[data-bulk-action]').prop('disabled', false);
        $('.bulk-actions-container').removeClass('loading');
    };

    /**
     * Execute a bulk action.
     *
     * @param {string} action The action to execute.
     */
    BulkActions.prototype.executeBulkAction = function(action) {
        if (this.selectedIds.length === 0) {
            Notification.addNotification({
                message: this.strings.noCasesSelected,
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
                // eslint-disable-next-line no-console
                console.warn('Unknown bulk action:', action);
        }
    };

    /**
     * Confirm and execute delete.
     */
    BulkActions.prototype.confirmDelete = function() {
        var self = this;

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: this.strings.delete,
            body: this.strings.confirmDelete.replace('{$a}', this.selectedIds.length)
        }).then(function(modal) {
            modal.setSaveButtonText(self.strings.delete);
            modal.getRoot().on(ModalEvents.save, function() {
                self.doDelete();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Execute delete.
     */
    BulkActions.prototype.doDelete = function() {
        var self = this;
        this.showLoading();

        Ajax.call([{
            methodname: 'local_casospracticos_bulk_delete',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            self.hideLoading();
            if (response.success) {
                Notification.addNotification({
                    message: self.strings.casesDeleted.replace('{$a}', response.deleted.length),
                    type: 'success'
                });
                window.location.reload();
            } else {
                Notification.addNotification({
                    message: response.deleted.length + ' deleted, ' + response.failed.length + ' failed',
                    type: 'warning'
                });
                window.location.reload();
            }
        }).fail(function(error) {
            self.hideLoading();
            Notification.exception(error);
        });
    };

    /**
     * Confirm and execute publish.
     */
    BulkActions.prototype.confirmPublish = function() {
        var self = this;

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: this.strings.publish,
            body: this.strings.confirmPublish.replace('{$a}', this.selectedIds.length)
        }).then(function(modal) {
            modal.setSaveButtonText(self.strings.publish);
            modal.getRoot().on(ModalEvents.save, function() {
                self.doPublish();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Execute publish.
     */
    BulkActions.prototype.doPublish = function() {
        var self = this;
        this.showLoading();

        Ajax.call([{
            methodname: 'local_casospracticos_bulk_publish',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            self.hideLoading();
            var msg = self.strings.casesPublished.replace('{$a}', response.published.length);
            if (response.failed.length > 0) {
                msg += ' (' + response.failed.length + ' failed)';
            }
            Notification.addNotification({
                message: msg,
                type: response.success ? 'success' : 'warning'
            });
            window.location.reload();
        }).fail(function(error) {
            self.hideLoading();
            Notification.exception(error);
        });
    };

    /**
     * Confirm and execute archive.
     */
    BulkActions.prototype.confirmArchive = function() {
        var self = this;

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: this.strings.archive,
            body: this.strings.confirmArchive.replace('{$a}', this.selectedIds.length)
        }).then(function(modal) {
            modal.setSaveButtonText(self.strings.archive);
            modal.getRoot().on(ModalEvents.save, function() {
                self.doArchive();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Execute archive.
     */
    BulkActions.prototype.doArchive = function() {
        var self = this;
        this.showLoading();

        Ajax.call([{
            methodname: 'local_casospracticos_bulk_archive',
            args: {caseids: this.selectedIds}
        }])[0].done(function(response) {
            self.hideLoading();
            if (response.success) {
                Notification.addNotification({
                    message: self.strings.casesArchived.replace('{$a}', response.archived.length),
                    type: 'success'
                });
                window.location.reload();
            }
        }).fail(function(error) {
            self.hideLoading();
            Notification.exception(error);
        });
    };

    /**
     * Escape HTML entities to prevent XSS.
     *
     * @param {string} text Text to escape.
     * @return {string} Escaped text.
     */
    BulkActions.prototype.escapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
                var indent = '';
                for (var i = 0; i < cat.depth; i++) {
                    indent += '&nbsp;&nbsp;';
                }
                // Escape category name to prevent XSS.
                return '<option value="' + parseInt(cat.id, 10) + '">' + indent + self.escapeHtml(cat.name) + '</option>';
            }).join('');

            var body = '<div class="form-group">' +
                '<label for="target-category">' + self.strings.selectTargetCategory + ':</label>' +
                '<select class="form-control" id="target-category">' + options + '</select>' +
                '</div>';

            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: self.strings.move,
                body: body
            }).then(function(modal) {
                modal.setSaveButtonText(self.strings.move);
                modal.getRoot().on(ModalEvents.save, function() {
                    var categoryid = modal.getRoot().find('#target-category').val();
                    self.doMove(parseInt(categoryid, 10));
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        }).fail(Notification.exception);
    };

    /**
     * Execute move.
     *
     * @param {number} categoryid Target category ID.
     */
    BulkActions.prototype.doMove = function(categoryid) {
        var self = this;
        this.showLoading();

        Ajax.call([{
            methodname: 'local_casospracticos_bulk_move',
            args: {caseids: this.selectedIds, categoryid: categoryid}
        }])[0].done(function(response) {
            self.hideLoading();
            if (response.success) {
                Notification.addNotification({
                    message: self.strings.casesMoved.replace('{$a}', response.moved.length),
                    type: 'success'
                });
                window.location.reload();
            }
        }).fail(function(error) {
            self.hideLoading();
            Notification.exception(error);
        });
    };

    /**
     * Export selected cases to PDF.
     */
    BulkActions.prototype.exportPDF = function() {
        // Validate IDs are integers and include sesskey for CSRF protection.
        var validIds = this.selectedIds.filter(function(id) {
            return Number.isInteger(id) && id > 0;
        });
        if (validIds.length === 0) {
            return;
        }
        var url = M.cfg.wwwroot + '/local/casospracticos/export.php?format=pdf&ids=' +
            validIds.join(',') + '&sesskey=' + M.cfg.sesskey;
        window.location.href = url;
    };

    /**
     * Export selected cases to CSV.
     */
    BulkActions.prototype.exportCSV = function() {
        // Validate IDs are integers and include sesskey for CSRF protection.
        var validIds = this.selectedIds.filter(function(id) {
            return Number.isInteger(id) && id > 0;
        });
        if (validIds.length === 0) {
            return;
        }
        var url = M.cfg.wwwroot + '/local/casospracticos/export.php?format=csv&ids=' +
            validIds.join(',') + '&sesskey=' + M.cfg.sesskey;
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
