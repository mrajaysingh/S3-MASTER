<?php
/**
 * S3 Master Uninstall
 * 
 * This file runs when the plugin is uninstalled to clean up all plugin data.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
$options = array(
    // AWS Credentials
    's3_master_aws_access_key_id',
    's3_master_aws_secret_access_key',
    's3_master_aws_region',
    
    // Bucket Settings
    's3_master_default_bucket',
    's3_master_bucket_list',
    's3_master_bucket_stats',
    
    // Backup Settings
    's3_master_auto_backup',
    's3_master_backup_schedule',
    's3_master_custom_backup_hours',
    's3_master_last_backup_time',
    's3_master_backup_status',
    's3_master_backup_log',
    
    // File Manager Settings
    's3_master_file_list_cache',
    's3_master_file_manager_settings',
    
    // Plugin Settings
    's3_master_version',
    's3_master_installed_time',
    's3_master_last_check',
    's3_master_github_token',
    
    // User Preferences
    's3_master_user_preferences',
    's3_master_dismissed_notices'
);

// Delete each option
foreach ($options as $option) {
    delete_option($option);
    // Also delete multisite options if in multisite
    if (is_multisite()) {
        delete_site_option($option);
    }
}

// Clear scheduled events
wp_clear_scheduled_hook('s3_master_backup_cron');
wp_clear_scheduled_hook('s3_master_cleanup_cron');
wp_clear_scheduled_hook('s3_master_sync_cron');

// Get all cron events containing our prefix
$crons = _get_cron_array();
if (is_array($crons)) {
    foreach ($crons as $timestamp => $cron) {
        foreach ($cron as $hook => $events) {
            if (strpos($hook, 's3_master_') === 0) {
                wp_clear_scheduled_hook($hook);
            }
        }
    }
}

// Clean up transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_s3_master_') . '%',
        $wpdb->esc_like('_transient_timeout_s3_master_') . '%'
    )
);

// If multisite, clean up network transients
if (is_multisite()) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            $wpdb->esc_like('_site_transient_s3_master_') . '%',
            $wpdb->esc_like('_site_transient_timeout_s3_master_') . '%'
        )
    );
}

// Clean up user meta
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('s3_master_') . '%'
    )
);

// Remove any custom capabilities
$roles = wp_roles();
$capabilities = array(
    's3_master_manage_buckets',
    's3_master_upload_files',
    's3_master_delete_files',
    's3_master_manage_backups',
    's3_master_manage_settings'
);

foreach ($roles->role_objects as $role) {
    foreach ($capabilities as $cap) {
        $role->remove_cap($cap);
    }
}

// Clean up any remaining database entries
$tables = array(
    $wpdb->prefix . 'options',
    $wpdb->prefix . 'usermeta',
    is_multisite() ? $wpdb->prefix . 'sitemeta' : null
);

foreach ($tables as $table) {
    if ($table) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('s3master_') . '%',
                $wpdb->esc_like('s3-master_') . '%',
                $wpdb->esc_like('s3_master_') . '%'
            )
        );
    }
}

// Clear any plugin logs if they exist
$upload_dir = wp_upload_dir();
$log_dir = trailingslashit($upload_dir['basedir']) . 's3-master-logs';
if (file_exists($log_dir)) {
    foreach (glob($log_dir . '/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($log_dir);
}

// Clear any cached files
$cache_dir = trailingslashit(WP_CONTENT_DIR) . 'cache/s3-master';
if (file_exists($cache_dir)) {
    foreach (glob($cache_dir . '/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($cache_dir);
} 