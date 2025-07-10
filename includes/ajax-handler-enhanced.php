<?php
/**
 * Enhanced AJAX Handler for S3 Master
 * 
 * Handles enhanced AJAX requests with bucket statistics and file operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_Enhanced_Ajax_Handler {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
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
        add_action('wp_ajax_s3_master_ajax_enhanced', array($this, 'handle_ajax_request'));
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 's3_master_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['s3_action']);
        
        switch ($action) {
            case 'list_buckets_with_stats':
                $this->list_buckets_with_stats();
                break;
                
            case 'get_bucket_usage':
                $this->get_bucket_usage();
                break;
                
            case 'get_file_url':
                $this->get_file_url();
                break;
                
            case 'get_file_preview':
                $this->get_file_preview();
                break;
                
            case 'set_default_bucket':
                $this->set_default_bucket();
                break;
                
            case 'calculate_media_files':
                $this->calculate_media_files();
                break;
                
            case 'backup_selected_media':
                $this->backup_selected_media();
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * List buckets with statistics
     */
    private function list_buckets_with_stats() {
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        
        // Get basic bucket list
        $buckets_result = $bucket_manager->list_buckets();
        
        if (!$buckets_result['success']) {
            wp_send_json_error($buckets_result['message']);
        }
        
        $buckets = $buckets_result['data'];
        $enhanced_buckets = array();
        $overview = array(
            'total_buckets' => count($buckets),
            'total_objects' => 0,
            'total_size' => 0,
            'total_size_formatted' => '0 B'
        );
        
        // Get statistics for each bucket
        foreach ($buckets as $bucket) {
            $bucket_with_stats = $bucket;
            $stats_result = $bucket_manager->get_bucket_stats($bucket['Name']);
            
            if ($stats_result['success']) {
                $bucket_with_stats['stats'] = $stats_result['stats'];
                $overview['total_objects'] += $stats_result['stats']['object_count'];
                $overview['total_size'] += $stats_result['stats']['total_size'];
            } else {
                $bucket_with_stats['stats'] = null;
            }
            
            $enhanced_buckets[] = $bucket_with_stats;
        }
        
        // Format overview total size
        $overview['total_size_formatted'] = $this->format_bytes($overview['total_size']);
        
        wp_send_json_success(array(
            'buckets' => $enhanced_buckets,
            'overview' => $overview
        ));
    }
    
    /**
     * Get bucket usage information
     */
    private function get_bucket_usage() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        
        if (empty($bucket_name)) {
            wp_send_json_error('Bucket name is required');
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $result = $bucket_manager->get_bucket_stats($bucket_name);
        
        if ($result['success']) {
            // Add additional usage information
            $usage_data = array(
                'object_count' => $result['stats']['object_count'],
                'total_size' => $result['stats']['total_size'],
                'total_size_formatted' => $result['stats']['total_size_formatted'],
                'bucket_name' => $bucket_name,
                'last_updated' => current_time('mysql'),
                'usage_percentage' => $this->calculate_usage_percentage($result['stats']['total_size'])
            );
            
            wp_send_json_success($usage_data);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get file URL for viewing
     */
    private function get_file_url() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        $key = sanitize_text_field($_POST['key']);
        
        if (empty($bucket_name) || empty($key)) {
            wp_send_json_error('Bucket name and file key are required');
        }
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $url = $file_manager->get_file_url($bucket_name, $key, 3600); // 1 hour expiry
        
        if ($url) {
            wp_send_json_success($url);
        } else {
            wp_send_json_error('Could not generate file URL');
        }
    }
    
    /**
     * Get file preview data
     */
    private function get_file_preview() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        $key = sanitize_text_field($_POST['key']);
        
        if (empty($bucket_name) || empty($key)) {
            wp_send_json_error('Bucket name and file key are required');
        }
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $url = $file_manager->get_file_url($bucket_name, $key, 3600);
        
        if ($url) {
            $file_ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            $preview_data = array(
                'url' => $url,
                'type' => $this->get_file_type($file_ext),
                'extension' => $file_ext,
                'can_preview' => $this->can_preview_file($file_ext)
            );
            
            wp_send_json_success($preview_data);
        } else {
            wp_send_json_error('Could not generate file preview');
        }
    }
    
    /**
     * Set default bucket
     */
    private function set_default_bucket() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        
        if (empty($bucket_name)) {
            wp_send_json_error('Bucket name is required');
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $result = $bucket_manager->set_default_bucket($bucket_name);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Calculate usage percentage (mock calculation)
     */
    private function calculate_usage_percentage($size_bytes) {
        // This is a mock calculation - in reality you'd compare against account limits
        $mock_limit = 5368709120; // 5GB mock limit
        
        if ($mock_limit <= 0) {
            return 0;
        }
        
        $percentage = ($size_bytes / $mock_limit) * 100;
        return min(100, round($percentage, 2));
    }
    
    /**
     * Get file type category
     */
    private function get_file_type($extension) {
        $types = array(
            'image' => array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico'),
            'video' => array('mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'),
            'audio' => array('mp3', 'wav', 'ogg', 'flac', 'aac', 'wma'),
            'document' => array('pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'),
            'spreadsheet' => array('xls', 'xlsx', 'csv', 'ods'),
            'presentation' => array('ppt', 'pptx', 'odp'),
            'archive' => array('zip', 'rar', '7z', 'tar', 'gz', 'bz2'),
            'code' => array('js', 'css', 'html', 'php', 'py', 'java', 'cpp', 'c')
        );
        
        foreach ($types as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check if file can be previewed in browser
     */
    private function can_preview_file($extension) {
        $previewable = array(
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp',
            'mp4', 'webm', 'ogg',
            'mp3', 'wav', 'ogg',
            'pdf', 'txt', 'html', 'css', 'js', 'json', 'xml'
        );
        
        return in_array($extension, $previewable);
    }
    
    /**
     * Calculate media files by type and extension
     */
    private function calculate_media_files() {
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!is_dir($uploads_path)) {
            wp_send_json_error('Uploads directory not found');
        }
        
        $media_data = array(
            'images' => array(),
            'videos' => array(),
            'audio' => array(),
            'documents' => array(),
            'archives' => array(),
            'other' => array()
        );
        
        $file_extensions = array(
            'images' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'),
            'videos' => array('mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp'),
            'audio' => array('mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a'),
            'documents' => array('pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages'),
            'archives' => array('zip', 'rar', '7z', 'tar', 'gz', 'bz2')
        );
        
        // Initialize extension counters
        foreach ($file_extensions as $category => $extensions) {
            foreach ($extensions as $ext) {
                $media_data[$category][$ext] = array('count' => 0, 'size' => 0);
            }
        }
        
        // Scan uploads directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploads_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $file_path = $file->getPathname();
                $file_size = $file->getSize();
                $file_ext = strtolower($file->getExtension());
                
                $category_found = false;
                
                // Check which category this extension belongs to
                foreach ($file_extensions as $category => $extensions) {
                    if (in_array($file_ext, $extensions)) {
                        if (isset($media_data[$category][$file_ext])) {
                            $media_data[$category][$file_ext]['count']++;
                            $media_data[$category][$file_ext]['size'] += $file_size;
                        }
                        $category_found = true;
                        break;
                    }
                }
                
                // If no category found, add to 'other'
                if (!$category_found && !empty($file_ext)) {
                    if (!isset($media_data['other'][$file_ext])) {
                        $media_data['other'][$file_ext] = array('count' => 0, 'size' => 0);
                    }
                    $media_data['other'][$file_ext]['count']++;
                    $media_data['other'][$file_ext]['size'] += $file_size;
                }
            }
        }
        
        wp_send_json_success($media_data);
    }
    
    /**
     * Backup selected media types
     */
    private function backup_selected_media() {
        if (!isset($_POST['media_types']) || !is_array($_POST['media_types'])) {
            wp_send_json_error('No media types selected');
        }
        
        $selected_types = array_map('sanitize_text_field', $_POST['media_types']);
        
        if (empty($selected_types)) {
            wp_send_json_error('No media types selected');
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $file_manager = S3_Master_File_Manager::get_instance();
        $default_bucket = $bucket_manager->get_default_bucket();
        
        if (empty($default_bucket)) {
            wp_send_json_error('No default bucket configured');
        }
        
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!is_dir($uploads_path)) {
            wp_send_json_error('Uploads directory not found');
        }
        
        $successful_uploads = 0;
        $failed_uploads = 0;
        $processed_files = 0;
        
        // Scan uploads directory for selected file types
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploads_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $file_path = $file->getPathname();
                $file_ext = strtolower($file->getExtension());
                
                // Check if this file extension is selected for backup
                if (in_array($file_ext, $selected_types)) {
                    $processed_files++;
                    
                    // Get relative path from uploads directory
                    $relative_path = str_replace($uploads_path, '', $file_path);
                    $relative_path = ltrim($relative_path, '/\\');
                    
                    // Prepare file data for upload
                    $file_data = array(
                        'tmp_name' => $file_path,
                        'name' => basename($file_path),
                        'error' => UPLOAD_ERR_OK,
                        'size' => $file->getSize()
                    );
                    
                    // Upload to S3
                    $s3_prefix = 'wp-content/uploads/' . dirname($relative_path);
                    $result = $file_manager->upload_file($file_data, $default_bucket, $s3_prefix);
                    
                    if ($result && $result['success']) {
                        $successful_uploads++;
                    } else {
                        $failed_uploads++;
                        error_log('S3 Master: Failed to backup file: ' . $file_path . ' - ' . ($result['message'] ?? 'Unknown error'));
                    }
                }
            }
        }
        
        $message = sprintf(
            'Backup completed: %d files uploaded successfully',
            $successful_uploads
        );
        
        if ($failed_uploads > 0) {
            $message .= sprintf(', %d failed', $failed_uploads);
        }
        
        if ($processed_files === 0) {
            wp_send_json_error('No files found matching the selected file types');
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'successful' => $successful_uploads,
            'failed' => $failed_uploads,
            'total' => $processed_files
        ));
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Initialize the enhanced AJAX handler
S3_Master_Enhanced_Ajax_Handler::get_instance();
