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
        
        $aws_client->update_credentials($access_key_id, $secret_access_key, $region);
        
        echo '<div class="notice notice-success"><p>' . __('AWS credentials updated successfully!', 's3-master') . '</p></div>';
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

// Check if AWS credentials are provided
$has_aws_credentials = !empty($aws_access_key_id) && !empty($aws_secret_access_key);

// Get instances
$aws_client = S3_Master_AWS_Client::get_instance();
$bucket_manager = S3_Master_Bucket_Manager::get_instance();
$media_backup = S3_Master_Media_Backup::get_instance();
$updater = S3_Master_Updater::get_instance();

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'credentials';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=s3-master&tab=credentials" class="nav-tab <?php echo $current_tab === 'credentials' ? 'nav-tab-active' : ''; ?>">
            <?php _e('AWS Credentials', 's3-master'); ?>
        </a>
        <?php if ($has_aws_credentials): ?>
        <a href="?page=s3-master&tab=buckets" class="nav-tab <?php echo $current_tab === 'buckets' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Bucket Management', 's3-master'); ?>
        </a>
        <a href="?page=s3-master&tab=files" class="nav-tab <?php echo $current_tab === 'files' ? 'nav-tab-active' : ''; ?>">
            <?php _e('File Manager', 's3-master'); ?>
        </a>
        <a href="?page=s3-master&tab=backup" class="nav-tab <?php echo $current_tab === 'backup' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Media Backup', 's3-master'); ?>
        </a>
        <a href="?page=s3-master&tab=storage" class="nav-tab <?php echo $current_tab === 'storage' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Storage Analytics', 's3-master'); ?>
        </a>
        <a href="?page=s3-master&tab=updates" class="nav-tab <?php echo $current_tab === 'updates' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Plugin Updates', 's3-master'); ?>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="tab-content">
        <?php if ($current_tab === 'credentials'): ?>
            <div class="tab-pane active">
                <h2><?php _e('AWS Credentials', 's3-master'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('AWS Access Key ID', 's3-master'); ?></th>
                            <td>
                                <input type="text" name="aws_access_key_id" value="<?php echo esc_attr($aws_access_key_id); ?>" class="regular-text" />
                                <p class="description"><?php _e('Your AWS Access Key ID', 's3-master'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('AWS Secret Access Key', 's3-master'); ?></th>
                            <td>
                                <input type="password" name="aws_secret_access_key" value="<?php echo esc_attr($aws_secret_access_key); ?>" class="regular-text" />
                                <p class="description"><?php _e('Your AWS Secret Access Key', 's3-master'); ?></p>
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
                        <?php if ($has_aws_credentials): ?>
                        <button type="button" id="test-connection" class="button"><?php _e('Test Connection', 's3-master'); ?></button>
                        <?php endif; ?>
                    </p>
                </form>
                
                <?php if ($has_aws_credentials): ?>
                <div id="connection-status" style="margin-top: 20px;"></div>
                <?php else: ?>
                <div class="notice notice-info" style="margin-top: 20px;">
                    <p><?php _e('Please enter your AWS Access Key ID and Secret Access Key above, then save the credentials to access other features.', 's3-master'); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($current_tab === 'buckets' && $has_aws_credentials): ?>
            <div class="tab-pane active">
                <h2><?php _e('Bucket Management', 's3-master'); ?></h2>
                
                <div class="s3-master-section">
                    <h3><?php _e('Create New Bucket', 's3-master'); ?></h3>
                    <form id="create-bucket-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Bucket Name', 's3-master'); ?></th>
                                <td>
                                    <input type="text" id="bucket-name" class="regular-text" placeholder="my-bucket-name" />
                                    <p class="description"><?php _e('Bucket names must be between 3-63 characters, contain only lowercase letters, numbers, and hyphens.', 's3-master'); ?></p>
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
                            <button type="submit" class="button-primary"><?php _e('Create Bucket', 's3-master'); ?></button>
                        </p>
                    </form>
                </div>
                
                <div class="s3-master-section s3-buckets-section">
                    <div class="section-header">
                        <h3><span class="dashicons dashicons-portfolio"></span><?php _e('Existing Buckets', 's3-master'); ?></h3>
                        <button id="refresh-buckets" class="button button-secondary"><span class="dashicons dashicons-update"></span><?php _e('Refresh List', 's3-master'); ?></button>
                    </div>
                    <div id="bucket-storage-overview" class="storage-overview">
                        <!-- Storage overview will be populated by JavaScript -->
                    </div>
                    <div id="buckets-list" class="buckets-grid">
                        <div class="loading-spinner">
                            <span class="dashicons dashicons-update spin"></span>
                            <p><?php _e('Click "Refresh List" to load your buckets.', 's3-master'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="s3-master-section">
                    <h3><?php _e('Default Bucket', 's3-master'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('s3_master_settings', 's3_master_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Default Bucket', 's3-master'); ?></th>
                                <td>
                                    <input type="text" name="default_bucket" value="<?php echo esc_attr($default_bucket); ?>" class="regular-text" />
                                    <p class="description"><?php _e('The default bucket to use for file uploads and backups.', 's3-master'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="set_default_bucket" class="button-primary" value="<?php _e('Set Default Bucket', 's3-master'); ?>" />
                        </p>
                    </form>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'files' && $has_aws_credentials): ?>
            <div class="tab-pane active">
                <h2><?php _e('File Manager', 's3-master'); ?></h2>
                
                <?php if (empty($default_bucket)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Please set a default bucket in the Bucket Management tab before using the file manager.', 's3-master'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="s3-master-file-manager">
                        <div class="file-manager-toolbar">
                            <input type="text" id="file-search" placeholder="<?php _e('Search files...', 's3-master'); ?>" />
                            <button id="upload-file" class="button button-primary"><span class="dashicons dashicons-upload"></span><?php _e('Upload File', 's3-master'); ?></button>
                            <button id="create-folder" class="button"><span class="dashicons dashicons-plus-alt"></span><?php _e('Create Folder', 's3-master'); ?></button>
                            <button id="refresh-files" class="button"><span class="dashicons dashicons-update"></span><?php _e('Refresh', 's3-master'); ?></button>
                            <span class="current-bucket" id="current-bucket"><?php printf(__('Bucket: %s', 's3-master'), esc_html($default_bucket)); ?></span>
                        </div>
                        
                        <div class="breadcrumb">
                            <span id="current-path">/</span>
                        </div>
                        
                        <div id="file-upload-area" style="display: none;">
                            <form id="file-upload-form" enctype="multipart/form-data">
                                <input type="file" id="file-input" multiple />
                                <button type="submit" class="button-primary"><?php _e('Upload', 's3-master'); ?></button>
                                <button type="button" id="cancel-upload" class="button"><?php _e('Cancel', 's3-master'); ?></button>
                            </form>
                            <div id="upload-progress"></div>
                        </div>
                        
                        <div id="files-list">
                            <p><?php _e('Loading files...', 's3-master'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($current_tab === 'backup' && $has_aws_credentials): ?>
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
                
                <div class="s3-master-section backup-section">
                    <h3><span class="dashicons dashicons-analytics"></span><?php _e('Manual Backup', 's3-master'); ?></h3>
                    <p><?php _e('Calculate and selectively backup existing media files to S3.', 's3-master'); ?></p>
                    
                    <div class="manual-backup-controls">
                        <button id="calculate-media" class="button button-secondary"><span class="dashicons dashicons-chart-pie"></span><?php _e('Calculate Media Files', 's3-master'); ?></button>
                        <button id="backup-selected-media" class="button button-primary" style="display: none;"><span class="dashicons dashicons-backup"></span><?php _e('Backup Selected Media', 's3-master'); ?></button>
                        <button id="select-all-media" class="button" style="display: none;"><span class="dashicons dashicons-yes"></span><?php _e('Select All', 's3-master'); ?></button>
                        <button id="deselect-all-media" class="button" style="display: none;"><span class="dashicons dashicons-no"></span><?php _e('Deselect All', 's3-master'); ?></button>
                    </div>
                    
                    <div id="media-calculation-progress" style="margin-top: 20px; display: none;">
                        <div class="calculation-spinner">
                            <span class="dashicons dashicons-update spin"></span>
                            <span><?php _e('Calculating media files...', 's3-master'); ?></span>
                        </div>
                    </div>
                    
                    <div id="media-categories" class="media-categories-grid" style="display: none; margin-top: 24px;">
                        <!-- Media categories will be populated by JavaScript -->
                    </div>
                    
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
                                <td><?php _e('Total Files Backed Up:', 's3-master'); ?></td>
                                <td><?php echo esc_html($stats['total_files']); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Total Size:', 's3-master'); ?></td>
                                <td><?php echo esc_html($stats['total_size_formatted']); ?></td>
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
                    </div>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'storage' && $has_aws_credentials): ?>
            <div class="tab-pane active">
                <h2><?php _e('Storage Analytics', 's3-master'); ?></h2>
                
                <div class="storage-overview">
                    <h3><?php _e('Storage Overview', 's3-master'); ?></h3>
                    <div class="storage-stats" id="storage-overview-stats">
                        <div class="storage-stat">
                            <span class="storage-stat-value" id="total-files">-</span>
                            <span class="storage-stat-label"><?php _e('Total Files', 's3-master'); ?></span>
                        </div>
                        <div class="storage-stat">
                            <span class="storage-stat-value" id="total-size">-</span>
                            <span class="storage-stat-label"><?php _e('Total Size', 's3-master'); ?></span>
                        </div>
                        <div class="storage-stat">
                            <span class="storage-stat-value" id="file-types">-</span>
                            <span class="storage-stat-label"><?php _e('File Types', 's3-master'); ?></span>
                        </div>
                        <div class="storage-stat">
                            <span class="storage-stat-value" id="avg-file-size">-</span>
                            <span class="storage-stat-label"><?php _e('Avg File Size', 's3-master'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="storage-chart-container">
                    <h3 class="storage-chart-title"><?php _e('Storage by File Extension', 's3-master'); ?></h3>
                    <div class="manual-backup-controls">
                        <button id="analyze-storage" class="button button-primary">
                            <span class="dashicons dashicons-chart-pie"></span>
                            <?php _e('Analyze Storage', 's3-master'); ?>
                        </button>
                        <button id="refresh-storage-analysis" class="button button-secondary" style="display: none;">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh Analysis', 's3-master'); ?>
                        </button>
                    </div>
                    
                    <div id="storage-analysis-progress" style="margin-top: 20px; display: none;">
                        <div class="calculation-spinner">
                            <span class="dashicons dashicons-update spin"></span>
                            <span><?php _e('Analyzing storage usage...', 's3-master'); ?></span>
                        </div>
                    </div>
                    
                    <div id="storage-breakdown" class="storage-breakdown" style="display: none;">
                        <!-- Storage breakdown will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="storage-chart-container">
                    <h3 class="storage-chart-title"><?php _e('Recent Activity', 's3-master'); ?></h3>
                    <div id="recent-activity-stats">
                        <?php
                        $backup_stats = $media_backup->get_backup_stats();
                        if (!empty($backup_stats['recent_backups'])):
                        ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('File', 's3-master'); ?></th>
                                    <th><?php _e('Status', 's3-master'); ?></th>
                                    <th><?php _e('Date', 's3-master'); ?></th>
                                    <th><?php _e('Message', 's3-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($backup_stats['recent_backups'], 0, 10) as $activity): ?>
                                <tr>
                                    <td><?php echo esc_html(basename($activity['file_path'])); ?></td>
                                    <td>
                                        <span class="backup-status-indicator backup-status-<?php echo esc_attr($activity['status']); ?>">
                                            <?php echo esc_html(ucfirst($activity['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($activity['timestamp']); ?></td>
                                    <td><?php echo esc_html($activity['message']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p><?php _e('No recent backup activity found.', 's3-master'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'updates' && $has_aws_credentials): ?>
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
        <?php elseif (!$has_aws_credentials && $current_tab !== 'credentials'): ?>
            <div class="tab-pane active">
                <div class="notice notice-warning">
                    <p><?php _e('Please configure your AWS credentials in the AWS Credentials tab to access this feature.', 's3-master'); ?></p>
                    <p><a href="?page=s3-master&tab=credentials" class="button button-primary"><?php _e('Configure AWS Credentials', 's3-master'); ?></a></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Backup schedule change handler
    $('#backup-schedule').change(function() {
        if ($(this).val() === 'custom') {
            $('#custom-hours-row').show();
        } else {
            $('#custom-hours-row').hide();
        }
    });
    
    // Handle credential form submission
    $('form').on('submit', function(e) {
        var form = $(this);
        if (form.find('input[name="save_credentials"]').length > 0) {
            var accessKey = form.find('input[name="aws_access_key_id"]').val();
            var secretKey = form.find('input[name="aws_secret_access_key"]').val();
            
            if (accessKey && secretKey) {
                // Credentials are being saved, page will reload after submission
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        }
    });
    
    // Test connection
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
                $('#connection-status').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
            } else {
                $('#connection-status').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Refresh buckets
    $('#refresh-buckets').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Loading...', 's3-master'); ?>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'list_buckets',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                var html = '<table class="widefat"><thead><tr><th><?php _e('Bucket Name', 's3-master'); ?></th><th><?php _e('Creation Date', 's3-master'); ?></th><th><?php _e('Actions', 's3-master'); ?></th></tr></thead><tbody>';
                
                if (response.data.length > 0) {
                    $.each(response.data, function(index, bucket) {
                        html += '<tr>';
                        html += '<td>' + bucket.Name + '</td>';
                        html += '<td>' + bucket.CreationDate + '</td>';
                        html += '<td><button class="button delete-bucket" data-bucket="' + bucket.Name + '"><?php _e('Delete', 's3-master'); ?></button></td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="3"><?php _e('No buckets found', 's3-master'); ?></td></tr>';
                }
                
                html += '</tbody></table>';
                $('#buckets-list').html(html);
            } else {
                $('#buckets-list').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
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
    
    // File manager functionality
    var currentPrefix = '';
    
    function loadFiles(prefix) {
        prefix = prefix || '';
        currentPrefix = prefix;
        
        $('#files-list').html('<p><?php _e('Loading...', 's3-master'); ?></p>');
        
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
                    html += '<button class="button view-file" data-key="' + file.key + '"><span class="dashicons dashicons-visibility"></span> \u003c?php _e('View', 's3-master'); ?\u003e</button> ';
                    html += '<button class="button download-file" data-key="' + file.key + '">\u003c?php _e('Download', 's3-master'); ?\u003e</button> ';
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
    
    $(document).on('click', '.view-file', function(e) {
        e.stopPropagation();
        
        var key = $(this).data('key');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'get_file_url',
            bucket_name: '<?php echo esc_js($default_bucket); ?>',
            key: key,
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                window.open(response.data.url, '_blank');
            } else {
                alert('<?php _e('Error:', 's3-master'); ?> ' + response.data);
            }
        });
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
    
    // Storage Analytics functionality
    $('#analyze-storage').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true);
        $('#storage-analysis-progress').show();
        $('#storage-breakdown').hide();
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'analyze_storage',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayStorageAnalysis(response.data);
                $('#refresh-storage-analysis').show();
            } else {
                $('#storage-breakdown').html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }).always(function() {
            button.prop('disabled', false);
            $('#storage-analysis-progress').hide();
        });
    });
    
    $('#refresh-storage-analysis').click(function() {
        $('#analyze-storage').click();
    });
    
    function displayStorageAnalysis(data) {
        // Update overview stats
        $('#total-files').text(data.overview.total_files);
        $('#total-size').text(data.overview.total_size_formatted);
        $('#file-types').text(data.overview.file_types);
        $('#avg-file-size').text(data.overview.avg_file_size_formatted);
        
        // Create storage breakdown
        var html = '';
        if (data.extensions && data.extensions.length > 0) {
            $.each(data.extensions, function(index, ext) {
                var extClass = 'ext-' + ext.extension.replace('.', '');
                html += '<div class="storage-item ' + extClass + '">';
                html += '<div class="storage-item-icon"></div>';
                html += '<div class="storage-item-info">';
                html += '<p class="storage-item-label">' + ext.extension.toUpperCase() + ' Files</p>';
                html += '<p class="storage-item-value">' + ext.size_formatted + ' (' + ext.count + ' files)</p>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html = '<p><?php _e('No files found for analysis.', 's3-master'); ?></p>';
        }
        
        $('#storage-breakdown').html(html).show();
    }
    
    // Calculate Media functionality
    $('#calculate-media').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true);
        $('#media-calculation-progress').show();
        $('#media-categories').hide();
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'calculate_media',
            nonce: s3_master_ajax.nonce
        }, function(response) {
            if (response.success) {
                displayMediaCategories(response.data);
                $('#backup-selected-media, #select-all-media, #deselect-all-media').show();
            } else {
                $('#media-categories').html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }).always(function() {
            button.prop('disabled', false);
            $('#media-calculation-progress').hide();
        });
    });
    
    function displayMediaCategories(data) {
        var html = '';
        var totalSize = data.total_size || 1; // Prevent division by zero
        
        if (data.categories && Object.keys(data.categories).length > 0) {
            $.each(data.categories, function(category, info) {
                var percentage = Math.round((info.size / totalSize) * 100);
                
                html += '<div class="media-category-card">';
                html += '<div class="media-category-header">';
                html += '<h4 class="media-category-title">' + category + '</h4>';
                html += '<div class="media-category-select">';
                html += '<input type="checkbox" class="category-checkbox" data-category="' + category + '" checked> ';
                html += '<span><?php _e('Include', 's3-master'); ?></span>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="media-category-stats">';
                html += '<div class="media-stat">';
                html += '<span class="media-stat-label"><?php _e('Files', 's3-master'); ?></span>';
                html += '<span class="media-stat-value">' + info.count + '</span>';
                html += '</div>';
                html += '<div class="media-stat">';
                html += '<span class="media-stat-label"><?php _e('Size', 's3-master'); ?></span>';
                html += '<span class="media-stat-value">' + info.size_formatted + '</span>';
                html += '</div>';
                html += '<div class="media-stat">';
                html += '<span class="media-stat-label"><?php _e('Percentage', 's3-master'); ?></span>';
                html += '<span class="media-stat-value">' + percentage + '%</span>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="media-size-bar">';
                html += '<div class="media-size-fill" style="width: ' + percentage + '%;"></div>';
                html += '</div>';
                html += '</div>';
            });
        } else {
            html = '<p><?php _e('No media files found for backup.', 's3-master'); ?></p>';
        }
        
        $('#media-categories').html(html).show();
    }
    
    // Select/Deselect all media
    $('#select-all-media').click(function() {
        $('.category-checkbox').prop('checked', true);
    });
    
    $('#deselect-all-media').click(function() {
        $('.category-checkbox').prop('checked', false);
    });
    
    // Backup selected media
    $('#backup-selected-media').click(function() {
        var selectedCategories = [];
        $('.category-checkbox:checked').each(function() {
            selectedCategories.push($(this).data('category'));
        });
        
        if (selectedCategories.length === 0) {
            alert('<?php _e('Please select at least one category to backup.', 's3-master'); ?>');
            return;
        }
        
        if (!confirm('<?php _e('This will backup the selected media files to S3. This may take a while. Continue?', 's3-master'); ?>')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Backing up...', 's3-master'); ?>');
        $('#backup-progress').html('<p><?php _e('Backup in progress... Please wait.', 's3-master'); ?></p>');
        
        $.post(s3_master_ajax.ajax_url, {
            action: 's3_master_ajax',
            s3_action: 'backup_selected_media',
            categories: selectedCategories,
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
});
</script>
