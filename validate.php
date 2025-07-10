<?php
/**
 * S3 Master Plugin Syntax Validation
 * 
 * This script checks all PHP files for syntax errors
 */

$plugin_dir = __DIR__;
$files_to_check = [
    's3-master.php',
    'admin/settings-page.php',
    'includes/aws-client.php',
    'includes/bucket-manager.php',
    'includes/file-manager.php',
    'includes/media-backup.php',
    'includes/updater.php'
];

echo "S3 Master Plugin - Syntax Validation\n";
echo str_repeat('=', 50) . "\n\n";

$errors_found = false;

foreach ($files_to_check as $file) {
    $filepath = $plugin_dir . DIRECTORY_SEPARATOR . $file;
    
    if (!file_exists($filepath)) {
        echo "❌ MISSING: {$file}\n";
        $errors_found = true;
        continue;
    }
    
    // Check PHP syntax
    $output = [];
    $return_code = 0;
    exec("php -l \"$filepath\" 2>&1", $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ VALID: {$file}\n";
    } else {
        echo "❌ SYNTAX ERROR: {$file}\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $errors_found = true;
    }
}

echo "\n" . str_repeat('=', 50) . "\n";

if ($errors_found) {
    echo "❌ VALIDATION FAILED - Please fix the errors above\n";
    exit(1);
} else {
    echo "✅ ALL FILES VALID - Plugin is ready for installation!\n";
    
    echo "\n📋 Installation Instructions:\n";
    echo "1. Copy the entire 's3-master' folder to /wp-content/plugins/\n";
    echo "2. Activate the plugin in WordPress admin\n";
    echo "3. Go to Settings > S3 Master to configure\n";
    echo "4. Enter your AWS credentials and test connection\n";
    echo "5. Create or select a bucket\n";
    echo "6. Configure backup settings as needed\n";
    
    exit(0);
}
?>
