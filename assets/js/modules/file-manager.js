/**
 * S3 Master Admin - File Manager Module
 * Handles file management operations
 */

(function($) {
    'use strict';

    const FileManager = {
        files: [],

        init: function() {
            this.bindEvents();
            this.refreshFiles();
        },

        bindEvents: function() {
            // Checkbox logic
            $('#select-all').on('change', this.toggleSelectAll.bind(this));
            $(document).on('change', '.file-checkbox', this.toggleFileSelect.bind(this));

            // Rename action
            $(document).on('click', '.rename-file', this.handleRenameFile.bind(this));

            // Delete action
            $('#delete-selected').on('click', this.deleteSelectedFiles.bind(this));
            
            // Search functionality
            $('#file-search').on('keyup', window.S3MasterAdmin.utils.debounce(this.searchFiles.bind(this), 300));

            // Sorting
            $('.sortable-header').on('click', this.sortColumn.bind(this));
        },

        toggleSelectAll: function(e) {
            const isChecked = $(e.target).is(':checked');
            $('.file-checkbox').prop('checked', isChecked).trigger('change');
            this.updateSelectedFiles();
        },

        toggleFileSelect: function(e) {
            const $checkbox = $(e.target);
            const key = $checkbox.data('key');
            
            if ($checkbox.is(':checked')) {
                if (!window.S3MasterAdmin.selectedFiles.includes(key)) {
                    window.S3MasterAdmin.selectedFiles.push(key);
                }
            } else {
                window.S3MasterAdmin.selectedFiles = window.S3MasterAdmin.selectedFiles.filter(f => f !== key);
            }

            this.updateSelectedFiles();
        },

        updateSelectedFiles: function() {
            $('#delete-selected-count').text(window.S3MasterAdmin.selectedFiles.length);
            $('#delete-selected').prop('disabled', window.S3MasterAdmin.selectedFiles.length === 0);
        },

        deleteSelectedFiles: function() {
            if (!confirm(`Are you sure you want to delete ${window.S3MasterAdmin.selectedFiles.length} selected files?`)) {
                return;
            }

            const deletePromises = window.S3MasterAdmin.selectedFiles.map(key => {
                return $.post(s3_master_ajax.ajax_url, {
                    action: 's3_master_ajax',
                    s3_action: 'delete_file',
                    bucket_name: window.S3MasterAdmin.modules.bucketManager.getCurrentBucket(),
                    key: key,
                    nonce: s3_master_ajax.nonce
                });
            });

            Promise.all(deletePromises)
                .then(() => {
                    window.S3MasterAdmin.utils.showToast('Selected files deleted successfully', 'success');
                    this.refreshFiles();
                })
                .catch(() => {
                    window.S3MasterAdmin.utils.showToast('Some files could not be deleted', 'error');
                });
        },

        searchFiles: function() {
            const query = $('#file-search').val().toLowerCase();
            
            $('.file-row').each(function() {
                const $row = $(this);
                const filename = $row.find('td').first().text().toLowerCase();

                if (filename.includes(query)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },

        sortColumn: function(e) {
            const $header = $(e.target);
            const isAsc = $header.hasClass('asc');
            const column = $header.data('column');

            this.files.sort((a, b) => {
                const valA = a[column];
                const valB = b[column];
                if (valA < valB) return isAsc ? -1 : 1;
                if (valA > valB) return isAsc ? 1 : -1;
                return 0;
            });

            $header.toggleClass('asc', !isAsc).toggleClass('desc', isAsc);
            this.renderFiles();
        },

        refreshFiles: function() {
            const bucketName = window.S3MasterAdmin.modules.bucketManager.getCurrentBucket();
            
            if (!bucketName) {
                window.S3MasterAdmin.utils.showToast('No default bucket selected', 'warning');
                return;
            }

            $.post(s3_master_ajax.ajax_url, {
                action: 's3_master_ajax',
                s3_action: 'list_objects',
                bucket_name: bucketName,
                nonce: s3_master_ajax.nonce
            }, response => {
                if (response.success) {
                    this.files = response.data;
                    this.renderFiles();
                } else {
                    window.S3MasterAdmin.utils.showToast('Failed to load files', 'error');
                }
            });
        },

        renderFiles: function() {
            const $fileList = $('#file-list');
            $fileList.empty();

            this.files.forEach(file => {
                $fileList.append(`
                    <tr class="file-row">
                        <td>
                            <input type="checkbox" class="file-checkbox" data-key="${file.Key}" />
                        </td>
                        <td data-label="Name">${file.Name}</td>
                        <td data-label="Size">${window.S3MasterAdmin.utils.formatFileSize(file.Size)}</td>
                        <td data-label="Last Modified">${file.LastModified}</td>
                        <td>
                            <button class="rename-file" data-key="${file.Key}">Rename</button>
                        </td>
                    </tr>
                `);
            });

            this.updateSelectedFiles();
        },

        handleRenameFile: function(e) {
            e.preventDefault();
            const key = $(e.target).data('key');
            const fileName = prompt('Enter new name');
            if (!fileName) return;

            $.post(s3_master_ajax.ajax_url, {
                action: 's3_master_ajax',
                s3_action: 'rename_file',
                bucket_name: window.S3MasterAdmin.modules.bucketManager.getCurrentBucket(),
                key: key,
                new_name: fileName,
                nonce: s3_master_ajax.nonce
            }, response => {
                if (response.success) {
                    window.S3MasterAdmin.utils.showToast('File renamed successfully', 'success');
                    this.refreshFiles();
                } else {
                    window.S3MasterAdmin.utils.showToast('Failed to rename file', 'error');
                }
            });
        }
    };

    // Register the module
    window.S3MasterAdmin.registerModule('fileManager', FileManager);

})(jQuery);
