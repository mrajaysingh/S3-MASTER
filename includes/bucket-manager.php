<?php
/**
 * Bucket Manager Class for S3 Master
 * 
 * Handles S3 bucket operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_Bucket_Manager {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
    /**
     * AWS Client instance
     */
    private $aws_client = null;
    
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
    }
    
    /**
     * List all buckets
     */
    public function list_buckets() {
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            if (method_exists($s3_client, 'listBuckets')) {
                // AWS SDK method
                $result = $s3_client->listBuckets();
                $buckets = array();
                
                if (isset($result['Buckets'])) {
                    foreach ($result['Buckets'] as $bucket) {
                        $buckets[] = array(
                            'Name' => $bucket['Name'],
                            'CreationDate' => $bucket['CreationDate']->format('Y-m-d H:i:s')
                        );
                    }
                }
                
                return array(
                    'success' => true,
                    'data' => $buckets
                );
            } else {
                // Manual client method
                return $s3_client->list_buckets();
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error listing buckets: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Create a new bucket
     */
    public function create_bucket($bucket_name, $region = 'us-east-1') {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        // Validate bucket name
        if (!$this->is_valid_bucket_name($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Invalid bucket name. Bucket names must be between 3-63 characters, contain only lowercase letters, numbers, and hyphens.', 's3-master')
            );
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            if (method_exists($s3_client, 'createBucket')) {
                // AWS SDK method
                $params = array(
                    'Bucket' => $bucket_name
                );
                
                if ($region !== 'us-east-1') {
                    $params['CreateBucketConfiguration'] = array(
                        'LocationConstraint' => $region
                    );
                }
                
                $result = $s3_client->createBucket($params);
                
                return array(
                    'success' => true,
                    'message' => __('Bucket created successfully', 's3-master')
                );
            } else {
                // Manual client method
                return $s3_client->create_bucket($bucket_name, $region);
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error creating bucket: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Delete a bucket
     */
    public function delete_bucket($bucket_name) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            if (method_exists($s3_client, 'deleteBucket')) {
                // AWS SDK method
                $result = $s3_client->deleteBucket(array(
                    'Bucket' => $bucket_name
                ));
                
                return array(
                    'success' => true,
                    'message' => __('Bucket deleted successfully', 's3-master')
                );
            } else {
                // Manual client method
                return $s3_client->delete_bucket($bucket_name);
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error deleting bucket: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Check if bucket exists
     */
    public function bucket_exists($bucket_name) {
        if (empty($bucket_name)) {
            return false;
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return false;
        }
        
        try {
            if (method_exists($s3_client, 'doesBucketExist')) {
                // AWS SDK method
                return $s3_client->doesBucketExist($bucket_name);
            } else {
                // Manual client method - check by listing buckets
                $result = $s3_client->list_buckets();
                if ($result['success'] && isset($result['data'])) {
                    foreach ($result['data'] as $bucket) {
                        if ($bucket['Name'] === $bucket_name) {
                            return true;
                        }
                    }
                }
                return false;
            }
        } catch (Exception $e) {
            error_log('S3 Master: Error checking bucket existence: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get bucket location
     */
    public function get_bucket_location($bucket_name) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            if (method_exists($s3_client, 'getBucketLocation')) {
                // AWS SDK method
                $result = $s3_client->getBucketLocation(array(
                    'Bucket' => $bucket_name
                ));
                
                $location = $result['LocationConstraint'] ?? 'us-east-1';
                
                return array(
                    'success' => true,
                    'location' => $location
                );
            } else {
                // Manual client method - return default region
                return array(
                    'success' => true,
                    'location' => get_option('s3_master_aws_region', 'us-east-1')
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error getting bucket location: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Set default bucket
     */
    public function set_default_bucket($bucket_name) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        // Verify bucket exists
        if (!$this->bucket_exists($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket does not exist', 's3-master')
            );
        }
        
        update_option('s3_master_default_bucket', sanitize_text_field($bucket_name));
        
        return array(
            'success' => true,
            'message' => sprintf(__('Default bucket set to: %s', 's3-master'), $bucket_name)
        );
    }
    
    /**
     * Get default bucket
     */
    public function get_default_bucket() {
        return get_option('s3_master_default_bucket', '');
    }
    
    /**
     * Get bucket statistics
     */
    public function get_bucket_stats($bucket_name) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            $object_count = 0;
            $total_size = 0;
            
            if (method_exists($s3_client, 'listObjects')) {
                // AWS SDK method
                $result = $s3_client->listObjects(array(
                    'Bucket' => $bucket_name
                ));
                
                if (isset($result['Contents'])) {
                    $object_count = count($result['Contents']);
                    foreach ($result['Contents'] as $object) {
                        $total_size += $object['Size'];
                    }
                }
            } else {
                // Manual client method
                $result = $s3_client->list_objects($bucket_name);
                if ($result['success'] && isset($result['data'])) {
                    $object_count = count($result['data']);
                    foreach ($result['data'] as $object) {
                        $total_size += $object['Size'];
                    }
                }
            }
            
            return array(
                'success' => true,
                'stats' => array(
                    'object_count' => $object_count,
                    'total_size' => $total_size,
                    'total_size_formatted' => $this->format_bytes($total_size)
                )
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error getting bucket stats: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Validate bucket name
     */
    private function is_valid_bucket_name($bucket_name) {
        // Check length
        if (strlen($bucket_name) < 3 || strlen($bucket_name) > 63) {
            return false;
        }
        
        // Check characters
        if (!preg_match('/^[a-z0-9.-]+$/', $bucket_name)) {
            return false;
        }
        
        // Check for consecutive periods
        if (strpos($bucket_name, '..') !== false) {
            return false;
        }
        
        // Check start and end characters
        if (!preg_match('/^[a-z0-9]/', $bucket_name) || !preg_match('/[a-z0-9]$/', $bucket_name)) {
            return false;
        }
        
        // Check for IP address format
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $bucket_name)) {
            return false;
        }
        
        return true;
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
