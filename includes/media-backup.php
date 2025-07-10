<?php
/**
 * Media Backup Class for S3 Master
 * 
 * Handles automatic media backup to S3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_Media_Backup {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
    /**
     * AWS Client instance
     */
    private $aws_client = null;
    
    /**
     * File Manager instance
     */
    private $file_manager = null;
    
    /**
     * Bucket Manager instance
     */
    private $bucket_manager = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->aws_client = S3_Master_AWS_Client::get_instance();
        $this->file_manager = S3_Master_File_Manager::get_instance();
        $this->bucket_manager = S3_Master_Bucket_Manager::get_instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into media upload
        add_action('wp_handle_upload', array($this, 'handle_media_upload'), 10, 2);
        add_action('add_attachment', array($this, 'handle_attachment_upload'));
        
        // Cron hooks
        add_action('s3_master_backup_check', array($this, 'scheduled_backup_check'));
        add_action('s3_master_media_backup', array($this, 'scheduled_media_backup'));
        
        // Schedule backup check
        $this->schedule_backup_check();
    }
    
    /**
     * Handle media upload
     */
    public function handle_media_upload($file, $overrides = array()) {
        if (!get_option('s3_master_auto_backup', false)) {
            return $file;
        }
        
        $backup_schedule = get_option('s3_master_backup_schedule', 'hourly');
        
        if ($backup_schedule === 'immediate') {
            $this->backup_single_file($file['file']);
        }
        
        return $file;
    }
    
    /**
     * Handle attachment upload
     */
    public function handle_attachment_upload($attachment_id) {
        if (!get_option('s3_master_auto_backup', false)) {
            return;
        }
        
        $backup_schedule = get_option('s3_master_backup_schedule', 'hourly');
        
        if ($backup_schedule === 'immediate') {
            $file_path = get_attached_file($attachment_id);
            if ($file_path) {
                $this->backup_single_file($file_path);
            }
        }
    }
    
    /**
     * Backup single file
     */
    private function backup_single_file($file_path) {
        $default_bucket = $this->bucket_manager->get_default_bucket();
        
        if (empty($default_bucket)) {
            error_log('S3 Master: No default bucket configured for backup');
            return false;
        }
        
        // Get relative path from uploads directory
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
        $relative_path = ltrim($relative_path, '/\\');
        
        // Prepare file data
        $file_data = array(
            'tmp_name' => $file_path,
            'name' => basename($file_path),
            'error' => UPLOAD_ERR_OK
        );
        
        // Upload to S3
        $result = $this->file_manager->upload_file($file_data, $default_bucket, 'wp-content/uploads/' . dirname($relative_path));
        
        if ($result['success']) {
            // Log successful backup
            $this->log_backup_activity($file_path, 'success');
            
            // Update backup metadata
            $key = isset($result['key']) ? $result['key'] : basename($file_path);
            $this->update_backup_metadata($file_path, $key);
        } else {
            error_log('S3 Master: Failed to backup file: ' . $file_path . ' - ' . $result['message']);
            $this->log_backup_activity($file_path, 'failed', $result['message']);
        }
        
        return $result;
    }
    
    /**
     * Backup existing media files
     */
    public function backup_existing_media() {
        $default_bucket = $this->bucket_manager->get_default_bucket();
        
        if (empty($default_bucket)) {
            return array(
                'success' => false,
                'message' => __('No default bucket configured', 's3-master')
            );
        }
        
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!is_dir($uploads_path)) {
            return array(
                'success' => false,
                'message' => __('Uploads directory not found', 's3-master')
            );
        }
        
        $files = $this->get_media_files($uploads_path);
        $successful_uploads = 0;
        $failed_uploads = 0;
        
        foreach ($files as $file_path) {
            $result = $this->backup_single_file($file_path);
            
            if ($result && $result['success']) {
                $successful_uploads++;
            } else {
                $failed_uploads++;
            }
        }
        
        return array(
            'success' => true,
            'count' => $successful_uploads,
            'failed' => $failed_uploads,
            'message' => sprintf(__('Backup completed: %d successful, %d failed', 's3-master'), $successful_uploads, $failed_uploads)
        );
    }
    
    /**
     * Get media files recursively
     */
    private function get_media_files($directory) {
        $files = array();
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $file_path = $file->getPathname();
                
                // Check if it's a media file
                if ($this->is_media_file($file_path)) {
                    $files[] = $file_path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Check if file is a media file
     */
    private function is_media_file($file_path) {
        $media_extensions = array(
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            'mp3', 'wav', 'ogg', 'wma', 'flac',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'rar', '7z', 'tar', 'gz'
        );
        
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        return in_array($file_extension, $media_extensions);
    }
    
    /**
     * Schedule backup check
     */
    private function schedule_backup_check() {
        if (!wp_next_scheduled('s3_master_backup_check')) {
            wp_schedule_event(time(), 'hourly', 's3_master_backup_check');
        }
    }
    
    /**
     * Scheduled backup check
     */
    public function scheduled_backup_check() {
        if (!get_option('s3_master_auto_backup', false)) {
            return;
        }
        
        $backup_schedule = get_option('s3_master_backup_schedule', 'hourly');
        
        if ($backup_schedule === 'immediate') {
            return; // Already handled in real-time
        }
        
        // Check if it's time for backup
        if ($this->is_backup_time($backup_schedule)) {
            $this->scheduled_media_backup();
        }
    }
    
    /**
     * Check if it's time for backup
     */
    private function is_backup_time($schedule) {
        $last_backup = get_option('s3_master_last_backup', 0);
        $current_time = time();
        $interval = 0;
        
        switch ($schedule) {
            case 'hourly':
                $interval = HOUR_IN_SECONDS;
                break;
            case 's3_master_6_hours':
                $interval = 6 * HOUR_IN_SECONDS;
                break;
            case 'daily':
                $interval = DAY_IN_SECONDS;
                break;
            case 's3_master_weekly':
                $interval = WEEK_IN_SECONDS;
                break;
            case 's3_master_monthly':
                $interval = MONTH_IN_SECONDS;
                break;
            case 'custom':
                $custom_hours = get_option('s3_master_custom_backup_hours', 1);
                $interval = $custom_hours * HOUR_IN_SECONDS;
                break;
        }
        
        return ($current_time - $last_backup) >= $interval;
    }
    
    /**
     * Scheduled media backup
     */
    public function scheduled_media_backup() {
        if (!get_option('s3_master_auto_backup', false)) {
            return;
        }
        
        $result = $this->backup_new_media_files();
        
        // Update last backup time
        update_option('s3_master_last_backup', time());
        
        // Log backup activity
        $this->log_backup_activity('scheduled_backup', $result['success'] ? 'success' : 'failed', $result['message']);
    }
    
    /**
     * Backup new media files
     */
    private function backup_new_media_files() {
        $default_bucket = $this->bucket_manager->get_default_bucket();
        
        if (empty($default_bucket)) {
            return array(
                'success' => false,
                'message' => __('No default bucket configured', 's3-master')
            );
        }
        
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        $files = $this->get_media_files($uploads_path);
        $new_files = array();
        
        // Filter new files
        foreach ($files as $file_path) {
            if (!$this->is_file_backed_up($file_path)) {
                $new_files[] = $file_path;
            }
        }
        
        if (empty($new_files)) {
            return array(
                'success' => true,
                'count' => 0,
                'message' => __('No new files to backup', 's3-master')
            );
        }
        
        $successful_uploads = 0;
        $failed_uploads = 0;
        
        foreach ($new_files as $file_path) {
            $result = $this->backup_single_file($file_path);
            
            if ($result && $result['success']) {
                $successful_uploads++;
            } else {
                $failed_uploads++;
            }
        }
        
        return array(
            'success' => true,
            'count' => $successful_uploads,
            'failed' => $failed_uploads,
            'message' => sprintf(__('Backup completed: %d new files uploaded, %d failed', 's3-master'), $successful_uploads, $failed_uploads)
        );
    }
    
    /**
     * Check if file is already backed up
     */
    private function is_file_backed_up($file_path) {
        $backup_metadata = get_option('s3_master_backup_metadata', array());
        
        return isset($backup_metadata[$file_path]);
    }
    
    /**
     * Update backup metadata
     */
    private function update_backup_metadata($file_path, $s3_key) {
        $backup_metadata = get_option('s3_master_backup_metadata', array());
        
        $backup_metadata[$file_path] = array(
            's3_key' => $s3_key,
            'backup_date' => current_time('mysql'),
            'file_size' => file_exists($file_path) ? filesize($file_path) : 0
        );
        
        update_option('s3_master_backup_metadata', $backup_metadata);
    }
    
    /**
     * Log backup activity
     */
    private function log_backup_activity($file_path, $status, $message = '') {
        $log_entries = get_option('s3_master_backup_log', array());
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'file_path' => $file_path,
            'status' => $status,
            'message' => $message
        );
        
        array_unshift($log_entries, $log_entry);
        
        // Keep only last 100 entries
        if (count($log_entries) > 100) {
            $log_entries = array_slice($log_entries, 0, 100);
        }
        
        update_option('s3_master_backup_log', $log_entries);
    }
    
    /**
     * Get backup statistics
     */
    public function get_backup_stats() {
        $backup_metadata = get_option('s3_master_backup_metadata', array());
        $backup_log = get_option('s3_master_backup_log', array());
        
        $total_files = count($backup_metadata);
        $total_size = 0;
        
        foreach ($backup_metadata as $file_data) {
            $total_size += $file_data['file_size'];
        }
        
        $recent_backups = array_slice($backup_log, 0, 10);
        $last_backup = get_option('s3_master_last_backup', 0);
        
        return array(
            'total_files' => $total_files,
            'total_size' => $total_size,
            'total_size_formatted' => $this->format_bytes($total_size),
            'last_backup' => $last_backup ? date('Y-m-d H:i:s', $last_backup) : __('Never', 's3-master'),
            'recent_backups' => $recent_backups,
            'auto_backup_enabled' => get_option('s3_master_auto_backup', false),
            'backup_schedule' => get_option('s3_master_backup_schedule', 'hourly')
        );
    }
    
    /**
     * Clear backup metadata
     */
    public function clear_backup_metadata() {
        delete_option('s3_master_backup_metadata');
        delete_option('s3_master_backup_log');
        delete_option('s3_master_last_backup');
        
        return array(
            'success' => true,
            'message' => __('Backup metadata cleared successfully', 's3-master')
        );
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
