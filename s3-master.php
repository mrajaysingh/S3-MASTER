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
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_s3_master_ajax', array($this, 'ajax_handler'));
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
        wp_localize_script('s3-master-admin', 's3_master_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('s3_master_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 's3-master'),
                'uploading' => __('Uploading...', 's3-master'),
                'success' => __('Success!', 's3-master'),
                'error' => __('Error occurred!', 's3-master'),
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
            case 'test_connection':
                $this->ajax_test_connection();
                break;
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
            case 'backup_media':
                $this->ajax_backup_media();
                break;
            case 'analyze_storage':
                $this->ajax_analyze_storage();
                break;
            case 'calculate_media':
                $this->ajax_calculate_media();
                break;
            case 'backup_selected_media':
                $this->ajax_backup_selected_media();
                break;
            case 'get_file_url':
                $this->ajax_get_file_url();
                break;
            default:
                wp_send_json_error(__('Invalid action', 's3-master'));
        }
    }
    
    /**
     * Test AWS connection
     */
    private function ajax_test_connection() {
        $aws_client = S3_Master_AWS_Client::get_instance();
        $result = $aws_client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(__('Connection successful!', 's3-master'));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * List S3 buckets
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
     * Analyze storage usage
     */
    private function ajax_analyze_storage() {
        $uploads_path = wp_upload_dir()['basedir'];
        $media_files = $this->get_media_files($uploads_path);

        $overview = array(
            'total_files' => count($media_files),
            'total_size' => 0,
            'file_types' => count(array_unique(array_map('pathinfo', $media_files, array_fill(0, count($media_files), PATHINFO_EXTENSION)))),
        );

        $extensions_data = array();
        foreach ($media_files as $file_path) {
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? $file_info['extension'] : 'unknown';
            $file_size = filesize($file_path);

            $overview['total_size'] += $file_size;

            if (!isset($extensions_data[$extension])) {
                $extensions_data[$extension] = ['count' => 0, 'size' => 0];
            }

            $extensions_data[$extension]['count']++;
            $extensions_data[$extension]['size'] += $file_size;
        }

        $extensions_result = array();
        foreach ($extensions_data as $ext => $data) {
            $extensions_result[] = array(
                'extension' => $ext,
                'size' => $data['size'],
                'size_formatted' => size_format($data['size']),
                'count' => $data['count'],
            );
        }

        wp_send_json_success(array(
            'overview' => array_merge($overview, array('total_size_formatted' => size_format($overview['total_size']), 'avg_file_size_formatted' => size_format($overview['total_size'] / $overview['total_files']))),
            'extensions' => $extensions_result,
        ));
    }

    /**
     * Calculate media files by type and extension
     */
    private function ajax_calculate_media() {
        $uploads_path = wp_upload_dir()['basedir'];
        $media_files = $this->get_media_files($uploads_path);

        if (empty($media_files)) {
            wp_send_json_error(__('No media files found for backup.', 's3-master'));
        }

        $media_data = [];
        foreach ($media_files as $file_path) {
            $file_size = filesize($file_path);
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : 'unknown';

            if (!isset($media_data[$extension])) {
                $media_data[$extension] = ['count' => 0, 'size' => 0];
            }
            $media_data[$extension]['count']++;
            $media_data[$extension]['size'] += $file_size;
        }

        $categories = array();
        $total_size = 0;
        
        foreach ($media_data as $extension => $data) {
            $categories[strtoupper($extension)] = array(
                'count' => $data['count'],
                'size' => $data['size'],
                'size_formatted' => size_format($data['size']),
            );
            $total_size += $data['size'];
        }

        wp_send_json_success(array('categories' => $categories, 'total_size' => $total_size));
    }

    /**
     * Backup selected media categories
     */
    private function ajax_backup_selected_media() {
        if (!isset($_POST['categories']) || !is_array($_POST['categories'])) {
            wp_send_json_error(__('No categories selected.', 's3-master'));
        }

        $selected_categories = array_map('sanitize_text_field', $_POST['categories']);
        $media_backup = S3_Master_Media_Backup::get_instance();
        $bucket_manager = S3_Master_Bucket_Manager::get_instance();
        $file_manager = S3_Master_File_Manager::get_instance();
        $default_bucket = $bucket_manager->get_default_bucket();

        if (empty($default_bucket)) {
            wp_send_json_error(__('No default bucket configured.', 's3-master'));
        }

        $uploads_path = wp_upload_dir()['basedir'];
        $media_files = $this->get_media_files($uploads_path);
        $successful_uploads = 0;
        $failed_uploads = 0;

        foreach ($media_files as $file_path) {
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtoupper($file_info['extension']) : 'UNKNOWN';

            if (in_array($extension, $selected_categories)) {
                $relative_path = str_replace($uploads_path, '', $file_path);
                $relative_path = ltrim($relative_path, '/\\');

                $file_data = array(
                    'tmp_name' => $file_path,
                    'name' => basename($file_path),
                    'error' => UPLOAD_ERR_OK
                );

                $result = $file_manager->upload_file($file_data, $default_bucket, 'wp-content/uploads/' . dirname($relative_path));

                if ($result && $result['success']) {
                    $successful_uploads++;
                } else {
                    $failed_uploads++;
                }
            }
        }

        $message = sprintf(__('Backup completed: %d files uploaded successfully', 's3-master'), $successful_uploads);
        if ($failed_uploads > 0) {
            $message .= sprintf(__(', %d failed', 's3-master'), $failed_uploads);
        }

        wp_send_json_success($message);
    }

    /**
     * Get media files from uploads directory
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
        
        $media_extensions = array(
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            'mp3', 'wav', 'ogg', 'wma', 'flac',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'rar', '7z', 'tar', 'gz'
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $file_extension = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                if (in_array($file_extension, $media_extensions)) {
                    $files[] = $file->getPathname();
                }
            }
        }
        
        return $files;
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
     * Get presigned URL for file viewing
     */
    private function ajax_get_file_url() {
        $bucket_name = sanitize_text_field(isset($_POST['bucket_name']) ? $_POST['bucket_name'] : '');
        $key = sanitize_text_field(isset($_POST['key']) ? $_POST['key'] : '');
        
        if (empty($bucket_name) || empty($key)) {
            wp_send_json_error(__('Bucket name and file key are required', 's3-master'));
        }
        
        $file_manager = S3_Master_File_Manager::get_instance();
        $result = $file_manager->get_file_url($bucket_name, $key);
        
        if ($result['success']) {
            wp_send_json_success(array('url' => $result['url']));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Backup media to S3
     */
    private function ajax_backup_media() {
        $media_backup = S3_Master_Media_Backup::get_instance();
        $result = $media_backup->backup_existing_media();
        
        if ($result['success']) {
            wp_send_json_success(sprintf(__('Backed up %d files successfully!', 's3-master'), $result['count']));
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
}

// Initialize plugin
S3_Master::get_instance();
