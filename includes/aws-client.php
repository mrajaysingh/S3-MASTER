<?php
/**
 * AWS Client Class for S3 Master
 * 
 * Handles AWS S3 connections and authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_AWS_Client {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
    /**
     * S3 Client instance
     */
    private $s3_client = null;
    
    /**
     * AWS credentials
     */
    private $credentials = array();
    
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
        $this->load_credentials();
    }
    
    /**
     * Load AWS credentials from WordPress options
     */
    private function load_credentials() {
        $this->credentials = array(
            'access_key_id' => get_option('s3_master_aws_access_key_id', ''),
            'secret_access_key' => get_option('s3_master_aws_secret_access_key', ''),
            'region' => get_option('s3_master_aws_region', 'us-east-1'),
        );
    }
    
    /**
     * Get S3 client instance
     */
    public function get_s3_client() {
        if (null === $this->s3_client) {
            $this->s3_client = $this->create_s3_client();
        }
        return $this->s3_client;
    }
    
    /**
     * Create S3 client
     */
    private function create_s3_client() {
        // Check if AWS SDK is available
        if (!class_exists('Aws\S3\S3Client')) {
            // Fallback to manual implementation if AWS SDK is not available
            return $this->create_manual_s3_client();
        }
        
        try {
            return new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $this->credentials['region'],
                'credentials' => [
                    'key' => $this->credentials['access_key_id'],
                    'secret' => $this->credentials['secret_access_key'],
                ],
            ]);
        } catch (Exception $e) {
            error_log('S3 Master: Error creating S3 client: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create manual S3 client (fallback)
     */
    private function create_manual_s3_client() {
        return new S3_Master_Manual_Client($this->credentials);
    }
    
    /**
     * Test AWS connection
     */
    public function test_connection() {
        try {
            $this->load_credentials();
            
            if (empty($this->credentials['access_key_id']) || empty($this->credentials['secret_access_key'])) {
                return array(
                    'success' => false,
                    'message' => __('AWS credentials are not configured.', 's3-master')
                );
            }
            
            $s3_client = $this->get_s3_client();
            
            if (!$s3_client) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create S3 client. Please check your credentials and try again.', 's3-master')
                );
            }
            
            // Try to list buckets to verify connection
            if (method_exists($s3_client, 'listBuckets')) {
                // AWS SDK client
                $s3_client->listBuckets();
                return array(
                    'success' => true,
                    'message' => __('Connection successful! Your AWS credentials are working.', 's3-master')
                );
            } else {
                // Manual client
                $result = $s3_client->list_buckets();
                if ($result['success']) {
                    return array(
                        'success' => true,
                        'message' => __('Connection successful! Your AWS credentials are working.', 's3-master')
                    );
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log('S3 Master: Connection test failed - ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Update credentials
     */
    public function update_credentials($access_key_id, $secret_access_key, $region) {
        update_option('s3_master_aws_access_key_id', sanitize_text_field($access_key_id));
        update_option('s3_master_aws_secret_access_key', sanitize_text_field($secret_access_key));
        update_option('s3_master_aws_region', sanitize_text_field($region));
        
        $this->load_credentials();
        $this->s3_client = null; // Reset client to force recreation
    }
    
    /**
     * Get AWS regions
     */
    public function get_regions() {
        return array(
            'us-east-1' => __('US East (N. Virginia)', 's3-master'),
            'us-east-2' => __('US East (Ohio)', 's3-master'),
            'us-west-1' => __('US West (N. California)', 's3-master'),
            'us-west-2' => __('US West (Oregon)', 's3-master'),
            'ca-central-1' => __('Canada (Central)', 's3-master'),
            'eu-central-1' => __('Europe (Frankfurt)', 's3-master'),
            'eu-west-1' => __('Europe (Ireland)', 's3-master'),
            'eu-west-2' => __('Europe (London)', 's3-master'),
            'eu-west-3' => __('Europe (Paris)', 's3-master'),
            'eu-north-1' => __('Europe (Stockholm)', 's3-master'),
            'ap-northeast-1' => __('Asia Pacific (Tokyo)', 's3-master'),
            'ap-northeast-2' => __('Asia Pacific (Seoul)', 's3-master'),
            'ap-northeast-3' => __('Asia Pacific (Osaka)', 's3-master'),
            'ap-southeast-1' => __('Asia Pacific (Singapore)', 's3-master'),
            'ap-southeast-2' => __('Asia Pacific (Sydney)', 's3-master'),
            'ap-south-1' => __('Asia Pacific (Mumbai)', 's3-master'),
            'sa-east-1' => __('South America (São Paulo)', 's3-master'),
        );
    }
}

/**
 * Manual S3 Client (fallback when AWS SDK is not available)
 */
class S3_Master_Manual_Client {
    
    private $credentials;
    
    public function __construct($credentials) {
        $this->credentials = $credentials;
    }
    
    /**
     * List buckets using REST API
     */
    public function list_buckets() {
        $url = 'https://s3.amazonaws.com/';
        $response = $this->make_request('GET', $url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);
        
        if (!$xml) {
            return array(
                'success' => false,
                'message' => __('Failed to parse response', 's3-master')
            );
        }
        
        $buckets = array();
        if (isset($xml->Buckets->Bucket)) {
            foreach ($xml->Buckets->Bucket as $bucket) {
                $buckets[] = array(
                    'Name' => (string)$bucket->Name,
                    'CreationDate' => (string)$bucket->CreationDate
                );
            }
        }
        
        return array(
            'success' => true,
            'data' => $buckets
        );
    }
    
    /**
     * Create bucket
     */
    public function create_bucket($bucket_name, $region = 'us-east-1') {
        $url = "https://s3.amazonaws.com/{$bucket_name}";
        
        $body = '';
        if ($region !== 'us-east-1') {
            $body = '<?xml version="1.0" encoding="UTF-8"?>
<CreateBucketConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
    <LocationConstraint>' . $region . '</LocationConstraint>
</CreateBucketConfiguration>';
        }
        
        $response = $this->make_request('PUT', $url, $body);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200 || $status_code === 201) {
            return array(
                'success' => true,
                'message' => __('Bucket created successfully', 's3-master')
            );
        } else {
            // Parse XML error response
            $xml = simplexml_load_string($body);
            $error_message = '';
            
            if ($xml && isset($xml->Error->Code)) {
                $error_code = (string)$xml->Error->Code;
                $error_message = (string)$xml->Error->Message;
                
                switch ($error_code) {
                    case 'BucketAlreadyExists':
                        return array(
                            'success' => false,
                            'message' => __('This bucket name is already taken. Please choose a different name.', 's3-master')
                        );
                    case 'InvalidBucketName':
                        return array(
                            'success' => false,
                            'message' => __('Invalid bucket name. Bucket names must be between 3-63 characters and can only contain lowercase letters, numbers, dots, and hyphens.', 's3-master')
                        );
                    default:
                        $error_message = sprintf(__('Error: %s - %s', 's3-master'), $error_code, $error_message);
                }
            }
            
            return array(
                'success' => false,
                'message' => $error_message ?: sprintf(__('Failed to create bucket. Status code: %d', 's3-master'), $status_code)
            );
        }
    }
    
    /**
     * Delete bucket
     */
    public function delete_bucket($bucket_name) {
        $url = "https://s3.amazonaws.com/{$bucket_name}";
        $response = $this->make_request('DELETE', $url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 204) {
            return array(
                'success' => true,
                'message' => __('Bucket deleted successfully', 's3-master')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to delete bucket. Status code: %d', 's3-master'), $status_code)
            );
        }
    }
    
    /**
     * List objects in bucket
     */
    public function list_objects($bucket_name, $prefix = '') {
        $url = "https://s3.amazonaws.com/{$bucket_name}";
        
        if (!empty($prefix)) {
            $url .= '?prefix=' . urlencode($prefix);
        }
        
        $response = $this->make_request('GET', $url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);
        
        if (!$xml) {
            return array(
                'success' => false,
                'message' => __('Failed to parse response', 's3-master')
            );
        }
        
        $objects = array();
        if (isset($xml->Contents)) {
            foreach ($xml->Contents as $object) {
                $objects[] = array(
                    'Key' => (string)$object->Key,
                    'LastModified' => (string)$object->LastModified,
                    'Size' => (int)$object->Size,
                    'StorageClass' => (string)$object->StorageClass
                );
            }
        }
        
        return array(
            'success' => true,
            'data' => $objects
        );
    }
    
    /**
     * Upload object
     */
    public function put_object($bucket_name, $key, $body) {
        $url = "https://s3.amazonaws.com/{$bucket_name}/{$key}";
        $response = $this->make_request('PUT', $url, $body);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200 || $status_code === 201) {
            return array(
                'success' => true,
                'message' => __('Object uploaded successfully', 's3-master')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to upload object. Status code: %d', 's3-master'), $status_code)
            );
        }
    }
    
    /**
     * Delete object
     */
    public function delete_object($bucket_name, $key) {
        $url = "https://s3.amazonaws.com/{$bucket_name}/{$key}";
        $response = $this->make_request('DELETE', $url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 204) {
            return array(
                'success' => true,
                'message' => __('Object deleted successfully', 's3-master')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to delete object. Status code: %d', 's3-master'), $status_code)
            );
        }
    }
    
    /**
     * Make authenticated request to S3
     */
    private function make_request($method, $url, $body = '') {
        $timestamp = gmdate('D, d M Y H:i:s T');
        $signature = $this->generate_signature($method, $url, $timestamp, $body);
        
        $headers = array(
            'Date' => $timestamp,
            'Authorization' => 'AWS ' . $this->credentials['access_key_id'] . ':' . $signature,
        );
        
        if (!empty($body)) {
            $headers['Content-Length'] = strlen($body);
            $headers['Content-Type'] = 'application/xml';
        }
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
        );
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * Generate AWS signature
     */
    private function generate_signature($method, $url, $timestamp, $body = '') {
        $parsed_url = parse_url($url);
        $resource = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
        
        if (isset($parsed_url['query'])) {
            $resource .= '?' . $parsed_url['query'];
        }
        
        $string_to_sign = $method . "\n";
        $string_to_sign .= "\n"; // Content-MD5 (empty)
        $string_to_sign .= (!empty($body) ? "application/xml" : "") . "\n";
        $string_to_sign .= $timestamp . "\n";
        $string_to_sign .= $resource;
        
        return base64_encode(hash_hmac('sha1', $string_to_sign, $this->credentials['secret_access_key'], true));
    }
}
