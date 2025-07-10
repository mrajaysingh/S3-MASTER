=== S3 Master ===
Contributors: ajaysingh
Tags: s3, aws, backup, storage, media, file-manager, cloud
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete AWS S3 integration for WordPress with file management, automatic media backup, and GitHub-based plugin updates.

== Description ==

S3 Master is a comprehensive WordPress plugin that provides seamless integration with Amazon Web Services (AWS) S3 for file storage, media backup, and file management. The plugin offers a complete solution for managing your WordPress media files in the cloud with automatic backup capabilities and an intuitive file manager interface.

= Key Features =

**🔐 AWS S3 Integration**
* Secure credential storage using WordPress Options API
* Support for all AWS regions with easy region selection
* Automatic connection testing and validation
* Compatible with AWS SDK for PHP v3+ or fallback manual implementation

**📦 Bucket Management**
* Create new S3 buckets directly from WordPress admin
* List and manage all your existing buckets
* Delete buckets with confirmation prompts
* Set default bucket for uploads and backups
* Bucket validation and naming compliance

**📁 Advanced File Manager**
* Intuitive web-based file manager interface
* Create, rename, and delete folders (prefixes)
* Upload files with drag-and-drop support
* Multiple file upload with progress indicators
* File type detection and proper MIME type handling
* Download files directly from S3
* Search and filter functionality
* Keyboard shortcuts for power users

**🗂️ Automatic Media Library Backup**
* Scan and backup existing `/wp-content/uploads/` files
* Automatic backup of new media uploads
* Flexible backup scheduling:
  - Immediate (on upload)
  - Hourly, 6-hourly, daily intervals
  - Weekly and monthly options
  - Custom interval settings
* WordPress Cron integration for reliable scheduling
* Backup progress tracking and statistics
* Comprehensive backup logs

**♻️ Smart Media Upload Integration**
* Hooks into WordPress media upload process
* Automatic S3 upload for new media files
* Preserves original WordPress functionality
* Maintains file structure and organization
* Support for all media file types

**🔄 GitHub-Based Plugin Updates**
* Automatic updates from GitHub repository
* Support for both public and private repositories
* GitHub personal access token support
* Version checking and update notifications
* Seamless integration with WordPress update system
* Changelog display from GitHub releases

= Technical Features =

* **Security**: All AWS credentials stored securely with WordPress Options API
* **Performance**: Efficient AJAX/REST API implementation for non-blocking operations
* **Compatibility**: Works independently without requiring other plugins
* **Standards**: Uses WordPress native design elements and follows WP coding standards
* **Reliability**: Comprehensive error handling and user feedback
* **Extensibility**: Clean, modular architecture for easy customization

= Use Cases =

* **Website Backup**: Automatically backup all media files to S3
* **Cloud Storage**: Use S3 as primary storage for WordPress media
* **File Management**: Manage S3 files directly from WordPress admin
* **Disaster Recovery**: Ensure media files are safely stored in the cloud
* **Performance**: Offload media storage to reduce server load
* **Scalability**: Handle large media libraries with cloud storage

== Installation ==

1. **Upload the plugin files** to the `/wp-content/plugins/s3-master/` directory, or install the plugin through the WordPress plugins screen directly.

2. **Activate the plugin** through the 'Plugins' screen in WordPress.

3. **Configure AWS credentials** by navigating to Settings > S3 Master and entering your:
   - AWS Access Key ID
   - AWS Secret Access Key
   - Preferred AWS Region

4. **Test the connection** using the "Test Connection" button to verify your credentials.

5. **Create or select a bucket** in the Bucket Management tab.

6. **Configure backup settings** in the Media Backup tab according to your needs.

== Getting AWS Credentials ==

To use this plugin, you need AWS credentials with S3 access:

1. Log in to your AWS Console
2. Navigate to IAM (Identity and Access Management)
3. Create a new user or use existing user
4. Attach the `AmazonS3FullAccess` policy (or create a custom policy with required S3 permissions)
5. Generate Access Keys for the user
6. Copy the Access Key ID and Secret Access Key to the plugin settings

**Minimum Required S3 Permissions:**
* s3:ListAllMyBuckets
* s3:CreateBucket
* s3:DeleteBucket
* s3:ListBucket
* s3:GetObject
* s3:PutObject
* s3:DeleteObject

== Frequently Asked Questions ==

= Do I need the AWS SDK for PHP installed? =

No, the plugin includes a fallback manual implementation that works without the AWS SDK. However, if you have the AWS SDK for PHP v3+ available via Composer, the plugin will use it for enhanced performance.

= Can I use this plugin with existing S3 buckets? =

Yes, the plugin can work with existing S3 buckets. Simply enter your credentials and select your existing bucket as the default bucket.

= What happens if my AWS credentials are incorrect? =

The plugin includes connection testing functionality. If credentials are incorrect, you'll receive an error message with details about the connection failure.

= Can I backup existing media files? =

Yes, the plugin provides a "Backup Existing Media" feature that scans your `/wp-content/uploads/` directory and uploads all media files to S3.

= Is the plugin compatible with multisite? =

The current version is designed for single-site installations. Multisite support may be added in future versions.

= How do I set up GitHub updates for private repositories? =

In the Plugin Updates tab, enter your GitHub Personal Access Token. This allows the plugin to access private repositories and increases API rate limits.

= What file types are supported for backup? =

The plugin supports all common media file types including images (jpg, png, gif, webp), videos (mp4, avi, mov), audio files (mp3, wav), documents (pdf, doc, xls), and archives (zip, rar).

== Screenshots ==

1. **AWS Credentials Setup** - Easy configuration of AWS credentials with region selection
2. **Bucket Management** - Create, list, and manage S3 buckets
3. **File Manager** - Intuitive interface for managing S3 files and folders
4. **Media Backup Settings** - Configure automatic backup schedules and options
5. **Plugin Updates** - GitHub-based update management with version checking
6. **Backup Statistics** - Comprehensive backup progress and statistics

== Changelog ==

= 1.0.0 =
* Initial release
* Complete AWS S3 integration with credential management
* Bucket creation, listing, and deletion functionality
* Advanced file manager with upload, download, and folder management
* Automatic media backup with flexible scheduling options
* WordPress media upload integration
* GitHub-based plugin update system
* Comprehensive admin interface with tabbed navigation
* AJAX-powered operations for smooth user experience
* Security features with nonce verification and permission checks
* Responsive design compatible with WordPress admin themes
* Error handling and user feedback systems
* Support for both AWS SDK and manual S3 implementation

== Upgrade Notice ==

= 1.0.0 =
Initial release of S3 Master. This version provides complete S3 integration functionality.

== Privacy Policy ==

This plugin stores AWS credentials locally in your WordPress database using the WordPress Options API. No data is transmitted to external services except for direct communication with AWS S3 and GitHub (for updates). AWS credentials are never shared with third parties.

== Support ==

For support, feature requests, and bug reports, please visit the [GitHub repository](https://github.com/mrajaysingh/S3-MASTER) or create an issue on GitHub.

== Development ==

This plugin is open source and available on GitHub. Contributions are welcome!

**GitHub Repository**: https://github.com/mrajaysingh/S3-MASTER

== Credits ==

* Developed by Ajay Singh
* Uses WordPress native APIs and design patterns
* Compatible with AWS SDK for PHP v3+
* Includes fallback manual S3 implementation for maximum compatibility
