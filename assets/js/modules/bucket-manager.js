/**
 * S3 Master Admin - Bucket Manager Module
 * Handles bucket operations and selection
 */

(function($) {
    'use strict';

    const BucketManager = {
        currentBucket: '',
        
        init: function() {
            this.bindEvents();
            this.loadInitialBuckets();
        },

        bindEvents: function() {
            // Default bucket form submission
            $('#default-bucket-form').on('submit', this.handleDefaultBucketSubmission.bind(this));
            
            // Refresh buckets button
            $('#refresh-buckets').on('click', this.loadBucketList.bind(this));
            
            // Bucket selection change
            $('#default-bucket-select').on('change', this.handleBucketChange.bind(this));
        },

        handleDefaultBucketSubmission: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submit = $form.find('button[type="submit"]');
            const $select = $('#default-bucket-select');
            const $status = $('.bucket-select-status');
            const bucketName = $select.val();
            
            if (!bucketName) {
                $status.removeClass('loading success').addClass('error').text('Please select a bucket first.');
                return false;
            }
            
            $submit.prop('disabled', true);
            $select.prop('disabled', true);
            $status.removeClass('error success').addClass('loading').text('Verifying bucket...');
            
            // First verify the bucket
            this.verifyBucket(bucketName)
                .then(() => this.setDefaultBucket(bucketName))
                .then(() => {
                    $status.removeClass('loading error').addClass('success').text('Default bucket set successfully!');
                    this.currentBucket = bucketName;
                    window.S3MasterAdmin.utils.showToast('Default bucket set successfully!', 'success');
                })
                .catch(error => {
                    $status.removeClass('loading success').addClass('error').text(error || 'Failed to set default bucket.');
                    window.S3MasterAdmin.utils.showToast(error || 'Failed to set default bucket.', 'error');
                })
                .finally(() => {
                    $submit.prop('disabled', false);
                    $select.prop('disabled', false);
                });
        },

        verifyBucket: function(bucketName) {
            return new Promise((resolve, reject) => {
                $.post(s3_master_ajax.ajax_url, {
                    action: 's3_master_ajax',
                    s3_action: 'verify_bucket',
                    bucket_name: bucketName,
                    nonce: s3_master_ajax.nonce
                })
                .done(response => {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response.data || 'Failed to verify bucket.');
                    }
                })
                .fail(() => {
                    reject('Failed to verify bucket. Please try again.');
                });
            });
        },

        setDefaultBucket: function(bucketName) {
            return new Promise((resolve, reject) => {
                $.post(s3_master_ajax.ajax_url, {
                    action: 's3_master_ajax',
                    s3_action: 'set_default_bucket',
                    bucket_name: bucketName,
                    nonce: s3_master_ajax.nonce
                })
                .done(response => {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response.data || 'Failed to set default bucket.');
                    }
                })
                .fail(() => {
                    reject('Failed to set default bucket. Please try again.');
                });
            });
        },

        loadBucketList: function() {
            const $bucketSelect = $('#default-bucket-select');
            const $bucketStatus = $('.bucket-select-status');
            const currentBucket = $bucketSelect.data('current') || '';
            
            // Check if credentials are set
            if (!s3_master_ajax.has_credentials) {
                $bucketSelect.html('<option value="">' + s3_master_ajax.strings.no_bucket + '</option>');
                $bucketStatus.removeClass('loading success').addClass('error').text('Please enter and verify your AWS credentials first.');
                return;
            }
            
            $bucketSelect.prop('disabled', true);
            $bucketStatus.removeClass('error success').addClass('loading').text('Loading buckets...');
            
            const spinner = window.S3MasterAdmin.utils.showLoadingSpinner($bucketStatus, 'Loading buckets...');
            
            $.post(s3_master_ajax.ajax_url, {
                action: 's3_master_ajax',
                s3_action: 'list_buckets',
                nonce: s3_master_ajax.nonce
            })
            .done(response => {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">' + s3_master_ajax.strings.select_bucket + '</option>';
                    response.data.forEach(bucket => {
                        const selected = bucket.Name === currentBucket ? ' selected' : '';
                        options += `<option value="${bucket.Name}"${selected}>${bucket.Name}</option>`;
                    });
                    $bucketSelect.html(options);
                    $bucketStatus.removeClass('loading error').addClass('success').text(`${response.data.length} buckets found`);
                    window.S3MasterAdmin.utils.showToast(`Loaded ${response.data.length} buckets`, 'success');
                } else {
                    $bucketSelect.html('<option value="">' + s3_master_ajax.strings.no_buckets + '</option>');
                    $bucketStatus.removeClass('loading').addClass('error').text('No buckets found. Please create a bucket first.');
                    window.S3MasterAdmin.utils.showToast('No buckets found', 'warning');
                }
            })
            .fail(() => {
                $bucketSelect.html('<option value="">' + s3_master_ajax.strings.no_buckets + '</option>');
                $bucketStatus.removeClass('loading').addClass('error').text('Failed to load buckets. Please try again.');
                window.S3MasterAdmin.utils.showToast('Failed to load buckets', 'error');
            })
            .always(() => {
                $bucketSelect.prop('disabled', false);
                window.S3MasterAdmin.utils.hideLoadingSpinner($bucketStatus);
            });
        },

        handleBucketChange: function(e) {
            const bucketName = $(e.target).val();
            this.currentBucket = bucketName;
            
            // Update current bucket display
            $('#current-bucket').text(bucketName);
            
            // Trigger file refresh if file manager is available
            if (window.S3MasterAdmin.modules.fileManager) {
                window.S3MasterAdmin.modules.fileManager.refreshFiles();
            }
        },

        loadInitialBuckets: function() {
            if (s3_master_ajax.has_credentials) {
                this.loadBucketList();
            }
        },

        getCurrentBucket: function() {
            return this.currentBucket || $('#current-bucket').text() || '';
        }
    };

    // Register the module
    window.S3MasterAdmin.registerModule('bucketManager', BucketManager);

})(jQuery);
