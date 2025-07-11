/**
 * S3 Master Admin - Uploader Module
 * Handles the file upload functionality
 */

(function($) {
    'use strict';

    const Uploader = {
        init: function() {
            this.bindDragAndDrop();
            this.bindFileInputChange();
        },

        bindDragAndDrop: function() {
            const $uploadArea = $('#file-upload-area');
            const $fileInput = $('#file-input');

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
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $fileInput[0].files = files;
                    Uploader.handleFiles(files);
                }
            });
        },

        bindFileInputChange: function() {
            $('#file-input').on('change', function(e) {
                const files = e.target.files;
                if (files.length > 0) {
                    Uploader.handleFiles(files);
                }
            });
        },

        handleFiles: function(files) {
            Array.from(files).forEach((file, index) => {
                Uploader.uploadFile(file, index);
            });
        },

        uploadFile: function(file, index) {
            const formData = new FormData();
            formData.append('action', 's3_master_ajax');
            formData.append('s3_action', 'upload_file');
            formData.append('bucket_name', window.S3MasterAdmin.modules.bucketManager.getCurrentBucket());
            formData.append('prefix', window.S3MasterAdmin.currentPrefix);
            formData.append('nonce', s3_master_ajax.nonce);
            formData.append('file', file);

            const $progressContainer = $('#upload-progress');
            const progressId = `progress-${index}`;
            const progressHtml = `
                <div class="file-upload-progress" id="${progressId}">
                    <span class="filename">${file.name}</span>
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <span class="status">Uploading...</span>
                </div>
            `;

            $progressContainer.append(progressHtml);

            $.ajax({
                url: s3_master_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = evt.loaded / evt.total * 100;
                            $(`#${progressId} .progress-fill`).css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        $(`#${progressId} .status`).text('✓ Uploaded').css('color', 'green');
                        window.S3MasterAdmin.utils.showToast(`${file.name} uploaded successfully`, 'success');
                    } else {
                        $(`#${progressId} .status`).text(`✗ Failed: ${response.data}`).css('color', 'red');
                        window.S3MasterAdmin.utils.showToast(`Failed to upload ${file.name}`, 'error');
                    }
                },
                error: function() {
                    $(`#${progressId} .status`).text('✗ Error').css('color', 'red');
                    window.S3MasterAdmin.utils.showToast(`Error uploading ${file.name}`, 'error');
                }
            });
        }
    };

    // Register the module
    window.S3MasterAdmin.registerModule('uploader', Uploader);

})(jQuery);
