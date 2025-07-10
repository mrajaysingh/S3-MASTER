/**
 * S3 Master Admin JavaScript
 * 
 * Handles admin interface interactions
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // File upload with drag and drop
    var $uploadArea = $('#file-upload-area');
    var $fileInput = $('#file-input');
    
    // Drag and drop functionality
    if ($uploadArea.length) {
        $uploadArea.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        $uploadArea.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        $uploadArea.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $fileInput[0].files = files;
                uploadFiles(files);
            }
        });
    }
    
    // Enhanced file upload with progress
    function uploadFiles(files) {
        var totalFiles = files.length;
        var uploadedFiles = 0;
        var failedFiles = 0;
        
        $('#upload-progress').html('<div class="upload-progress-container"></div>');
        
        Array.from(files).forEach(function(file, index) {
            uploadSingleFile(file, index, function(success) {
                if (success) {
                    uploadedFiles++;
                } else {
                    failedFiles++;
                }
                
                if (uploadedFiles + failedFiles === totalFiles) {
                    // All files processed
                    var message = s3_master_ajax.strings.success + ' ' + uploadedFiles + ' files uploaded';
                    if (failedFiles > 0) {
                        message += ', ' + failedFiles + ' failed';
                    }
                    
                    $('#upload-progress').append('<p><strong>' + message + '</strong></p>');
                    
                    // Refresh file list if available
                    if (typeof loadFiles === 'function') {
                        loadFiles(window.currentPrefix || '');
                    }
                }
            });
        });
    }
    
    function uploadSingleFile(file, index, callback) {
        var formData = new FormData();
        formData.append('action', 's3_master_ajax');
        formData.append('s3_action', 'upload_file');
        formData.append('bucket_name', $('#current-bucket').text() || '');
        formData.append('prefix', window.currentPrefix || '');
        formData.append('nonce', s3_master_ajax.nonce);
        formData.append('file', file);
        
        var progressId = 'progress-' + index;
        var progressHtml = '<div class="file-upload-progress" id="' + progressId + '">';
        progressHtml += '<span class="filename">' + file.name + '</span>';
        progressHtml += '<div class="progress-bar"><div class="progress-fill"></div></div>';
        progressHtml += '<span class="status">Uploading...</span>';
        progressHtml += '</div>';
        
        $('.upload-progress-container').append(progressHtml);
        
        $.ajax({
            url: s3_master_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total * 100;
                        $('#' + progressId + ' .progress-fill').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $('#' + progressId + ' .status').text('✓ Uploaded').css('color', 'green');
                    callback(true);
                } else {
                    $('#' + progressId + ' .status').text('✗ Failed: ' + response.data).css('color', 'red');
                    callback(false);
                }
            },
            error: function() {
                $('#' + progressId + ' .status').text('✗ Error').css('color', 'red');
                callback(false);
            }
        });
    }
    
    // File size formatting
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Connection status indicator
    function updateConnectionStatus(status, message) {
        var $indicator = $('#connection-indicator');
        if (!$indicator.length) {
            $indicator = $('<div id="connection-indicator"></div>');
            $('.wrap h1').after($indicator);
        }
        
        $indicator.removeClass('success warning error').addClass(status);
        $indicator.html('<span class="dashicons dashicons-' + (status === 'success' ? 'yes' : status === 'warning' ? 'warning' : 'no') + '"></span> ' + message);
    }
    
    // Auto-save settings
    var settingsTimeout;
    $('.s3-master-setting').on('change', function() {
        clearTimeout(settingsTimeout);
        settingsTimeout = setTimeout(function() {
            saveSettings();
        }, 1000);
    });
    
    function saveSettings() {
        var settings = {};
        $('.s3-master-setting').each(function() {
            var $input = $(this);
            var name = $input.attr('name');
            var value = $input.is(':checkbox') ? $input.is(':checked') : $input.val();
            settings[name] = value;
        });
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'save_settings',
            settings: settings,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('Settings saved automatically', 'success');
            }
        });
    }
    
    // Show admin notices
    function showNotice(message, type) {
        type = type || 'info';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + U for upload
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 85) {
            e.preventDefault();
            $('#upload-file').click();
        }
        
        // Ctrl/Cmd + N for new folder
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 78) {
            e.preventDefault();
            $('#create-folder').click();
        }
        
        // F5 for refresh
        if (e.keyCode === 116) {
            e.preventDefault();
            $('#refresh-files, #refresh-buckets').click();
        }
    });
    
    // Context menu for files
    $(document).on('contextmenu', '.file-row, .folder-row', function(e) {
        e.preventDefault();
        
        var $row = $(this);
        var key = $row.data('key');
        var isFolder = $row.hasClass('folder-row');
        
        // Remove existing context menu
        $('.context-menu').remove();
        
        var menuItems = [];
        
        if (!isFolder) {
            menuItems.push('<li><a href="#" class="download-file" data-key="' + key + '">Download</a></li>');
            menuItems.push('<li><a href="#" class="rename-file" data-key="' + key + '">Rename</a></li>');
        }
        
        menuItems.push('<li><a href="#" class="delete-file" data-key="' + key + '">Delete</a></li>');
        
        var $menu = $('<ul class="context-menu">' + menuItems.join('') + '</ul>');
        $menu.css({
            position: 'absolute',
            left: e.pageX,
            top: e.pageY,
            zIndex: 9999
        });
        
        $('body').append($menu);
        
        // Hide menu on click elsewhere
        $(document).one('click', function() {
            $menu.remove();
        });
    });
    
    // Search functionality
    var searchTimeout;
    $('#file-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        var query = $(this).val().toLowerCase();
        
        searchTimeout = setTimeout(function() {
            $('.file-row, .folder-row').each(function() {
                var $row = $(this);
                var filename = $row.find('td:first').text().toLowerCase();
                
                if (filename.indexOf(query) === -1) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });
        }, 300);
    });
    
    // Batch operations
    var selectedFiles = [];
    
    $(document).on('change', '.file-checkbox', function() {
        var key = $(this).data('key');
        var index = selectedFiles.indexOf(key);
        
        if ($(this).is(':checked')) {
            if (index === -1) {
                selectedFiles.push(key);
            }
        } else {
            if (index > -1) {
                selectedFiles.splice(index, 1);
            }
        }
        
        updateBatchActions();
    });
    
    function updateBatchActions() {
        var $batchActions = $('#batch-actions');
        if (selectedFiles.length > 0) {
            if (!$batchActions.length) {
                $batchActions = $('<div id="batch-actions"><button class="button" id="delete-selected">Delete Selected (' + selectedFiles.length + ')</button></div>');
                $('.file-manager-toolbar').append($batchActions);
            } else {
                $batchActions.find('#delete-selected').text('Delete Selected (' + selectedFiles.length + ')');
            }
        } else {
            $batchActions.remove();
        }
    }
    
    $(document).on('click', '#delete-selected', function() {
        if (!confirm('Are you sure you want to delete ' + selectedFiles.length + ' selected files?')) {
            return;
        }
        
        var deletePromises = selectedFiles.map(function(key) {
            return $.post(s3_master_ajax.ajax_url, {
                action: 's3_master_ajax',
                s3_action: 'delete_file',
                bucket_name: $('#current-bucket').text() || '',
                key: key,
                nonce: s3_master_ajax.nonce
            });
        });
        
        Promise.all(deletePromises).then(function() {
            selectedFiles = [];
            updateBatchActions();
            if (typeof loadFiles === 'function') {
                loadFiles(window.currentPrefix || '');
            }
            showNotice('Selected files deleted successfully', 'success');
        }).catch(function() {
            showNotice('Some files could not be deleted', 'error');
        });
    });
    
    // Tooltips
    $('[data-tooltip]').each(function() {
        var $element = $(this);
        var tooltip = $element.data('tooltip');
        
        $element.on('mouseenter', function() {
            var $tooltip = $('<div class="s3-tooltip">' + tooltip + '</div>');
            $('body').append($tooltip);
            
            var offset = $element.offset();
            $tooltip.css({
                position: 'absolute',
                left: offset.left + $element.outerWidth() / 2 - $tooltip.outerWidth() / 2,
                top: offset.top - $tooltip.outerHeight() - 5
            });
        });
        
        $element.on('mouseleave', function() {
            $('.s3-tooltip').remove();
        });
    });
    
    // Initialize components
    if ($('.s3-master-file-manager').length) {
        // Add search box if not exists
        if (!$('#file-search').length) {
            $('.file-manager-toolbar').prepend('<input type="text" id="file-search" placeholder="Search files..." />');
        }
    }
    
    // Handle connection test on page load
    if ($('#test-connection').length && $('input[name="aws_access_key_id"]').val()) {
        // Auto-test connection if credentials are present
        setTimeout(function() {
            $('#test-connection').click();
        }, 1000);
    }
});
