/**
 * S3 Master Enhanced Admin JavaScript
 * 
 * Modern admin interface with improved UX and functionality
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Enhanced file upload with drag and drop
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
                    var message = 'Successfully uploaded ' + uploadedFiles + ' files';
                    if (failedFiles > 0) {
                        message += ', ' + failedFiles + ' failed';
                    }
                    
                    $('#upload-progress').append('<p><strong>' + message + '</strong></p>');
                    
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
                    $('#' + progressId + ' .status').text('✓ Uploaded').css('color', '#10b981');
                    callback(true);
                } else {
                    $('#' + progressId + ' .status').text('✗ Failed: ' + response.data).css('color', '#ef4444');
                    callback(false);
                }
            },
            error: function() {
                $('#' + progressId + ' .status').text('✗ Error').css('color', '#ef4444');
                callback(false);
            }
        });
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
            bucket_name: $('#current-bucket').text() || '',
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
    
    // Event Handlers
    
    // Refresh buckets
    $('#refresh-buckets').click(function() {
        loadBucketsWithStats();
    });
    
    // Set default bucket
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
    
    // Connection status indicator
    function updateConnectionStatus(status, message) {
        var $indicator = $('#connection-indicator');
        if (!$indicator.length) {
            $indicator = $('<div id="connection-indicator"></div>');
            $('.wrap h1').after($indicator);
        }
        
        $indicator.removeClass('success warning error').addClass(status);
        var iconClass = status === 'success' ? 'yes' : status === 'warning' ? 'warning' : 'no';
        $indicator.html('<span class="dashicons dashicons-' + iconClass + '"></span> ' + message);
    }
    
    // Test connection
    $('#test-connection').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'test_connection',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                updateConnectionStatus('success', response.data);
                $('#connection-status').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
            } else {
                updateConnectionStatus('error', response.data);
                $('#connection-status').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Create bucket
    $('#create-bucket-form').submit(function(e) {
        e.preventDefault();
        
        var bucketName = $('#bucket-name').val();
        var region = $('#bucket-region').val();
        
        if (!bucketName) {
            alert('Please enter a bucket name');
            return;
        }
        
        var button = $(this).find('button[type="submit"]');
        var originalText = button.text();
        
        button.prop('disabled', true).text('Creating...');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'create_bucket',
            bucket_name: bucketName,
            region: region,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('Bucket created successfully!', 'success');
                $('#bucket-name').val('');
                loadBucketsWithStats();
            } else {
                showNotice('Error: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Delete bucket
    $(document).on('click', '.delete-bucket', function() {
        var bucketName = $(this).data('bucket');
        
        if (!confirm('Are you sure you want to delete bucket "' + bucketName + '"? This action cannot be undone.')) {
            return;
        }
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'delete_bucket',
            bucket_name: bucketName,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                showNotice('Bucket deleted successfully!', 'success');
                loadBucketsWithStats();
            } else {
                showNotice('Error: ' + response.data, 'error');
            }
        });
    });
    
    // Auto-load buckets if on buckets tab
    if (window.location.href.includes('tab=buckets')) {
        loadBucketsWithStats();
    }
    
    // Make bucket cards clickable to explore
    $(document).on('click', '.bucket-card', function(e) {
        if (!$(e.target).hasClass('button') && !$(e.target).parent().hasClass('button')) {
            $(this).find('.explore-bucket').click();
        }
    });
    
    // Enhanced search functionality
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
    
    // Initialize on page load
    if (typeof window.s3MasterInit !== 'undefined') {
        window.s3MasterInit();
    }
});
