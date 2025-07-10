/**
 * S3 Master Enhanced Admin JavaScript
 * 
 * Modern admin interface with improved UX and functionality
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
    
    // Enhanced Bucket Loading with Storage Info
    function loadBucketsWithStats() {
        var button = $('#refresh-buckets');
        var originalHtml = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>Loading...');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'list_buckets_with_stats',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayBuckets(response.data.buckets || response.data, response.data.overview);
            } else {
                $('#buckets-list').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).html(originalHtml);
        });
    }
    
    function displayBuckets(buckets, overview) {
        // Display storage overview
        if (overview) {
            var overviewHtml = '<div class="storage-overview">';
            overviewHtml += '<h3><span class="dashicons dashicons-dashboard"></span>Storage Overview</h3>';
            overviewHtml += '<div class="storage-stats">';
            overviewHtml += '<div class="storage-stat">';
            overviewHtml += '<span class="storage-stat-value">' + overview.total_buckets + '</span>';
            overviewHtml += '<span class="storage-stat-label">Total Buckets</span>';
            overviewHtml += '</div>';
            overviewHtml += '<div class="storage-stat">';
            overviewHtml += '<span class="storage-stat-value">' + overview.total_objects + '</span>';
            overviewHtml += '<span class="storage-stat-label">Total Objects</span>';
            overviewHtml += '</div>';
            overviewHtml += '<div class="storage-stat">';
            overviewHtml += '<span class="storage-stat-value">' + overview.total_size_formatted + '</span>';
            overviewHtml += '<span class="storage-stat-label">Total Storage</span>';
            overviewHtml += '</div>';
            overviewHtml += '</div></div>';
            $('#bucket-storage-overview').html(overviewHtml);
        }
        
        // Display buckets grid
        var html = '';
        
        if (buckets.length > 0) {
            $.each(buckets, function(index, bucket) {
                html += '<div class="bucket-card">';
                html += '<div class="bucket-name"><span class="dashicons dashicons-portfolio"></span>' + bucket.Name + '</div>';
                html += '<div class="bucket-meta">Created: ' + bucket.CreationDate + '</div>';
                
                if (bucket.stats) {
                    html += '<div class="bucket-stats">';
                    html += '<div class="bucket-stat">';
                    html += '<span class="bucket-stat-value">' + bucket.stats.object_count + '</span>';
                    html += '<div class="bucket-stat-label">Objects</div>';
                    html += '</div>';
                    html += '<div class="bucket-stat">';
                    html += '<span class="bucket-stat-value">' + bucket.stats.total_size_formatted + '</span>';
                    html += '<div class="bucket-stat-label">Size</div>';
                    html += '</div>';
                    html += '</div>';
                } else {
                    html += '<div class="bucket-stats">';
                    html += '<div class="bucket-stat">';
                    html += '<span class="bucket-stat-value">-</span>';
                    html += '<div class="bucket-stat-label">Objects</div>';
                    html += '</div>';
                    html += '<div class="bucket-stat">';
                    html += '<span class="bucket-stat-value">-</span>';
                    html += '<div class="bucket-stat-label">Size</div>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '<div class="bucket-actions">';
                html += '<button class="button button-primary set-default-bucket" data-bucket="' + bucket.Name + '">';
                html += '<span class="dashicons dashicons-star-filled"></span>Set Default</button>';
                html += '<button class="button explore-bucket" data-bucket="' + bucket.Name + '">';
                html += '<span class="dashicons dashicons-visibility"></span>Explore</button>';
                html += '<button class="button button-danger delete-bucket" data-bucket="' + bucket.Name + '">';
                html += '<span class="dashicons dashicons-trash"></span>Delete</button>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html = '<div class="loading-spinner"><span class="dashicons dashicons-portfolio"></span><p>No buckets found. Create your first bucket above.</p></div>';
        }
        
        $('#buckets-list').html(html);
    }
    
    // Enhanced File Display with View File Button
    function displayFiles(files) {
        var html = '<table class="widefat"><thead><tr>';
        html += '<th><span class="dashicons dashicons-media-default"></span>Name</th>';
        html += '<th>Type</th><th>Size</th><th>Modified</th><th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        if (window.currentPrefix) {
            html += '<tr class="folder-row" data-prefix="' + window.currentPrefix.replace(/[^\/]*\/$/, '') + '">';
            html += '<td><span class="dashicons dashicons-arrow-up-alt2"></span> ..</td>';
            html += '<td>-</td><td>-</td><td>-</td><td>-</td>';
            html += '</tr>';
        }
        
        if (files.length > 0) {
            $.each(files, function(index, file) {
                var fileTypeClass = getFileTypeClass(file.mime_type || file.name);
                html += '<tr class="' + (file.is_folder ? 'folder-row' : 'file-row ' + fileTypeClass) + '" data-key="' + file.key + '">';
                
                if (file.is_folder) {
                    html += '<td><span class="dashicons dashicons-portfolio"></span> ' + file.name + '</td>';
                } else {
                    html += '<td><span class="dashicons dashicons-media-default"></span> ' + file.name + '</td>';
                }
                
                html += '<td>' + file.type + '</td>';
                html += '<td>' + (file.size_formatted || '-') + '</td>';
                html += '<td>' + (file.last_modified || '-') + '</td>';
                html += '<td>';
                
                if (!file.is_folder) {
                    html += '<button class="button view-file-btn view-file" data-key="' + file.key + '" data-name="' + file.name + '">';
                    html += '<span class="dashicons dashicons-visibility"></span>View</button> ';
                    html += '<button class="button download-file" data-key="' + file.key + '">';
                    html += '<span class="dashicons dashicons-download"></span>Download</button> ';
                }
                
                html += '<button class="button button-danger delete-file" data-key="' + file.key + '">';
                html += '<span class="dashicons dashicons-trash"></span>Delete</button>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" style="text-align: center; padding: 40px;">No files found</td></tr>';
        }
        
        html += '</tbody></table>';
        $('#files-list').html(html);
    }
    
    // Get file type class for styling
    function getFileTypeClass(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'];
        var videoExts = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        var audioExts = ['mp3', 'wav', 'ogg', 'flac', 'aac'];
        var archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];
        var docExts = ['doc', 'docx', 'txt', 'rtf'];
        
        if (imageExts.includes(ext)) return 'file-type-image';
        if (videoExts.includes(ext)) return 'file-type-video';
        if (audioExts.includes(ext)) return 'file-type-audio';
        if (ext === 'pdf') return 'file-type-pdf';
        if (archiveExts.includes(ext)) return 'file-type-archive';
        if (docExts.includes(ext)) return 'file-type-document';
        
        return 'file-type-default';
    }
    
    // View File Modal
    function showFileModal(key, name) {
        var modalHtml = '<div id="file-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">';
        modalHtml += '<div style="background: white; border-radius: 8px; max-width: 90%; max-height: 90%; padding: 20px; position: relative;">';
        modalHtml += '<button id="close-modal" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>';
        modalHtml += '<h3 style="margin-top: 0;">' + name + '</h3>';
        modalHtml += '<div id="file-content" style="text-align: center; padding: 20px;">Loading...</div>';
        modalHtml += '</div></div>';
        
        $('body').append(modalHtml);
        
        // Get file URL and display content
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'get_file_url',
            bucket_name: $('#current-bucket').text().replace('Bucket: ', '') || '',
            key: key,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                var fileExt = name.split('.').pop().toLowerCase();
                var contentHtml = '';
                
                if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'].includes(fileExt)) {
                    contentHtml = '<img src="' + response.data + '" style="max-width: 100%; height: auto;" alt="' + name + '">';
                } else if (['mp4', 'webm', 'ogg'].includes(fileExt)) {
                    contentHtml = '<video controls style="max-width: 100%; height: auto;"><source src="' + response.data + '" type="video/' + fileExt + '"></video>';
                } else if (['mp3', 'wav', 'ogg'].includes(fileExt)) {
                    contentHtml = '<audio controls style="width: 100%;"><source src="' + response.data + '" type="audio/' + fileExt + '"></audio>';
                } else if (fileExt === 'pdf') {
                    contentHtml = '<iframe src="' + response.data + '" style="width: 100%; height: 500px; border: none;"></iframe>';
                } else {
                    contentHtml = '<p>File preview not available for this type.</p>';
                    contentHtml += '<a href="' + response.data + '" target="_blank" class="button button-primary">Open in New Tab</a>';
                }
                
                $('#file-content').html(contentHtml);
            } else {
                $('#file-content').html('<p style="color: red;">Error loading file preview.</p>');
            }
        });
        
        // Close modal
        $('#close-modal, #file-modal').click(function(e) {
            if (e.target === this) {
                $('#file-modal').remove();
            }
        });
    }
    
    // Event handlers for enhanced features
    $(document).on('click', '.set-default-bucket', function() {
        var bucketName = $(this).data('bucket');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'set_default_bucket',
            bucket_name: bucketName,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('Default bucket set successfully!', 'success');
            } else {
                showNotice('Error: ' + response.data, 'error');
            }
        });
    });
    
    // Explore bucket
    $(document).on('click', '.explore-bucket', function() {
        var bucketName = $(this).data('bucket');
        window.location.href = '?page=s3-master&tab=files&bucket=' + bucketName;
    });
    
    // View file
    $(document).on('click', '.view-file', function(e) {
        e.stopPropagation();
        var key = $(this).data('key');
        var name = $(this).data('name');
        showFileModal(key, name);
    });
    
    // Auto-load buckets if on buckets tab
    if (window.location.href.includes('tab=buckets')) {
        setTimeout(function() {
            loadBucketsWithStats();
        }, 500);
    }
    
    // Make the enhanced refresh button work
    $('#refresh-buckets').click(function() {
        if (typeof loadBucketsWithStats === 'function') {
            loadBucketsWithStats();
        }
    });
    
    // Enhanced Media Calculation and Backup Functionality
    $('#calculate-media').click(function() {
        var button = $(this);
        var originalHtml = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>Calculating...');
        $('#media-calculation-progress').show();
        $('#media-categories').hide();
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'calculate_media_files',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayMediaCategories(response.data);
                $('#media-calculation-progress').hide();
                $('#media-categories').show();
                $('#backup-selected-media, #select-all-media, #deselect-all-media').show();
            } else {
                showNotice('Error calculating media files: ' + response.data, 'error');
                $('#media-calculation-progress').hide();
            }
        }).always(function() {
            button.prop('disabled', false).html(originalHtml);
        });
    });
    
    function displayMediaCategories(mediaData) {
        var html = '';
        var categories = {
            'images': {
                'title': 'IMAGES',
                'icon': 'IMG',
                'class': 'images',
                'extensions': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico']
            },
            'videos': {
                'title': 'VIDEOS',
                'icon': 'VID',
                'class': 'videos',
                'extensions': ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp']
            },
            'audio': {
                'title': 'AUDIO',
                'icon': 'AUD',
                'class': 'audio',
                'extensions': ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a']
            },
            'documents': {
                'title': 'DOCUMENTS',
                'icon': 'DOC',
                'class': 'documents',
                'extensions': ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages']
            },
            'archives': {
                'title': 'ARCHIVES',
                'icon': 'ZIP',
                'class': 'archives',
                'extensions': ['zip', 'rar', '7z', 'tar', 'gz', 'bz2']
            },
            'other': {
                'title': 'OTHER',
                'icon': 'OTH',
                'class': 'other',
                'extensions': []
            }
        };
        
        Object.keys(categories).forEach(function(categoryKey) {
            var category = categories[categoryKey];
            var categoryData = mediaData[categoryKey] || {};
            var totalFiles = 0;
            var totalSize = 0;
            
            html += '<div class="media-category">';
            html += '<div class="media-category-header">';
            html += '<div class="media-category-title">';
            html += '<span class="media-category-icon ' + category.class + '">' + category.icon + '</span>';
            html += category.title;
            html += '</div>';
            
            // Count total files for this category
            category.extensions.forEach(function(ext) {
                if (categoryData[ext]) {
                    totalFiles += categoryData[ext].count;
                    totalSize += categoryData[ext].size;
                }
            });
            
            html += '<span class="media-category-count">' + totalFiles + ' files</span>';
            html += '</div>';
            
            html += '<div class="media-category-body">';
            html += '<ul class="media-type-list">';
            
            category.extensions.forEach(function(ext) {
                var extData = categoryData[ext] || { count: 0, size: 0 };
                var hasFiles = extData.count > 0;
                
                html += '<li class="media-type-item">';
                html += '<label class="media-type-checkbox">';
                html += '<input type="checkbox" name="media_types[]" value="' + ext + '"' + (hasFiles ? '' : ' disabled') + '>';
                html += '<span class="media-type-label">' + ext.toUpperCase() + '</span>';
                html += '</label>';
                html += '<span class="media-type-count ' + (hasFiles ? 'has-files' : 'no-files') + '">';
                if (hasFiles) {
                    html += extData.count + ' files (' + formatBytes(extData.size) + ')';
                } else {
                    html += 'No ' + ext.toUpperCase();
                }
                html += '</span>';
                html += '</li>';
            });
            
            html += '</ul>';
            html += '</div>';
            
            html += '<div class="media-category-footer">';
            html += '<label class="category-select-all" data-category="' + categoryKey + '">';
            html += '<input type="checkbox" class="category-select-all-checkbox"> Select All';
            html += '</label>';
            html += '<span class="category-total-size">' + formatBytes(totalSize) + '</span>';
            html += '</div>';
            
            html += '</div>';
        });
        
        $('#media-categories').html(html);
        
        // Add event handlers for category select all
        $('.category-select-all-checkbox').change(function() {
            var categoryKey = $(this).closest('.category-select-all').data('category');
            var isChecked = $(this).is(':checked');
            var categoryCard = $(this).closest('.media-category');
            
            categoryCard.find('input[name="media_types[]"]').each(function() {
                if (!$(this).prop('disabled')) {
                    $(this).prop('checked', isChecked);
                }
            });
        });
        
        // Update category select all when individual items change
        $('input[name="media_types[]"]').change(function() {
            var categoryCard = $(this).closest('.media-category');
            var allCheckboxes = categoryCard.find('input[name="media_types[]"]').not(':disabled');
            var checkedCheckboxes = categoryCard.find('input[name="media_types[]"]:checked').not(':disabled');
            var selectAllCheckbox = categoryCard.find('.category-select-all-checkbox');
            
            if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes.length === allCheckboxes.length) {
                selectAllCheckbox.prop('checked', true).prop('indeterminate', false);
            } else {
                selectAllCheckbox.prop('checked', false).prop('indeterminate', true);
            }
        });
    }
    
    // Select All Media Button
    $('#select-all-media').click(function() {
        $('input[name="media_types[]"]').not(':disabled').prop('checked', true);
        $('.category-select-all-checkbox').prop('checked', true).prop('indeterminate', false);
    });
    
    // Deselect All Media Button
    $('#deselect-all-media').click(function() {
        $('input[name="media_types[]"]').prop('checked', false);
        $('.category-select-all-checkbox').prop('checked', false).prop('indeterminate', false);
    });
    
    // Backup Selected Media Button
    $('#backup-selected-media').click(function() {
        var selectedTypes = [];
        $('input[name="media_types[]"]:checked').each(function() {
            selectedTypes.push($(this).val());
        });
        
        if (selectedTypes.length === 0) {
            showNotice('Please select at least one media type to backup.', 'warning');
            return;
        }
        
        if (!confirm('This will backup all selected media file types to S3. This may take a while. Continue?')) {
            return;
        }
        
        var button = $(this);
        var originalHtml = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>Backing up...');
        $('#backup-progress').html('<div class="backup-progress-bar"><div class="backup-progress-fill"></div></div><p>Backup in progress... Please wait.</p>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'backup_selected_media',
            media_types: selectedTypes,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('#backup-progress').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                showNotice('Backup completed successfully!', 'success');
            } else {
                $('#backup-progress').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                showNotice('Backup failed: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).html(originalHtml);
        });
    });
    
    // Helper function to format bytes
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // Handle connection test on page load
    if ($('#test-connection').length && $('input[name="aws_access_key_id"]').val()) {
        // Auto-test connection if credentials are present
        setTimeout(function() {
            $('#test-connection').click();
        }, 1000);
    }
});
