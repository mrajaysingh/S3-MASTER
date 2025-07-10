# S3 Master Plugin - Installation & Usage Guide

## Quick Installation

1. **Download or Clone the Plugin**
   ```bash
   git clone https://github.com/mrajaysingh/S3-MASTER.git
   ```

2. **Upload to WordPress**
   - Copy the entire `S3-MASTER` folder to `/wp-content/plugins/s3-master/`
   - Or upload as a ZIP file through WordPress admin

3. **Activate the Plugin**
   - Go to WordPress admin > Plugins
   - Find "S3 Master" and click "Activate"

## AWS Setup

### Step 1: Create AWS Account
1. Visit [AWS Console](https://aws.amazon.com/)
2. Create an account or sign in to existing account

### Step 2: Create IAM User
1. Navigate to **IAM** service in AWS Console
2. Click **Users** > **Add users**
3. Enter username (e.g., `s3-master-wordpress`)
4. Select **Programmatic access**
5. Click **Next: Permissions**

### Step 3: Set Permissions
**Option A: Use Existing Policy (Recommended for testing)**
1. Click **Attach existing policies directly**
2. Search for and select **AmazonS3FullAccess**

**Option B: Create Custom Policy (Recommended for production)**
1. Click **Create policy**
2. Use JSON editor and paste:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListAllMyBuckets",
                "s3:GetBucketLocation"
            ],
            "Resource": "*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:CreateBucket",
                "s3:DeleteBucket",
                "s3:ListBucket",
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:GetObjectAcl",
                "s3:PutObjectAcl"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name/*",
                "arn:aws:s3:::your-bucket-name"
            ]
        }
    ]
}
```

### Step 4: Get Access Keys
1. Complete user creation
2. **Important**: Download and save the CSV file with credentials
3. Note down:
   - Access Key ID
   - Secret Access Key

## Plugin Configuration

### Step 1: Access Plugin Settings
1. Go to WordPress admin
2. Navigate to **Settings > S3 Master**

### Step 2: Configure AWS Credentials
1. Click **AWS Credentials** tab
2. Enter your:
   - **AWS Access Key ID**
   - **AWS Secret Access Key** 
   - **AWS Region** (select from dropdown)
3. Click **Save Credentials**
4. Click **Test Connection** to verify

### Step 3: Set Up Bucket
1. Go to **Bucket Management** tab
2. **Option A**: Create new bucket
   - Enter bucket name (must be globally unique)
   - Select region
   - Click **Create Bucket**
3. **Option B**: Use existing bucket
   - Click **Refresh List** to see existing buckets
4. Set as default bucket in **Default Bucket** section

### Step 4: Configure Media Backup
1. Go to **Media Backup** tab
2. Check **Enable Auto Backup**
3. Select backup schedule:
   - **Immediate**: Backup on upload
   - **Hourly/Daily/Weekly**: Scheduled backup
   - **Custom**: Set custom interval
4. Click **Save Settings**

## Usage Guide

### File Manager
1. Go to **File Manager** tab
2. **Upload Files**:
   - Click **Upload File**
   - Select files or drag & drop
   - Click **Upload**
3. **Create Folders**:
   - Click **Create Folder**
   - Enter folder name
4. **Navigate**: Click on folders to browse
5. **Delete**: Click delete button next to files/folders

### Media Backup
1. **Manual Backup**:
   - Go to **Media Backup** tab
   - Click **Backup Existing Media**
   - Wait for completion
2. **Automatic Backup**:
   - Enabled via backup settings
   - New uploads automatically backed up
   - Scheduled backups run via WordPress cron

### Plugin Updates
1. Go to **Plugin Updates** tab
2. Click **Check for Updates**
3. Updates will appear in WordPress admin when available
4. **GitHub Token** (optional):
   - For private repositories
   - Increases API rate limits

## Advanced Features

### Composer Installation (Optional)
For enhanced performance, install AWS SDK:
```bash
cd /path/to/plugin
composer install --no-dev
```

### Keyboard Shortcuts (File Manager)
- **Ctrl/Cmd + U**: Upload files
- **Ctrl/Cmd + N**: Create new folder
- **F5**: Refresh file list

### Backup Scheduling
- Uses WordPress Cron system
- Schedules are persistent across page loads
- Manual backup available anytime

## Troubleshooting

### Connection Issues
1. **"Connection failed"**:
   - Verify AWS credentials
   - Check internet connectivity
   - Ensure IAM user has S3 permissions

2. **"Access denied"**:
   - Check IAM policy permissions
   - Verify bucket access rights

### Upload Issues
1. **"Upload failed"**:
   - Check file size limits
   - Verify bucket permissions
   - Check available disk space

2. **"Bucket not found"**:
   - Ensure bucket exists
   - Check bucket name spelling
   - Verify region settings

### Backup Issues
1. **"No files to backup"**:
   - Check `/wp-content/uploads/` directory
   - Verify file permissions

2. **"Backup failed"**:
   - Check AWS credentials
   - Verify bucket permissions
   - Check error logs

## File Structure
```
s3-master/
├── s3-master.php           # Main plugin file
├── composer.json           # Composer dependencies
├── README.txt             # WordPress plugin readme
├── admin/
│   └── settings-page.php  # Admin interface
├── includes/
│   ├── aws-client.php     # AWS S3 client
│   ├── bucket-manager.php # Bucket operations
│   ├── file-manager.php   # File operations
│   ├── media-backup.php   # Backup functionality
│   └── updater.php        # GitHub updates
├── assets/
│   ├── js/
│   │   └── admin.js       # Admin JavaScript
│   └── css/
│       └── admin.css      # Admin styles
```

## Security Best Practices

1. **AWS Credentials**:
   - Use IAM users, not root account
   - Follow principle of least privilege
   - Rotate keys regularly

2. **WordPress**:
   - Keep WordPress updated
   - Use strong admin passwords
   - Regular backups

3. **S3 Buckets**:
   - Use private buckets
   - Enable versioning
   - Set up bucket policies

## Support

- **GitHub Issues**: [Report bugs](https://github.com/mrajaysingh/S3-MASTER/issues)
- **Documentation**: Check README.txt for detailed information
- **WordPress Support**: Standard WordPress debugging practices apply

## License

This plugin is licensed under GPL v2 or later. See LICENSE file for details.
