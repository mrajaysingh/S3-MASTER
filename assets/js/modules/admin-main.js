/**
 * S3 Master Admin - Main Module
 * Initializes all modules and handles global functionality
 */

(function($) {
    'use strict';

    // Global state management
    window.S3MasterAdmin = {
        currentPrefix: '',
        selectedFiles: [],
        modules: {},
        settings: {
            debounceDelay: 300,
            toastDuration: 5000
        }
    };

    // Toast notification system
    function showToast(message, type = 'info', duration = null) {
        const toastClass = `s3-toast s3-toast-${type}`;
        const toast = $(`<div class="${toastClass}">
            <span class="s3-toast-message">${message}</span>
            <button class="s3-toast-close" type="button">&times;</button>
        </div>`);

        // Add to container or create one
        let container = $('.s3-toast-container');
        if (!container.length) {
            container = $('<div class="s3-toast-container"></div>');
            $('body').append(container);
        }

        container.append(toast);

        // Auto-hide after duration
        const hideDelay = duration || window.S3MasterAdmin.settings.toastDuration;
        setTimeout(() => {
            toast.addClass('s3-toast-hide');
            setTimeout(() => toast.remove(), 300);
        }, hideDelay);

        // Manual close
        toast.find('.s3-toast-close').on('click', function() {
            toast.addClass('s3-toast-hide');
            setTimeout(() => toast.remove(), 300);
        });
    }

    // Loading spinner utility
    function showLoadingSpinner(target, message = 'Loading...') {
        const spinner = $(`<div class="s3-loading-spinner">
            <div class="s3-spinner"></div>
            <span class="s3-loading-message">${message}</span>
        </div>`);
        
        if (typeof target === 'string') {
            target = $(target);
        }
        
        target.addClass('s3-loading-container').append(spinner);
        return spinner;
    }

    function hideLoadingSpinner(target) {
        if (typeof target === 'string') {
            target = $(target);
        }
        target.removeClass('s3-loading-container').find('.s3-loading-spinner').remove();
    }

    // Debounce utility
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Format file size utility
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Global utilities
    window.S3MasterAdmin.utils = {
        showToast,
        showLoadingSpinner,
        hideLoadingSpinner,
        debounce,
        formatFileSize
    };

    // Module registration system
    window.S3MasterAdmin.registerModule = function(name, module) {
        this.modules[name] = module;
        if (typeof module.init === 'function') {
            module.init();
        }
    };

    // Initialize all modules when DOM is ready
    $(document).ready(function() {
        // Initialize modules in order
        const moduleOrder = ['bucketManager', 'fileManager', 'uploader'];
        
        moduleOrder.forEach(moduleName => {
            if (window.S3MasterAdmin.modules[moduleName]) {
                console.log(`Initializing ${moduleName} module`);
            }
        });

        // Global keyboard shortcuts
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

            // Escape to close modals
            if (e.keyCode === 27) {
                $('.s3-modal').removeClass('s3-modal-active');
            }
        });

        // Auto-save settings
        let settingsTimeout;
        $('.s3-master-setting').on('change', function() {
            clearTimeout(settingsTimeout);
            settingsTimeout = setTimeout(function() {
                saveSettings();
            }, 1000);
        });

        function saveSettings() {
            const settings = {};
            $('.s3-master-setting').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                const value = $input.is(':checkbox') ? $input.is(':checked') : $input.val();
                settings[name] = value;
            });
            
            $.post(s3_master_ajax.ajax_url, {
                action: 's3_master_ajax',
                s3_action: 'save_settings',
                settings: settings,
                nonce: s3_master_ajax.nonce
            }, function(response) {
                if (response.success) {
                    showToast('Settings saved automatically', 'success');
                }
            });
        }

        // Connection status indicator
        window.S3MasterAdmin.updateConnectionStatus = function(status, message) {
            let $indicator = $('#connection-indicator');
            if (!$indicator.length) {
                $indicator = $('<div id="connection-indicator"></div>');
                $('.wrap h1').after($indicator);
            }
            
            $indicator.removeClass('success warning error').addClass(status);
            $indicator.html(`<span class="dashicons dashicons-${status === 'success' ? 'yes' : status === 'warning' ? 'warning' : 'no'}"></span> ${message}`);
        };

        console.log('S3 Master Admin initialized');
    });

})(jQuery);
