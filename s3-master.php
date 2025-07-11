<?php
/**
 * Plugin Name: S3 Master
 * Plugin URI: https://github.com/mrajaysingh/S3-MASTER
 * Description: Complete AWS S3 integration for WordPress with file management, auto backup, and GitHub updates.
 * Version: 1.0.0
 * Author: Ajay Singh
 * Author URI: https://github.com/mrajaysingh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: s3-master
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: https://github.com/mrajaysingh/S3-MASTER
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('S3_MASTER_VERSION', '1.0.0');
define('S3_MASTER_PLUGIN_FILE', __FILE__);
define('S3_MASTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S3_MASTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('S3_MASTER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloader (if using AWS SDK via Composer)
if (file_exists(S3_MASTER_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once S3_MASTER_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main S3 Master Plugin Class
 */
class S3_Master {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
        $this->init_hooks();
        $this->load_dependencies();
        
        // Add AJAX handlers
        add_action('wp_ajax_verify_and_set_default_bucket', array($this, 'handle_verify_and_set_default_bucket'));
        add_action('wp_ajax_get_buckets_list', array($this, 'handle_get_buckets_list'));
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_s3_master_ajax', array($this, 'ajax_handler'));
        add_action('wp_ajax_s3_master_ajax_enhanced', array($this, 'ajax_handler_enhanced'));
        add_action('init', array($this, 'init_cron_schedules'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once S3_MASTER_PLUGIN_DIR . 'includes/aws-client.php';
        require_once S3_MASTER_PLUGIN_DIR . 'includes/bucket-manager.php';
        require_once S3_MASTER_PLUGIN_DIR . 'includes/file-manager.php';
        require_once S3_MASTER_PLUGIN_DIR . 'includes/media-backup.php';
        require_once S3_MASTER_PLUGIN_DIR . 'includes/updater.php';
        
        // Initialize components
        S3_Master_AWS_Client::get_instance();
        S3_Master_Bucket_Manager::get_instance();
        S3_Master_File_Manager::get_instance();
        S3_Master_Media_Backup::get_instance();
        S3_Master_Updater::get_instance();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('s3-master', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_options_page(
            __('S3 Master', 's3-master'),
            __('S3 Master', 's3-master'),
            'manage_options',
            's3-master',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        require_once S3_MASTER_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if ('settings_page_s3-master' !== $hook) {
            return;
        }
        
        wp_enqueue_script('s3-master-admin', S3_MASTER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), S3_MASTER_VERSION, true);
        wp_enqueue_style('s3-master-admin', S3_MASTER_PLUGIN_URL . 'assets/css/admin.css', array(), S3_MASTER_VERSION);
        
        // Localize script
        $aws_access_key_id = get_option('s3_master_aws_access_key_id');
        $aws_secret_access_key = get_option('s3_master_aws_secret_access_key');
        $connection_verified = get_option('s3_master_connection_verified', false);
        $has_credentials = !empty($aws_access_key_id) && !empty($aws_secret_access_key) && $connection_verified;

        wp_localize_script('s3-master-admin', 's3_master_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('s3_master_nonce'),
            'has_credentials' => $has_credentials,
            'connection_verified' => $connection_verified,
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 's3-master'),
                'uploading' => __('Uploading...', 's3-master'),
                'success' => __('Success!', 's3-master'),
                'error' => __('Error occurred!', 's3-master'),
                'loading' => __('Loading...', 's3-master'),
                'test_connection' => __('Test Connection', 's3-master'),
                'no_bucket' => __('Select a bucket', 's3-master'),
                'no_buckets' => __('No buckets available', 's3-master'),
                'select_bucket' => __('Select a bucket', 's3-master'),
                'buckets_found' => __('%d buckets found', 's3-master'),
                'no_buckets_create' => __('No buckets found. Please create a bucket first.', 's3-master'),
                'enter_credentials' => __('Please enter and verify your AWS credentials first.', 's3-master'),
                'failed_load_buckets' => __('Failed to load buckets. Please try again.', 's3-master')
            )
        ));
    }
    
    /**
     * AJAX handler
     */
    public function ajax_handler() {
        check_ajax_referer('s3_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $action = sanitize_text_field(isset($_POST['s3_action']) ? $_POST['s3_action'] : '');
        
        switch ($action) {
            case 'list_buckets':
                $this->ajax_list_buckets();
                break;
            case 'create_bucket':
                $this->ajax_create_bucket();
                break;
            case 'delete_bucket':
                $this->ajax_delete_bucket();
                break;
            case 'list_files':
                $this->ajax_list_files();
                break;
            case 'upload_file':
                $this->ajax_upload_file();
                break;
            case 'delete_file':
                $this->ajax_delete_file();
                break;
            case 'create_folder':
                $this->ajax_create_folder();
                break;
            case 'verify_bucket':
                $this->ajax_verify_bucket();
                break;
            case 'set_default_bucket':
                $this->ajax_set_default_bucket();
                break;
            case 'backup_media':
                $this->ajax_backup_media();
                break;
            case 'test_connection':
                $this->ajax_test_connection();
                break;
            case 'verify_and_set_default_bucket':
                $this->ajax_verify_and_set_default_bucket();
                break;
            case 'backup_media':
                $this->ajax_backup_media();
                break;
            case 'get_backup_progress':
                $this->ajax_get_backup_progress();
                break;
            default:
                wp_send_json_error(__('Invalid action', 's3-master'));
        }
    }
    
    /**
     * Enhanced AJAX handler
     */
    public function ajax_handler_enhanced() {
        check_ajax_referer('s3_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        require_once S3_MASTER_PLUGIN_DIR . 'includes/ajax-handler-enhanced.php';
        $handler = S3_Master_Enhanced_Ajax_Handler::get_instance();
        $handler->handle_ajax_request();
    }
    
    /**
     * List buckets
     */
    private function ajax_list_buckets() {
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $buckets = $bucket_manager->list_buckets();
        
        if ($buckets['success']) {
            wp_send_json_success($buckets['data']);
        } else {
            wp_send_json_error($buckets['message']);
        }
    }
    
    /**
     * Create S3 bucket
     */
    private function ajax_create_bucket() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $region = sanitize_text_field(isset($_POST['region']) ? $_POST['region'] : '');
        
        if (empty($bucket_name)) {
            wp_send_json_error(__('Bucket name is required', 's3-master'));
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $result = $bucket_manager->create_bucket($bucket_name, $region);
        
        if ($result['success']) {
            wp_send_json_success(__('Bucket created successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Delete S3 bucket
     */
    private function ajax_delete_bucket() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        
        if (empty($bucket_name)) {
            wp_send_json_error(__('Bucket name is required', 's3-master'));
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $result = $bucket_manager->delete_bucket($bucket_name);
        
        if ($result['success']) {
            wp_send_json_success(__('Bucket deleted successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * List files in bucket
     */
    private function ajax_list_files() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $prefix = sanitize_text_field(isset($_POST['prefix']) ? $_POST['prefix'] : '');
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $files = $file_manager->list_files($bucket_name, $prefix);
        
        if ($files['success']) {
            wp_send_json_success($files['data']);
        } else {
            wp_send_json_error($files['message']);
        }
    }
    
    /**
     * Upload file to S3
     */
    private function ajax_upload_file() {
        if (!isset($_FILES['file'])) {
            wp_send_json_error(__('No file selected', 's3-master'));
        }
        
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $prefix = sanitize_text_field(isset($_POST['prefix']) ? $_POST['prefix'] : '');
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $result = $file_manager->upload_file($_FILES['file'], $bucket_name, $prefix);
        
        if ($result['success']) {
            wp_send_json_success(__('File uploaded successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Delete file from S3
     */
    private function ajax_delete_file() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $key = sanitize_text_field(isset($_POST['key']) ? $_POST['key'] : '');
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $result = $file_manager->delete_file($bucket_name, $key);
        
        if ($result['success']) {
            wp_send_json_success(__('File deleted successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Create folder in S3
     */
    private function ajax_create_folder() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $folder_name = sanitize_text_field(isset($_POST['folder_name']) ? $_POST['folder_name'] : '');
        $prefix = sanitize_text_field(isset($_POST['prefix']) ? $_POST['prefix'] : '');
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $result = $file_manager->create_folder($bucket_name, $folder_name, $prefix);
        
        if ($result['success']) {
            wp_send_json_success(__('Folder created successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    

    /**
     * AJAX handler for verifying and setting default bucket
     */
    public function handle_verify_and_set_default_bucket() {
        check_ajax_referer('s3_master_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        
        if (empty($bucket_name)) {
            wp_send_json_error('Bucket name is required');
            return;
        }

        try {
            // Use bucket manager to verify and set default bucket
            $bucket_manager = S3_Master_Bucket_Manager::get_instance();
            
            // Check if bucket exists and is accessible
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
            
            // Set as default bucket
            $set_default_result = $bucket_manager->set_default_bucket($bucket_name);
            
            if ($set_default_result['success']) {
                wp_send_json_success('Bucket verified and set as default successfully!');
            } else {
                wp_send_json_error($set_default_result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting the list of buckets
     */
    public function handle_get_buckets_list() {
        check_ajax_referer('s3_master_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            // Get AWS client
            $s3_client = S3_Master_AWS_Client::get_instance()->get_s3_client();
            
            if (!$s3_client) {
                wp_send_json_error('AWS client not initialized. Please check your credentials.');
                return;
            }

            // Get list of buckets
            $result = $s3_client->listBuckets();
            $buckets = array_map(function($bucket) {
                return $bucket['Name'];
            }, $result['Buckets']);
            
            wp_send_json_success($buckets);
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle bucket verification
     */
    private function ajax_verify_bucket() {
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
     * Handle setting default bucket
     */
    private function ajax_set_default_bucket() {
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
     * Initialize custom cron schedules
     */
    public function init_cron_schedules() {
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['s3_master_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 's3-master')
        );
        
        $schedules['s3_master_weekly'] = array(
            'interval' => 7 * DAY_IN_SECONDS,
            'display' => __('Weekly', 's3-master')
        );
        
        $schedules['s3_master_monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Monthly', 's3-master')
        );
        
        return $schedules;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'aws_access_key_id' => '',
            'aws_secret_access_key' => '',
            'aws_region' => 'us-east-1',
            'default_bucket' => '',
            'auto_backup' => false,
            'backup_schedule' => 'hourly',
            'custom_backup_hours' => 1,
            'github_token' => '',
        );
        
        foreach ($default_options as $key => $value) {
            if (false === get_option('s3_master_' . $key)) {
                add_option('s3_master_' . $key, $value);
            }
        }
        
        // Schedule initial backup check
        if (!wp_next_scheduled('s3_master_backup_check')) {
            wp_schedule_event(time(), 'hourly', 's3_master_backup_check');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('s3_master_backup_check');
        wp_clear_scheduled_hook('s3_master_media_backup');
    }
    
    /**
     * Backup media to S3 with real-time progress
     */
    private function ajax_backup_media() {
        $media_backup = S3_Master_Media_Backup::get_instance();
        $stats = $media_backup->get_backup_stats();
        
        // Start backup process
        $result = $media_backup->backup_existing_media();
        
        if ($result['success']) {
            // Get updated stats after backup
            $updated_stats = $media_backup->get_backup_stats();
            
            $progress_message = sprintf(
                __('Backup completed successfully!\n\nTotal Files: %d\nBacked UP Files: %d\nRemaining Files: %d\n\nFiles processed in this session: %d', 's3-master'),
                $updated_stats['total_media_files'],
                $updated_stats['uploaded_files'],
                $updated_stats['remaining_files'],
                $result['count']
            );
            
            wp_send_json_success($progress_message);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get backup progress
     */
    private function ajax_get_backup_progress() {
        $media_backup = S3_Master_Media_Backup::get_instance();
        $stats = $media_backup->get_backup_stats();
        
        $progress_message = sprintf(
            __('Backup in progress... Please wait.\n\nTotal Files: %d\nBacked UP Files: %d\nRemaining Files: %d\nProgress: %s%%', 's3-master'),
            $stats['total_media_files'],
            $stats['uploaded_files'],
            $stats['remaining_files'],
            $stats['backup_progress']
        );
        
        wp_send_json_success(array(
            'message' => $progress_message,
            'stats' => $stats
        ));
    }
    
    /**
     * Test AWS connection
     */
    private function ajax_test_connection() {
        $aws_client = S3_Master_AWS_Client::get_instance();
        $result = $aws_client->test_connection();
        
        if ($result['success']) {
            update_option('s3_master_connection_verified', true);
            wp_send_json_success(__('Connection successful!', 's3-master'));
        } else {
            update_option('s3_master_connection_verified', false);
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Verify and set default bucket
     */
    private function ajax_verify_and_set_default_bucket() {
        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        
        if (empty($bucket_name)) {
            wp_send_json_error(__('Bucket name is required', 's3-master'));
        }
        
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        
        // Verify bucket exists
        if (!$bucket_manager->bucket_exists($bucket_name)) {
            wp_send_json_error(__('Bucket does not exist or is not accessible', 's3-master'));
        }
        
        // Set as default
        $result = $bucket_manager->set_default_bucket($bucket_name);
        
        if ($result['success']) {
            wp_send_json_success(__('Bucket verified and set as default successfully!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
}

// Initialize plugin
S3_Master::get_instance();
