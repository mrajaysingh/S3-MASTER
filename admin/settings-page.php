<?php
/**
 * Admin Settings Page for S3 Master
 * 
 * Displays the plugin settings interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['s3_master_nonce']) && wp_verify_nonce($_POST['s3_master_nonce'], 's3_master_settings')) {
    $aws_client = S3_Master_AWS_Client::get_instance();
    $bucket_manager = S3_Master_Bucket_Manager::get_instance();
    $updater = S3_Master_Updater::get_instance();
    
    if (isset($_POST['save_credentials'])) {
        $access_key_id = sanitize_text_field($_POST['aws_access_key_id']);
        $secret_access_key = sanitize_text_field($_POST['aws_secret_access_key']);
        $region = sanitize_text_field($_POST['aws_region']);
        
        // First update the credentials
        $aws_client->update_credentials($access_key_id, $secret_access_key, $region);
        
        // Then test the connection
        try {
            $test_result = $aws_client->test_connection();
            
            if ($test_result['success']) {
                update_option('s3_master_connection_verified', true);
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php _e('AWS credentials saved successfully!', 's3-master'); ?></strong></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text"><?php _e('Dismiss this notice.', 's3-master'); ?></span>
                    </button>
                </div>
                <script>
                    jQuery(document).ready(function($) {
                        $('.s3-master-managed-tab').show();
                        $('.wrap').addClass('connection-verified');
                    });
                </script>
                <?php
            } else {
                update_option('s3_master_connection_verified', false);
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php _e('AWS credentials saving failed!', 's3-master'); ?></strong></p>
                    <p><?php echo esc_html($test_result['message']); ?></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text"><?php _e('Dismiss this notice.', 's3-master'); ?></span>
                    </button>
                </div>
                <?php
            }
        } catch (Exception $e) {
            update_option('s3_master_connection_verified', false);
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong><?php _e('AWS credentials saving failed!', 's3-master'); ?></strong></p>
                <p><?php echo esc_html($e->getMessage()); ?></p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text"><?php _e('Dismiss this notice.', 's3-master'); ?></span>
                </button>
            </div>
            <?php
        }
    }
    
    if (isset($_POST['save_backup_settings'])) {
        update_option('s3_master_auto_backup', isset($_POST['auto_backup']));
        update_option('s3_master_backup_schedule', sanitize_text_field($_POST['backup_schedule']));
        update_option('s3_master_custom_backup_hours', intval($_POST['custom_backup_hours']));
        
        echo '<div class="notice notice-success"><p>' . __('Backup settings updated successfully!', 's3-master') . '</p></div>';
    }
    
    if (isset($_POST['set_default_bucket'])) {
        $bucket_name = sanitize_text_field($_POST['default_bucket']);
        $result = $bucket_manager->set_default_bucket($bucket_name);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . $result['message'] . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . $result['message'] . '</p></div>';
        }
    }
    
    if (isset($_POST['save_github_token'])) {
        $github_token = sanitize_text_field($_POST['github_token']);
        $result = $updater->set_github_token($github_token);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . $result['message'] . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . $result['message'] . '</p></div>';
        }
    }
}

// Get current settings
$aws_access_key_id = get_option('s3_master_aws_access_key_id', '');
$aws_secret_access_key = get_option('s3_master_aws_secret_access_key', '');
$aws_region = get_option('s3_master_aws_region', 'us-east-1');
$default_bucket = get_option('s3_master_default_bucket', '');
$auto_backup = get_option('s3_master_auto_backup', false);
$backup_schedule = get_option('s3_master_backup_schedule', 'hourly');
$custom_backup_hours = get_option('s3_master_custom_backup_hours', 1);
$github_token = get_option('s3_master_github_token', '');

// Get instances
$aws_client = S3_Master_AWS_Client::get_instance();
$bucket_manager = S3_Master_Bucket_Manager::get_instance();
$media_backup = S3_Master_Media_Backup::get_instance();
$updater = S3_Master_Updater::get_instance();

// Check if AWS credentials are set and connection is verified
$has_credentials = !empty($aws_access_key_id) && !empty($aws_secret_access_key);
$connection_verified = get_option('s3_master_connection_verified', false);

// Connection verification only happens manually when Test Connection is clicked

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'credentials';
?>

<style>
/* Hide managed tabs by default */
.s3-master-managed-tab {
    display: none;
}
/* Show managed tabs when connection is verified */
.connection-verified .s3-master-managed-tab {
    display: block;
}
/* Notice dismiss button styling */
.notice-dismiss {
    background: none !important;
    border: none !important;
    color: #b4b9be;
    cursor: pointer;
    float: right;
    font-size: 13px;
    line-height: 1.23076923;
    margin: -6px 0 0 0;
    padding: 9px;
    text-decoration: none;
}
.notice-dismiss:hover {
    color: #c00;
}
.notice-dismiss:before {
    content: "\f153";
    font-family: dashicons;
    font-size: 16px;
}
</style>

