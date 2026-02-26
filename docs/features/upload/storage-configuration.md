# Storage Configuration Guide

This guide explains how to configure and switch between different storage drivers for the flexible image upload system.

## Overview

The image upload system supports multiple storage drivers that can be configured independently for different upload contexts:

- **Local Storage**: Files stored on the local filesystem
- **Amazon S3**: Files stored on AWS S3 with optional CDN support
- **Other Cloud Providers**: Extensible to support Google Cloud Storage, Azure, etc.

## Storage Contexts

The system defines three main upload contexts, each with its own configurable storage driver:

### 1. Images (`images` disk)
- **Purpose**: General image uploads
- **Environment Variable**: `IMAGE_STORAGE_DRIVER`
- **Default**: `local`
- **Public Access**: Yes
- **Directory**: `images/`

### 2. Avatars (`avatars` disk)
- **Purpose**: User avatar images
- **Environment Variable**: `AVATAR_STORAGE_DRIVER`
- **Default**: `local`
- **Public Access**: Yes
- **Directory**: `avatars/`

### 3. Assignments (`assignments` disk)
- **Purpose**: Assignment-related images
- **Environment Variable**: `ASSIGNMENT_STORAGE_DRIVER`
- **Default**: `local`
- **Public Access**: No (private)
- **Directory**: `assignments/`

## Local Storage Configuration

### Setup

Local storage is configured by default and requires no additional setup beyond ensuring symbolic links are created:

```bash
php artisan storage:link
```

### Environment Variables

```env
# Local storage (default)
IMAGE_STORAGE_DRIVER=local
AVATAR_STORAGE_DRIVER=local
ASSIGNMENT_STORAGE_DRIVER=local
```

### Directory Structure

```
storage/app/
├── public/
│   ├── images/          # General images (public)
│   └── avatars/         # Avatar images (public)
└── private/
    └── assignments/     # Assignment images (private)
```

### Public Access

Files in `public/` directories are accessible via symbolic links:
- `public/storage/images/` → `storage/app/public/images/`
- `public/storage/avatars/` → `storage/app/public/avatars/`

## Amazon S3 Configuration

### Prerequisites

1. AWS account with S3 access
2. S3 bucket created
3. IAM user with appropriate permissions
4. AWS SDK installed (already included)

### Required IAM Permissions

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:GetObjectAcl",
                "s3:PutObjectAcl"
            ],
            "Resource": "arn:aws:s3:::your-bucket-name/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket"
            ],
            "Resource": "arn:aws:s3:::your-bucket-name"
        }
    ]
}
```

### Environment Variables

```env
# S3 Configuration
AWS_ACCESS_KEY_ID=your_access_key_id
AWS_SECRET_ACCESS_KEY=your_secret_access_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket-name.s3.amazonaws.com
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Switch specific contexts to S3
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3
ASSIGNMENT_STORAGE_DRIVER=s3
```

### Mixed Configuration Example

You can use different drivers for different contexts:

```env
# Use S3 for public images and avatars
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3

# Keep assignments on local storage for security
ASSIGNMENT_STORAGE_DRIVER=local
```

## Testing Storage Configuration

### Basic Test

Run the included test script to verify your storage configuration:

```bash
php test_driver_switching.php
```

### Manual Testing

```php
use Illuminate\Support\Facades\Storage;

// Test each disk
$disks = ['images', 'avatars', 'assignments'];

foreach ($disks as $diskName) {
    $disk = Storage::disk($diskName);
    
    // Create test file
    $disk->put('test.txt', 'Hello World');
    
    // Verify file exists
    if ($disk->exists('test.txt')) {
        echo "✓ {$diskName} disk working\n";
    }
    
    // Clean up
    $disk->delete('test.txt');
}
```

## Switching Between Drivers

### Zero-Downtime Switching

1. Update environment variables
2. Clear configuration cache: `php artisan config:clear`
3. Test the new configuration
4. No code changes required

### Migration Considerations

When switching from local to cloud storage:

1. **Backup existing files**
2. **Upload existing files to new storage**
3. **Update file URLs in database** (if stored)
4. **Test thoroughly before going live**

### Example Migration Script

```php
// Migrate files from local to S3
$localDisk = Storage::disk('local');
$s3Disk = Storage::disk('s3');

$files = $localDisk->allFiles('images');

foreach ($files as $file) {
    $content = $localDisk->get($file);
    $s3Disk->put($file, $content);
}
```

## Performance Considerations

### Local Storage
- **Pros**: Fast access, no external dependencies
- **Cons**: Limited scalability, no CDN support

### S3 Storage
- **Pros**: Scalable, CDN support, global distribution
- **Cons**: Network latency, costs for requests

### Optimization Tips

1. **Use CDN** with S3 for better performance
2. **Configure appropriate cache headers**
3. **Consider regional S3 buckets** for global applications
4. **Monitor storage costs** and optimize file sizes

## Troubleshooting

### Common Issues

1. **Symbolic links not working**
   ```bash
   php artisan storage:link
   ```

2. **S3 permissions errors**
   - Verify IAM permissions
   - Check bucket policies
   - Ensure correct region configuration

3. **File not found errors**
   - Verify disk configuration
   - Check file paths
   - Ensure proper environment variables

### Debug Commands

```bash
# Check current configuration
php artisan config:show filesystems

# Clear configuration cache
php artisan config:clear

# Test storage connectivity
php test_driver_switching.php
```

## Security Best Practices

### Local Storage
- Ensure proper file permissions
- Use symbolic links for public access
- Validate file types and sizes

### S3 Storage
- Use IAM roles instead of access keys when possible
- Enable bucket versioning
- Configure bucket policies for public/private access
- Enable server-side encryption
- Monitor access logs

### General
- Always validate uploaded files
- Sanitize file names
- Implement rate limiting
- Use virus scanning for uploaded files

## Environment Examples

### Development (.env.local)
```env
IMAGE_STORAGE_DRIVER=local
AVATAR_STORAGE_DRIVER=local
ASSIGNMENT_STORAGE_DRIVER=local
```

### Staging (.env.staging)
```env
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3
ASSIGNMENT_STORAGE_DRIVER=local
AWS_BUCKET=myapp-staging-uploads
```

### Production (.env.production)
```env
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3
ASSIGNMENT_STORAGE_DRIVER=s3
AWS_BUCKET=myapp-production-uploads
AWS_URL=https://cdn.myapp.com
```
