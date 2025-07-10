<?php
/**
 * File Manager Class for S3 Master
 * 
 * Handles S3 file operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class S3_Master_File_Manager {
    
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
     * List files in bucket
     */
    public function list_files($bucket_name, $prefix = '', $delimiter = '/') {
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
            $files = array();
            $folders = array();
            
            if (method_exists($s3_client, 'listObjects')) {
                // AWS SDK method
                $params = array(
                    'Bucket' => $bucket_name,
                    'Delimiter' => $delimiter
                );
                
                if (!empty($prefix)) {
                    $params['Prefix'] = trailingslashit($prefix);
                }
                
                $result = $s3_client->listObjects($params);
                
                // Process common prefixes (folders)
                if (isset($result['CommonPrefixes'])) {
                    foreach ($result['CommonPrefixes'] as $commonPrefix) {
                        $folder_name = rtrim($commonPrefix['Prefix'], '/');
                        $folder_name = basename($folder_name);
                        
                        $folders[] = array(
                            'name' => $folder_name,
                            'type' => 'folder',
                            'key' => $commonPrefix['Prefix'],
                            'size' => 0,
                            'last_modified' => '',
                            'is_folder' => true
                        );
                    }
                }
                
                // Process files
                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        // Skip folders and current prefix
                        if (substr($object['Key'], -1) === '/' || $object['Key'] === $prefix) {
                            continue;
                        }
                        
                        $file_name = basename($object['Key']);
                        
                        $files[] = array(
                            'name' => $file_name,
                            'type' => 'file',
                            'key' => $object['Key'],
                            'size' => $object['Size'],
                            'size_formatted' => $this->format_bytes($object['Size']),
                            'last_modified' => $object['LastModified']->format('Y-m-d H:i:s'),
                            'is_folder' => false,
                            'mime_type' => $this->get_mime_type($file_name)
                        );
                    }
                }
            } else {
                // Manual client method
                $result = $s3_client->list_objects($bucket_name, $prefix);
                
                if ($result['success'] && isset($result['data'])) {
                    foreach ($result['data'] as $object) {
                        $file_name = basename($object['Key']);
                        
                        // Check if it's a folder
                        if (substr($object['Key'], -1) === '/') {
                            $folders[] = array(
                                'name' => rtrim($file_name, '/'),
                                'type' => 'folder',
                                'key' => $object['Key'],
                                'size' => 0,
                                'last_modified' => $object['LastModified'],
                                'is_folder' => true
                            );
                        } else {
                            $files[] = array(
                                'name' => $file_name,
                                'type' => 'file',
                                'key' => $object['Key'],
                                'size' => $object['Size'],
                                'size_formatted' => $this->format_bytes($object['Size']),
                                'last_modified' => $object['LastModified'],
                                'is_folder' => false,
                                'mime_type' => $this->get_mime_type($file_name)
                            );
                        }
                    }
                }
            }
            
            // Combine folders and files
            $all_items = array_merge($folders, $files);
            
            return array(
                'success' => true,
                'data' => $all_items,
                'current_prefix' => $prefix
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error listing files: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Upload file to S3
     */
    public function upload_file($file, $bucket_name, $prefix = '') {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        if (!is_array($file) || !isset($file['tmp_name']) || !isset($file['name'])) {
            return array(
                'success' => false,
                'message' => __('Invalid file data', 's3-master')
            );
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => sprintf(__('Upload error: %s', 's3-master'), $this->get_upload_error_message($file['error']))
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
            $file_name = sanitize_file_name($file['name']);
            $key = !empty($prefix) ? trailingslashit($prefix) . $file_name : $file_name;
            
            $file_content = file_get_contents($file['tmp_name']);
            
            if (method_exists($s3_client, 'putObject')) {
                // AWS SDK method
                $result = $s3_client->putObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $key,
                    'Body' => $file_content,
                    'ContentType' => $this->get_mime_type($file_name)
                ));
                
                return array(
                    'success' => true,
                    'message' => __('File uploaded successfully', 's3-master'),
                    'key' => $key
                );
            } else {
                // Manual client method
                $result = $s3_client->put_object($bucket_name, $key, $file_content);
                return $result;
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error uploading file: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Delete file from S3
     */
    public function delete_file($bucket_name, $key) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        if (empty($key)) {
            return array(
                'success' => false,
                'message' => __('File key cannot be empty', 's3-master')
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
            if (method_exists($s3_client, 'deleteObject')) {
                // AWS SDK method
                $result = $s3_client->deleteObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $key
                ));
                
                return array(
                    'success' => true,
                    'message' => __('File deleted successfully', 's3-master')
                );
            } else {
                // Manual client method
                return $s3_client->delete_object($bucket_name, $key);
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error deleting file: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Create folder in S3
     */
    public function create_folder($bucket_name, $folder_name, $prefix = '') {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        if (empty($folder_name)) {
            return array(
                'success' => false,
                'message' => __('Folder name cannot be empty', 's3-master')
            );
        }
        
        // Sanitize folder name
        $folder_name = sanitize_file_name($folder_name);
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return array(
                'success' => false,
                'message' => __('S3 client not available', 's3-master')
            );
        }
        
        try {
            $key = !empty($prefix) ? trailingslashit($prefix) . $folder_name . '/' : $folder_name . '/';
            
            if (method_exists($s3_client, 'putObject')) {
                // AWS SDK method
                $result = $s3_client->putObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $key,
                    'Body' => '',
                    'ContentType' => 'application/x-directory'
                ));
                
                return array(
                    'success' => true,
                    'message' => __('Folder created successfully', 's3-master'),
                    'key' => $key
                );
            } else {
                // Manual client method
                $result = $s3_client->put_object($bucket_name, $key, '');
                return $result;
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error creating folder: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Rename file or folder
     */
    public function rename_file($bucket_name, $old_key, $new_name) {
        if (empty($bucket_name)) {
            return array(
                'success' => false,
                'message' => __('Bucket name cannot be empty', 's3-master')
            );
        }
        
        if (empty($old_key) || empty($new_name)) {
            return array(
                'success' => false,
                'message' => __('Old key and new name cannot be empty', 's3-master')
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
            // Calculate new key
            $path_parts = pathinfo($old_key);
            $new_key = !empty($path_parts['dirname']) && $path_parts['dirname'] !== '.' 
                ? $path_parts['dirname'] . '/' . sanitize_file_name($new_name)
                : sanitize_file_name($new_name);
            
            if (method_exists($s3_client, 'copyObject') && method_exists($s3_client, 'deleteObject')) {
                // AWS SDK method
                // Copy object to new key
                $result = $s3_client->copyObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $new_key,
                    'CopySource' => $bucket_name . '/' . $old_key
                ));
                
                // Delete old object
                $s3_client->deleteObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $old_key
                ));
                
                return array(
                    'success' => true,
                    'message' => __('File renamed successfully', 's3-master'),
                    'new_key' => $new_key
                );
            } else {
                // Manual client method - not implemented in manual client
                return array(
                    'success' => false,
                    'message' => __('Rename operation not supported in manual mode', 's3-master')
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Error renaming file: %s', 's3-master'), $e->getMessage())
            );
        }
    }
    
    /**
     * Get file download URL
     */
    public function get_file_url($bucket_name, $key, $expires = 3600) {
        if (empty($bucket_name) || empty($key)) {
            return false;
        }
        
        $s3_client = $this->aws_client->get_s3_client();
        
        if (!$s3_client) {
            return false;
        }
        
        try {
            if (method_exists($s3_client, 'getObjectUrl')) {
                // AWS SDK method
                return $s3_client->getObjectUrl($bucket_name, $key, "+{$expires} seconds");
            } else {
                // Manual client method - return direct URL (not signed)
                return "https://s3.amazonaws.com/{$bucket_name}/{$key}";
            }
            
        } catch (Exception $e) {
            error_log('S3 Master: Error getting file URL: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get MIME type from file extension
     */
    private function get_mime_type($filename) {
        $mime_types = array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            
            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
            
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            
            // audio/video
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            
            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } else {
            return 'application/octet-stream';
        }
    }
    
    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 's3-master');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 's3-master');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 's3-master');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 's3-master');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 's3-master');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 's3-master');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 's3-master');
            default:
                return __('Unknown upload error', 's3-master');
        }
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
