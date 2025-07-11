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

            case 'verify_bucket':
                $this->verify_bucket();
                break;
                
            case 'verify_and_set_default_bucket':
                $this->verify_and_set_default_bucket();
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
     * Verify bucket access and existence
     */
    private function verify_bucket() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        
        if (empty($bucket_name)) {
            wp_send_json_error('Bucket name is required');
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        
        // First check if the bucket exists
        if (!$bucket_manager->bucket_exists($bucket_name)) {
            wp_send_json_error('Bucket does not exist or is not accessible');
            return;
        }
        
        // Try to get bucket location to verify permissions
        $location_result = $bucket_manager->get_bucket_location($bucket_name);
        
        if (!$location_result['success']) {
            wp_send_json_error($location_result['message']);
            return;
        }
        
        // Get bucket stats to verify read permissions
        $stats_result = $bucket_manager->get_bucket_stats($bucket_name);
        
        if (!$stats_result['success']) {
            wp_send_json_error('Cannot access bucket statistics. Please check your permissions.');
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Bucket verified successfully',
            'location' => $location_result['location'],
            'stats' => $stats_result['stats']
        ));
    }

    /**
     * Verify and Set Default Bucket
     */
    private function verify_and_set_default_bucket() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        if (empty($bucket_name)) {
            wp_send_json_error('Bucket name is required');
        }

        $bucket_manager = S3_Master_Bucket_Manager::get_instance();

        // Verify bucket
        if (!$bucket_manager->bucket_exists($bucket_name)) {
            wp_send_json_error('Bucket does not exist or is not accessible');
        }

        // Set as default
        $result = $bucket_manager->set_default_bucket($bucket_name);
        if ($result['success']) {
            wp_send_json_success('Bucket verified and set as default successfully!');
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
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
