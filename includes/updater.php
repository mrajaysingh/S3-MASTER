<?php
/**
 * Plugin Updater Class for S3 Master
 * 
 * Handles GitHub-based plugin updates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_Updater {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
    /**
     * Plugin file
     */
    private $plugin_file;
    
    /**
     * Plugin slug
     */
    private $plugin_slug;
    
    /**
     * Plugin version
     */
    private $plugin_version;
    
    /**
     * GitHub repository
     */
    private $github_repo = 'mrajaysingh/S3-MASTER';
    
    /**
     * GitHub branch
     */
    private $github_branch = 'main';
    
    /**
     * Update checker instance
     */
    private $update_checker = null;
    
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
        $this->plugin_file = S3_MASTER_PLUGIN_FILE;
        $this->plugin_slug = S3_MASTER_PLUGIN_BASENAME;
        $this->plugin_version = S3_MASTER_VERSION;
        
        $this->init_updater();
        $this->init_hooks();
    }
    
    /**
     * Initialize updater
     */
    private function init_updater() {
        // Check if plugin-update-checker library is available
        $puc_path = S3_MASTER_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
        
        if (file_exists($puc_path)) {
            require_once $puc_path;
            
            if (class_exists('Puc_v4_Factory')) {
                $this->setup_puc_updater();
            }
        } else {
            // Fallback to manual update checker
            $this->setup_manual_updater();
        }
    }
    
    /**
     * Setup Plugin Update Checker library
     */
    private function setup_puc_updater() {
        try {
            $this->update_checker = Puc_v4_Factory::buildUpdateChecker(
                "https://github.com/{$this->github_repo}/",
                $this->plugin_file,
                's3-master'
            );
            
            // Set branch
            $this->update_checker->setBranch($this->github_branch);
            
            // Set GitHub token if available
            $github_token = get_option('s3_master_github_token', '');
            if (!empty($github_token)) {
                $this->update_checker->setAuthentication($github_token);
            }
            
            // Add filter for release assets
            $this->update_checker->addFilter('request_info_result', array($this, 'filter_update_info'));
            
        } catch (Exception $e) {
            error_log('S3 Master: Error setting up update checker: ' . $e->getMessage());
        }
    }
    
    /**
     * Setup manual updater
     */
    private function setup_manual_updater() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_s3_master_check_updates', array($this, 'ajax_check_updates'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Check for updates daily
        if (!wp_next_scheduled('s3_master_check_updates')) {
            wp_schedule_event(time(), 'daily', 's3_master_check_updates');
        }
        
        add_action('s3_master_check_updates', array($this, 'scheduled_update_check'));
    }
    
    /**
     * Filter update info
     */
    public function filter_update_info($info) {
        // Add custom update info if needed
        return $info;
    }
    
    /**
     * Check for updates (manual implementation)
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->plugin_version, $remote_version, '<')) {
            $plugin_data = $this->get_plugin_info();
            
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_repo}",
                'package' => $this->get_download_url($remote_version),
                'icons' => array(),
                'banners' => array(),
                'tested' => $plugin_data['tested'] ?? '',
                'requires_php' => $plugin_data['requires_php'] ?? '',
                'compatibility' => new stdClass(),
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        $remote_version = $this->get_remote_version();
        $plugin_data = $this->get_plugin_info();
        
        return (object) array(
            'slug' => dirname($this->plugin_slug),
            'plugin' => $this->plugin_slug,
            'version' => $remote_version,
            'author' => $plugin_data['author'] ?? '',
            'author_profile' => $plugin_data['author_uri'] ?? '',
            'requires' => $plugin_data['requires'] ?? '',
            'tested' => $plugin_data['tested'] ?? '',
            'requires_php' => $plugin_data['requires_php'] ?? '',
            'name' => $plugin_data['name'] ?? '',
            'sections' => array(
                'description' => $plugin_data['description'] ?? '',
                'changelog' => $this->get_changelog(),
            ),
            'homepage' => "https://github.com/{$this->github_repo}",
            'download_link' => $this->get_download_url($remote_version),
            'tags' => array('s3', 'aws', 'backup', 'storage'),
            'contributors' => array(),
        );
    }
    
    /**
     * Get remote version from GitHub
     */
    private function get_remote_version() {
        $transient_key = 's3_master_remote_version';
        $remote_version = get_transient($transient_key);
        
        if (false === $remote_version) {
            $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
            
            $args = array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'WordPress S3 Master Plugin',
                ),
            );
            
            // Add authentication if token is available
            $github_token = get_option('s3_master_github_token', '');
            if (!empty($github_token)) {
                $args['headers']['Authorization'] = 'token ' . $github_token;
            }
            
            $response = wp_remote_get($url, $args);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['tag_name'])) {
                    $remote_version = ltrim($data['tag_name'], 'v');
                    set_transient($transient_key, $remote_version, 6 * HOUR_IN_SECONDS);
                }
            }
            
            if (false === $remote_version) {
                $remote_version = $this->plugin_version;
            }
        }
        
        return $remote_version;
    }
    
    /**
     * Get download URL
     */
    private function get_download_url($version) {
        return "https://github.com/{$this->github_repo}/archive/refs/tags/v{$version}.zip";
    }
    
    /**
     * Get plugin info
     */
    private function get_plugin_info() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return get_plugin_data($this->plugin_file);
    }
    
    /**
     * Get changelog
     */
    private function get_changelog() {
        $transient_key = 's3_master_changelog';
        $changelog = get_transient($transient_key);
        
        if (false === $changelog) {
            $url = "https://api.github.com/repos/{$this->github_repo}/releases";
            
            $args = array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'WordPress S3 Master Plugin',
                ),
            );
            
            // Add authentication if token is available
            $github_token = get_option('s3_master_github_token', '');
            if (!empty($github_token)) {
                $args['headers']['Authorization'] = 'token ' . $github_token;
            }
            
            $response = wp_remote_get($url, $args);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $releases = json_decode($body, true);
                
                $changelog = '<h4>' . __('Recent Releases', 's3-master') . '</h4>';
                
                if (is_array($releases)) {
                    foreach (array_slice($releases, 0, 5) as $release) {
                        $version = ltrim($release['tag_name'], 'v');
                        $date = date('Y-m-d', strtotime($release['published_at']));
                        $notes = $release['body'] ?? __('No release notes available.', 's3-master');
                        
                        $changelog .= "<h5>Version {$version} ({$date})</h5>";
                        $changelog .= '<div>' . wp_kses_post($notes) . '</div>';
                    }
                } else {
                    $changelog .= '<p>' . __('No changelog available.', 's3-master') . '</p>';
                }
                
                set_transient($transient_key, $changelog, 6 * HOUR_IN_SECONDS);
            } else {
                $changelog = '<p>' . __('Unable to fetch changelog.', 's3-master') . '</p>';
            }
        }
        
        return $changelog;
    }
    
    /**
     * AJAX check for updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('s3_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Clear transients
        delete_transient('s3_master_remote_version');
        delete_transient('s3_master_changelog');
        
        $remote_version = $this->get_remote_version();
        $current_version = $this->plugin_version;
        
        $update_available = version_compare($current_version, $remote_version, '<');
        
        $response = array(
            'current_version' => $current_version,
            'remote_version' => $remote_version,
            'update_available' => $update_available,
            'last_checked' => current_time('mysql'),
        );
        
        if ($update_available) {
            $response['message'] = sprintf(
                __('New version %s is available! You are currently running version %s.', 's3-master'),
                $remote_version,
                $current_version
            );
        } else {
            $response['message'] = __('You are running the latest version.', 's3-master');
        }
        
        // Update last checked time
        update_option('s3_master_last_update_check', time());
        
        wp_send_json_success($response);
    }
    
    /**
     * Scheduled update check
     */
    public function scheduled_update_check() {
        // Clear transients to force fresh check
        delete_transient('s3_master_remote_version');
        delete_transient('s3_master_changelog');
        
        // Trigger WordPress update check
        wp_update_plugins();
        
        // Update last checked time
        update_option('s3_master_last_update_check', time());
    }
    
    /**
     * Add plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_slug) {
            $row_meta = array(
                'github' => '<a href="https://github.com/' . $this->github_repo . '" target="_blank">' . __('GitHub Repository', 's3-master') . '</a>',
                'issues' => '<a href="https://github.com/' . $this->github_repo . '/issues" target="_blank">' . __('Support', 's3-master') . '</a>',
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Get update status
     */
    public function get_update_status() {
        $current_version = $this->plugin_version;
        $remote_version = $this->get_remote_version();
        $last_checked = get_option('s3_master_last_update_check', 0);
        
        return array(
            'current_version' => $current_version,
            'remote_version' => $remote_version,
            'update_available' => version_compare($current_version, $remote_version, '<'),
            'last_checked' => $last_checked ? date('Y-m-d H:i:s', $last_checked) : __('Never', 's3-master'),
            'github_repo' => $this->github_repo,
            'github_branch' => $this->github_branch,
        );
    }
    
    /**
     * Set GitHub token
     */
    public function set_github_token($token) {
        update_option('s3_master_github_token', sanitize_text_field($token));
        
        // Update PUC authentication if available
        if ($this->update_checker && method_exists($this->update_checker, 'setAuthentication')) {
            $this->update_checker->setAuthentication($token);
        }
        
        return array(
            'success' => true,
            'message' => __('GitHub token updated successfully', 's3-master')
        );
    }
}