<div class="wrap <?php echo $connection_verified ? 'connection-verified' : ''; ?>">
    <h1><?php _e('S3 Master Settings', 's3-master'); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=s3-master&tab=credentials" class="nav-tab <?php echo $current_tab === 'credentials' ? 'nav-tab-active' : ''; ?>">
            <?php _e('AWS Credentials', 's3-master'); ?>
        </a>
        
        <?php if ($connection_verified): ?>
        <a href="?page=s3-master&tab=buckets" class="nav-tab <?php echo $current_tab === 'buckets' ? 'nav-tab-active' : ''; ?> s3-master-managed-tab">
            <?php _e('Bucket Management', 's3-master'); ?>
        </a>
        
        <a href="?page=s3-master&tab=files" class="nav-tab <?php echo $current_tab === 'files' ? 'nav-tab-active' : ''; ?> s3-master-managed-tab">
            <?php _e('File Manager', 's3-master'); ?>
        </a>
        
        <a href="?page=s3-master&tab=backup" class="nav-tab <?php echo $current_tab === 'backup' ? 'nav-tab-active' : ''; ?> s3-master-managed-tab">
            <?php _e('Media Backup', 's3-master'); ?>
        </a>
        
        <a href="?page=s3-master&tab=updates" class="nav-tab <?php echo $current_tab === 'updates' ? 'nav-tab-active' : ''; ?> s3-master-managed-tab">
            <?php _e('Plugin Updates', 's3-master'); ?>
        </a>
        <?php endif; ?>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'credentials' || !$connection_verified): ?>
            <div class="tab-pane active">
                <?php if (isset($credentials_saved) && $credentials_saved): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong><?php _e('AWS credentials saved successfully!', 's3-master'); ?></strong></p>
                        <p><?php _e('Please test the connection to verify your credentials.', 's3-master'); ?></p>
                        <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
                    </div>
                <?php endif; ?>
                <form method="post" action="">
                    <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('AWS Access Key ID', 's3-master'); ?></th>
                            <td>
                                <input type="text" name="aws_access_key_id" class="regular-text" value="<?php echo esc_attr($aws_access_key_id); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('AWS Secret Access Key', 's3-master'); ?></th>
                            <td>
                                <input type="password" name="aws_secret_access_key" class="regular-text" value="<?php echo esc_attr($aws_secret_access_key); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('AWS Region', 's3-master'); ?></th>
                            <td>
                                <select name="aws_region">
                                    <?php foreach ($aws_client->get_regions() as $region_code => $region_name): ?>
                                        <option value="<?php echo esc_attr($region_code); ?>" <?php selected($aws_region, $region_code); ?>>
                                            <?php echo esc_html($region_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Select your preferred AWS region', 's3-master'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="save_credentials" class="button-primary" value="<?php _e('Save Credentials', 's3-master'); ?>" />
                    </p>
                </form>
                
                <div id="connection-status" style="margin-top: 20px;"></div>
            </div>
        <?php elseif ($connection_verified): ?>
            <?php if ($current_tab === 'buckets'): ?>
                <div class="tab-pane active">
                    <div class="s3-master-section">
                        <h3><?php _e('Create New Bucket', 's3-master'); ?></h3>
                        <form id="create-bucket-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Bucket Name', 's3-master'); ?></th>
                                    <td>
                                        <input type="text" id="bucket-name" class="regular-text" placeholder="my-bucket-name" pattern="[a-z0-9][a-z0-9.-]*[a-z0-9]" />
                                        <p class="description"><?php _e('Bucket name requirements:', 's3-master'); ?></p>
                                        <ul class="bucket-requirements">
                                            <li><?php _e('Between 3 and 63 characters long', 's3-master'); ?></li>
                                            <li><?php _e('Can contain only lowercase letters, numbers, dots (.), and hyphens (-)', 's3-master'); ?></li>
                                            <li><?php _e('Must begin and end with a letter or number', 's3-master'); ?></li>
                                            <li><?php _e('Must be unique across all AWS accounts', 's3-master'); ?></li>
                                            <li><?php _e('Cannot be formatted as an IP address', 's3-master'); ?></li>
                                            <li><?php _e('Cannot have consecutive dots or dots adjacent to hyphens', 's3-master'); ?></li>
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Region', 's3-master'); ?></th>
                                    <td>
                                        <select id="bucket-region">
                                            <?php foreach ($aws_client->get_regions() as $region_code => $region_name): ?>
                                                <option value="<?php echo esc_attr($region_code); ?>" <?php selected($aws_region, $region_code); ?>>
                                                    <?php echo esc_html($region_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button-primary create-bucket-button"><?php _e('Create Bucket', 's3-master'); ?></button>
                            </p>
                        </form>
                    </div>
                    
                    <div class="s3-master-section">
                        <div class="section-header">
                            <h3><?php _e('Existing Buckets', 's3-master'); ?></h3>
                            <button id="refresh-buckets" class="refresh-list">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Refresh List', 's3-master'); ?>
                            </button>
                        </div>
                        <div id="buckets-list">
                            <div class="loading-indicator">
                                <p><?php _e('Click "Refresh List" to load your buckets.', 's3-master'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('Default Bucket', 's3-master'); ?></h3>
                        <form method="post" action="" id="default-bucket-form">
                            <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Select Default Bucket', 's3-master'); ?></th>
                                    <td>
                                        <div class="bucket-select-wrapper">
                                            <select name="default_bucket" 
                                                   id="default-bucket-select" 
                                                   class="regular-text"
                                                   data-current="<?php echo esc_attr($default_bucket); ?>"
                                                   <?php echo !$has_credentials ? 'disabled' : ''; ?>
                                            >
                                                <option value=""><?php _e('Select a bucket', 's3-master'); ?></option>
                                            </select>
                                            <button type="submit" 
                                                    class="button button-primary"
                                                    <?php echo !$has_credentials ? 'disabled' : ''; ?>
                                            >
                                                <?php _e('Verify & Set Default', 's3-master'); ?>
                                            </button>
                                            <div class="bucket-select-status"></div>
                                        </div>
                                        <p class="description"><?php _e('Select an existing bucket to set as default for file uploads and backups.', 's3-master'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>
            <?php elseif ($current_tab === 'files'): ?>
                <div class="tab-pane active">
                    <?php if (empty($default_bucket)): ?>
                        <div class="notification warning">
                            <p><?php _e('Please set a default bucket in the Bucket Management tab before using the file manager.', 's3-master'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="s3-master-section">
                            <div class="file-manager-toolbar">
                                <button id="upload-file" class="button"><?php _e('Upload File', 's3-master'); ?></button>
                                <button id="create-folder" class="button"><?php _e('Create Folder', 's3-master'); ?></button>
                                <button id="refresh-files" class="button"><?php _e('Refresh', 's3-master'); ?></button>
                                <span class="current-bucket"><?php printf(__('Bucket: %s', 's3-master'), esc_html($default_bucket)); ?></span>
                            </div>
                            
                            <div class="breadcrumb">
                                <span id="current-path">/</span>
                            </div>
                            
                            <!-- Search and Actions Bar -->
                            <div class="file-manager-search-bar">
                                <div class="search-container">
                                    <input type="text" id="file-search" placeholder="<?php _e('Search files and folders...', 's3-master'); ?>" />
                                    <button type="button" id="clear-search" class="button"><?php _e('Clear', 's3-master'); ?></button>
                                </div>
                                <div class="view-controls">
                                    <button type="button" id="grid-view" class="button view-toggle" data-view="grid"><?php _e('Grid', 's3-master'); ?></button>
                                    <button type="button" id="list-view" class="button view-toggle active" data-view="list"><?php _e('List', 's3-master'); ?></button>
                                </div>
                            </div>
                            
                            <!-- File Manager Header with Sortable Columns -->
                            <div class="file-manager-header">
                                <div class="header-controls">
                                    <label class="master-checkbox-container">
                                        <input type="checkbox" id="select-all-files" />
                                        <span class="checkmark"></span>
                                        <span class="label-text"><?php _e('Select All', 's3-master'); ?></span>
                                    </label>
                                    <div class="bulk-actions">
                                        <select id="bulk-action-select" disabled>
                                            <option value=""><?php _e('Bulk Actions', 's3-master'); ?></option>
                                            <option value="download"><?php _e('Download Selected', 's3-master'); ?></option>
                                            <option value="delete"><?php _e('Delete Selected', 's3-master'); ?></option>
                                        </select>
                                        <button type="button" id="apply-bulk-action" class="button" disabled><?php _e('Apply', 's3-master'); ?></button>
                                    </div>
                                </div>
                                <div class="sort-headers">
                                    <div class="sort-header" data-sort="name">
                                        <span><?php _e('Name', 's3-master'); ?></span>
                                        <span class="sort-indicator"></span>
                                    </div>
                                    <div class="sort-header" data-sort="type">
                                        <span><?php _e('Type', 's3-master'); ?></span>
                                        <span class="sort-indicator"></span>
                                    </div>
                                    <div class="sort-header" data-sort="size">
                                        <span><?php _e('Size', 's3-master'); ?></span>
                                        <span class="sort-indicator"></span>
                                    </div>
                                    <div class="sort-header" data-sort="modified">
                                        <span><?php _e('Modified', 's3-master'); ?></span>
                                        <span class="sort-indicator"></span>
                                    </div>
                                    <div class="sort-header">
                                        <span><?php _e('Actions', 's3-master'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Files List Container -->
                            <div id="files-list" class="files-container">
                                <div class="loading-indicator">
                                    <p><?php _e('Loading files...', 's3-master'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_tab === 'backup'): ?>
                <div class="tab-pane active">
                    <h2><?php _e('Media Backup Settings', 's3-master'); ?></h2>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('Auto Backup Settings', 's3-master'); ?></h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Enable Auto Backup', 's3-master'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="auto_backup" value="1" <?php checked($auto_backup); ?> />
                                            <?php _e('Automatically backup new media uploads to S3', 's3-master'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Backup Schedule', 's3-master'); ?></th>
                                    <td>
                                        <select name="backup_schedule" id="backup-schedule">
                                            <option value="immediate" <?php selected($backup_schedule, 'immediate'); ?>><?php _e('Immediate (on upload)', 's3-master'); ?></option>
                                            <option value="hourly" <?php selected($backup_schedule, 'hourly'); ?>><?php _e('Every Hour', 's3-master'); ?></option>
                                            <option value="s3_master_6_hours" <?php selected($backup_schedule, 's3_master_6_hours'); ?>><?php _e('Every 6 Hours', 's3-master'); ?></option>
                                            <option value="daily" <?php selected($backup_schedule, 'daily'); ?>><?php _e('Daily', 's3-master'); ?></option>
                                            <option value="s3_master_weekly" <?php selected($backup_schedule, 's3_master_weekly'); ?>><?php _e('Weekly', 's3-master'); ?></option>
                                            <option value="s3_master_monthly" <?php selected($backup_schedule, 's3_master_monthly'); ?>><?php _e('Monthly', 's3-master'); ?></option>
                                            <option value="custom" <?php selected($backup_schedule, 'custom'); ?>><?php _e('Custom', 's3-master'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="custom-hours-row" style="<?php echo $backup_schedule === 'custom' ? '' : 'display: none;'; ?>">
                                    <th scope="row"><?php _e('Custom Hours', 's3-master'); ?></th>
                                    <td>
                                        <input type="number" name="custom_backup_hours" value="<?php echo esc_attr($custom_backup_hours); ?>" min="1" max="168" />
                                        <p class="description"><?php _e('Backup interval in hours (1-168)', 's3-master'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <input type="submit" name="save_backup_settings" class="button-primary" value="<?php _e('Save Settings', 's3-master'); ?>" />
                            </p>
                        </form>
                    </div>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('Manual Backup', 's3-master'); ?></h3>
                        <p><?php _e('Backup all existing media files to S3.', 's3-master'); ?></p>
                        <button id="backup-existing-media" class="button button-primary"><?php _e('Backup Existing Media', 's3-master'); ?></button>
                        <div id="backup-progress" style="margin-top: 20px;"></div>
                    </div>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('Backup Statistics', 's3-master'); ?></h3>
                        <div id="backup-stats">
                            <?php
                            $stats = $media_backup->get_backup_stats();
                            ?>
                            <table class="widefat">
                                <tr>
                                    <td><?php _e('Total Media Files:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['total_media_files']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Total Media Size:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['total_media_size_formatted']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Files Backed Up:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['uploaded_files']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Backed Up Size:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['uploaded_size_formatted']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Remaining Files:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['remaining_files']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Remaining Size:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['remaining_size_formatted']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Backup Progress:', 's3-master'); ?></td>
                                    <td>
                                        <div class="backup-progress-bar">
                                            <div class="progress-bar" style="width: <?php echo esc_attr($stats['backup_progress']); ?>%;"></div>
                                        </div>
                                        <span class="progress-text"><?php echo esc_html($stats['backup_progress']); ?>%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e('Last Backup:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['last_backup']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Auto Backup:', 's3-master'); ?></td>
                                    <td><?php echo $stats['auto_backup_enabled'] ? __('Enabled', 's3-master') : __('Disabled', 's3-master'); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Schedule:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($stats['backup_schedule']); ?></td>
                                </tr>
                            </table>
                            
                            <style>
                            .backup-progress-bar {
                                width: 100%;
                                height: 20px;
                                background-color: #f0f0f0;
                                border-radius: 10px;
                                overflow: hidden;
                                margin-bottom: 5px;
                            }
                            .progress-bar {
                                height: 100%;
                                background: linear-gradient(90deg, #28a745, #20c997);
                                transition: width 0.3s ease;
                            }
                            .progress-text {
                                font-weight: bold;
                                color: #333;
                            }
                            </style>
                        </div>
                    </div>
                </div>
            <?php elseif ($current_tab === 'updates'): ?>
                <div class="tab-pane active">
                    <h2><?php _e('Plugin Updates', 's3-master'); ?></h2>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('Update Information', 's3-master'); ?></h3>
                        <div id="update-info">
                            <?php
                            $update_status = $updater->get_update_status();
                            ?>
                            <table class="widefat">
                                <tr>
                                    <td><?php _e('Current Version:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($update_status['current_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Latest Version:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($update_status['remote_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Update Available:', 's3-master'); ?></td>
                                    <td>
                                        <?php if ($update_status['update_available']): ?>
                                            <span style="color: orange;"><?php _e('Yes', 's3-master'); ?></span>
                                        <?php else: ?>
                                            <span style="color: green;"><?php _e('No', 's3-master'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e('Last Checked:', 's3-master'); ?></td>
                                    <td><?php echo esc_html($update_status['last_checked']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('GitHub Repository:', 's3-master'); ?></td>
                                    <td><a href="https://github.com/<?php echo esc_attr($update_status['github_repo']); ?>" target="_blank"><?php echo esc_html($update_status['github_repo']); ?></a></td>
                                </tr>
                            </table>
                        </div>
                        
                        <p class="submit">
                            <button id="check-updates" class="button-primary"><?php _e('Check for Updates', 's3-master'); ?></button>
                        </p>
                    </div>
                    
                    <div class="s3-master-section">
                        <h3><?php _e('GitHub Settings', 's3-master'); ?></h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('GitHub Token', 's3-master'); ?></th>
                                    <td>
                                        <input type="password" name="github_token" value="<?php echo esc_attr($github_token); ?>" class="regular-text" />
                                        <p class="description"><?php _e('Optional: GitHub personal access token for private repositories or to increase API rate limits.', 's3-master'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <input type="submit" name="save_github_token" class="button-primary" value="<?php _e('Save Token', 's3-master'); ?>" />
                            </p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
var s3_master_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('s3_master_nonce'); ?>',
    has_credentials: <?php echo (!empty($aws_access_key_id) && !empty($aws_secret_access_key)) ? 'true' : 'false'; ?>,
    connection_verified: <?php echo $connection_verified ? 'true' : 'false'; ?>,
    strings: {
        no_bucket: '<?php _e('NO BUCKET', 's3-master'); ?>',
        loading: '<?php _e('Loading buckets...', 's3-master'); ?>',
        confirm_delete: '<?php _e('Are you sure you want to delete this file?', 's3-master'); ?>',
        uploading: '<?php _e('Uploading...', 's3-master'); ?>'
    }
};

jQuery(document).ready(function($) {
    // Test connection handler
    $('#test-connection').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Testing...', 's3-master'); ?>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'test_connection',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                // Show dismissable success message
                $('#connection-status').html(
                    '<div class="notice notice-success is-dismissible">' +
                    '<p><strong><?php _e('Connection Successful!', 's3-master'); ?></strong></p>' +
                    '<p><?php _e('Great! Your AWS credentials are working.', 's3-master'); ?></p>' +
                    '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                    '</div>'
                );

                // Update connection status
                s3_master_ajax.connection_verified = true;
                
                // Show tabs after successful connection
                $('.s3-master-managed-tab').show();
                $('.wrap').addClass('connection-verified');

                // Make the dismiss button work
                $('#connection-status .notice-dismiss').on('click', function() {
                    $(this).parent().fadeOut();
                });

                // Update the connection status via AJAX without reload
                $.post(s3_master_ajax.ajax_url, {
                    action: 's3_master_ajax',
                    s3_action: 'verify_connection',
                    nonce: s3_master_ajax.nonce
                });
            } else {
                $('#connection-status').html(
                    '<div class="notice notice-error"><p>' + 
                    (response.data || '<?php _e('Connection failed. Please check your credentials and try again.', 's3-master'); ?>') + 
                    '</p></div>'
                );
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });

    // Initialize tabs visibility if connection is verified
    if (s3_master_ajax.connection_verified) {
        $('.s3-master-managed-tab').show();
    }
    
    // Global handler for dismissible notices
    $(document).on('click', '.notice-dismiss', function() {
        $(this).parent().fadeOut();
    });

    // Backup schedule change handler
    $('#backup-schedule').change(function() {
        if ($(this).val() === 'custom') {
            $('#custom-hours-row').show();
        } else {
            $('#custom-hours-row').hide();
        }
    });
    
    // Function to load bucket list
    function loadBucketList() {
        var button = $('#refresh-buckets');
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Loading...', 's3-master'); ?>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'list_buckets',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                var html = '<div class="buckets-container">';
                html += '<div class="buckets-header">';
                html += '<div class="header-item"><?php _e('Bucket Name', 's3-master'); ?></div>';
                html += '<div class="header-item"><?php _e('Creation Date', 's3-master'); ?></div>';
                html += '<div class="header-item"><?php _e('Actions', 's3-master'); ?></div>';
                html += '</div>';
                html += '<ul class="buckets-list">';
                
                if (response.data.length > 0) {
                    $.each(response.data, function(index, bucket) {
                        html += '<li class="bucket-item" data-bucket="' + bucket.Name + '" data-created="' + bucket.CreationDate + '">';
                        html += '<div class="bucket-name">' + bucket.Name + '</div>';
                        html += '<div class="bucket-date">' + bucket.CreationDate + '</div>';
                        html += '<div class="bucket-actions">';
                        html += '<button class="button delete-bucket" data-bucket="' + bucket.Name + '"><?php _e('Delete', 's3-master'); ?></button>';
                        html += '</div>';
                        html += '</li>';
                    });
                } else {
                    html += '<li class="no-buckets"><?php _e('No buckets found', 's3-master'); ?></li>';
                }
                
                html += '</ul></div>';
                $('#buckets-list').html(html);
            } else {
                $('#buckets-list').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    }
    
    // Refresh buckets
    $('#refresh-buckets').click(function() {
        loadBucketList();
    });
    
    // Auto-load buckets when on buckets tab and connection is verified
    if (s3_master_ajax.connection_verified && window.location.href.indexOf('tab=buckets') > -1) {
        loadBucketList();
    }
    
    // Create bucket
    $('#create-bucket-form').submit(function(e) {
        e.preventDefault();
        
        var bucketName = $('#bucket-name').val();
        var region = $('#bucket-region').val();
        
        if (!bucketName) {
            alert('<?php _e('Please enter a bucket name', 's3-master'); ?>');
            return;
        }
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'create_bucket',
            bucket_name: bucketName,
            region: region,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data);
                $('#bucket-name').val('');
                $('#refresh-buckets').click();
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        });
    });
    
    // Delete bucket
    $(document).on('click', '.delete-bucket', function() {
        var bucketName = $(this).data('bucket');
        
        if (!confirm('<?php _e('Are you sure you want to delete this bucket?', 's3-master'); ?>')) {
            return;
        }
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'delete_bucket',
            bucket_name: bucketName,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data);
                $('#refresh-buckets').click();
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        });
    });
    
    // Backup existing media
    $('#backup-existing-media').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        if (!confirm('<?php _e('This will backup all existing media files to S3. This may take a while. Continue?', 's3-master'); ?>')) {
            return;
        }
        
        button.prop('disabled', true).text('<?php _e('Backing up...', 's3-master'); ?>');
        $('#backup-progress').html('<p><?php _e('Backup in progress... Please wait.', 's3-master'); ?></p>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'backup_media',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('#backup-progress').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
            } else {
                $('#backup-progress').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Check for updates
    $('#check-updates').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Checking...', 's3-master'); ?>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_check_updates',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Verify and Set Default Bucket functionality
    $('#default-bucket-form').submit(function(e) {
        e.preventDefault();
        
        var bucketName = $('#default-bucket-select').val();
        var button = $('#default-bucket-form button[type="submit"]');
        var originalText = button.text();
        var statusDiv = $('.bucket-select-status');
        
        if (!bucketName) {
            statusDiv.html('<span style="color: red;">Please select a bucket</span>');
            return;
        }
        
        button.prop('disabled', true).text('<?php _e('Verifying...', 's3-master'); ?>');
        statusDiv.html('<span style="color: #0073aa;">Verifying bucket...</span>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'verify_and_set_default_bucket',
            bucket_name: bucketName,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                statusDiv.html('<span style="color: green;">✓ ' + response.data + '</span>');
                // Show success notice
                var notice = '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Default bucket has been set to "' + bucketName + '".</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
                $('.wrap h1').after(notice);
                
                // Make dismiss button work
                $('.notice-dismiss').on('click', function() {
                    $(this).parent().fadeOut();
                });
            } else {
                statusDiv.html('<span style="color: red;">✗ ' + response.data + '</span>');
            }
        }).fail(function() {
            statusDiv.html('<span style="color: red;">✗ Failed to verify bucket. Please try again.</span>');
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // File manager functionality
    var currentPrefix = '';
    
    
    function loadFiles(prefix) {
        prefix = prefix || '';
        currentPrefix = prefix;
        
        $('#files-list').html('<div class="loading-indicator"><p><?php _e('Loading...', 's3-master'); ?></p></div>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'list_files',
            bucket_name: '<?php echo esc_js($default_bucket); ?>',
            prefix: prefix,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayFiles(response.data);
                updateBreadcrumb(prefix);
            } else {
                $('#files-list').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        });
    }
    
    function displayFiles(files) {
        var html = '<table class="widefat"><thead><tr><th><?php _e('Name', 's3-master'); ?></th><th><?php _e('Type', 's3-master'); ?></th><th><?php _e('Size', 's3-master'); ?></th><th><?php _e('Modified', 's3-master'); ?></th><th><?php _e('Actions', 's3-master'); ?></th></tr></thead><tbody>';
        
        if (currentPrefix) {
            html += '<tr class="folder-row" data-prefix="' + currentPrefix.replace(/[^\/]*\/$/, '') + '">';
            html += '<td><span class="dashicons dashicons-arrow-up-alt2"></span> ..</td>';
            html += '<td>-</td><td>-</td><td>-</td><td>-</td>';
            html += '</tr>';
        }
        
        if (files.length > 0) {
            $.each(files, function(index, file) {
                html += '<tr class="' + (file.is_folder ? 'folder-row' : 'file-row') + '" data-key="' + file.key + '">';
                
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
                    html += '<button class="button download-file" data-key="' + file.key + '"><?php _e('Download', 's3-master'); ?></button> ';
                }
                
                html += '<button class="button delete-file" data-key="' + file.key + '"><?php _e('Delete', 's3-master'); ?></button>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5"><?php _e('No files found', 's3-master'); ?></td></tr>';
        }
        
        html += '</tbody></table>';
        $('#files-list').html(html);
    }
    
    function updateBreadcrumb(prefix) {
        var path = '/' + (prefix || '');
        $('#current-path').text(path);
    }
    
    // Initialize file manager if on files tab
    <?php if ($current_tab === 'files' && !empty($default_bucket)): ?>
    loadFiles();
    <?php endif; ?>
    
    // File manager event handlers
    $('#refresh-files').click(function() {
        loadFiles(currentPrefix);
    });
    
    $(document).on('click', '.folder-row', function() {
        var prefix = $(this).data('prefix');
        if (typeof prefix !== 'undefined') {
            loadFiles(prefix);
        }
    });
    
    $(document).on('click', '.delete-file', function(e) {
        e.stopPropagation();
        
        var key = $(this).data('key');
        
        if (!confirm(s3_master_ajax.strings.confirm_delete)) {
            return;
        }
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'delete_file',
            bucket_name: '<?php echo esc_js($default_bucket); ?>',
            key: key,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                loadFiles(currentPrefix);
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        });
    });
    
    $('#upload-file').click(function() {
        $('#file-upload-area').toggle();
    });
    
    $('#cancel-upload').click(function() {
        $('#file-upload-area').hide();
        $('#file-input').val('');
    });
    
    $('#create-folder').click(function() {
        var folderName = prompt('<?php _e('Enter folder name:', 's3-master'); ?>');
        if (!folderName) return;
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'create_folder',
            bucket_name: '<?php echo esc_js($default_bucket); ?>',
            folder_name: folderName,
            prefix: currentPrefix,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                loadFiles(currentPrefix);
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        });
    });
    
    $('#file-upload-form').submit(function(e) {
        e.preventDefault();
        
        var fileInput = $('#file-input')[0];
        if (!fileInput.files.length) {
            alert('<?php _e('Please select files to upload', 's3-master'); ?>');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 's3_master_ajax');
        formData.append('s3_action', 'upload_file');
        formData.append('bucket_name', '<?php echo esc_js($default_bucket); ?>');
        formData.append('prefix', currentPrefix);
        formData.append('nonce', s3_master_ajax.nonce);
        formData.append('file', fileInput.files[0]);
        
        $('#upload-progress').html('<p>' + s3_master_ajax.strings.uploading + '</p>');
        
        $.ajax({
            url: s3_master_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#upload-progress').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    $('#file-input').val('');
                    loadFiles(currentPrefix);
                } else {
                    $('#upload-progress').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#upload-progress').html('<div class="notice notice-error"><p>' + s3_master_ajax.strings.error + '</p></div>');
            }
        });
    });
});
</script>
