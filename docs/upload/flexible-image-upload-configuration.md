# Flexible Image Upload System - Configuration Options

## Overview

This document provides detailed information about all configuration options available in the flexible image upload system, including context definitions, security settings, and performance tuning options.

## Configuration Files

### Main Configuration File

The primary configuration is located at `config/uploads.php`:

```php
<?php

return [
    'contexts' => [
        // Context-specific configurations
    ],
    'defaults' => [
        // Default settings for all contexts
    ],
    'security' => [
        // Security-related settings
    ],
    'performance' => [
        // Performance optimization settings
    ],
    'virus_scanning' => [
        // Virus scanning configuration
    ],
    'chunked' => [
        // Chunked upload settings
    ],
];
```

## Context Configuration

### Available Contexts

#### Avatar Context
```php
'avatar' => [
    'max_size' => env('AVATAR_MAX_SIZE', 2048), // KB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'directory' => '', // Empty since avatars disk already points to avatars directory
    'generate_thumbnails' => true,
    'public' => true,
    'disk' => 'avatars',
],
```

**Use Case**: User profile pictures
**Characteristics**: Small file size, public access, thumbnail generation enabled

#### Assignment Context
```php
'assignment' => [
    'max_size' => env('ASSIGNMENT_MAX_SIZE', 10240), // KB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'directory' => 'assignments',
    'generate_thumbnails' => false,
    'public' => false,
    'disk' => 'assignments',
],
```

**Use Case**: Assignment-related images
**Characteristics**: Larger file size, private access, no thumbnails

#### General Context
```php
'general' => [
    'max_size' => env('GENERAL_MAX_SIZE', 5120), // KB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'directory' => 'general',
    'generate_thumbnails' => true,
    'public' => true,
    'disk' => 'images',
],
```

**Use Case**: General purpose image uploads
**Characteristics**: Medium file size, public access, supports SVG

### Context Configuration Options

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `max_size` | `integer` | Maximum file size in KB | `10240` |
| `allowed_types` | `array` | Allowed MIME types | `['image/jpeg', 'image/png']` |
| `allowed_extensions` | `array` | Allowed file extensions | `['jpg', 'jpeg', 'png']` |
| `directory` | `string` | Storage directory name | `'general'` |
| `generate_thumbnails` | `boolean` | Enable thumbnail generation | `false` |
| `public` | `boolean` | Public or private access | `true` |
| `disk` | `string` | Storage disk to use | `'images'` |

### Creating Custom Contexts

```php
// Add to config/uploads.php
'contexts' => [
    'custom_context' => [
        'max_size' => 15360, // 15MB
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/tiff'
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'tiff'],
        'directory' => 'custom',
        'generate_thumbnails' => true,
        'public' => false,
        'disk' => 'custom_disk',
        'validation_rules' => [
            'min_width' => 800,
            'min_height' => 600,
            'max_width' => 4000,
            'max_height' => 3000,
        ],
    ],
],
```

## Default Settings

```php
'defaults' => [
    'max_size' => env('DEFAULT_UPLOAD_MAX_SIZE', 10240), // KB
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'generate_thumbnails' => false,
    'public' => true,
    'disk' => 'images',
],
```

These defaults are used when a context doesn't specify a particular option.

## Security Configuration

### File Validation Settings

```php
'security' => [
    'validate_mime_type' => true,
    'validate_file_signature' => true,
    'sanitize_filename' => true,
    'generate_unique_names' => true,
    'scan_for_malware' => env('UPLOAD_SCAN_MALWARE', false),
    'scan_for_malicious_content' => env('UPLOAD_SCAN_MALICIOUS_CONTENT', true),
    'check_file_entropy' => env('UPLOAD_CHECK_FILE_ENTROPY', true),
    'detect_polyglot_files' => env('UPLOAD_DETECT_POLYGLOT_FILES', true),
    'validate_image_metadata' => env('UPLOAD_VALIDATE_IMAGE_METADATA', true),
    'check_steganography' => env('UPLOAD_CHECK_STEGANOGRAPHY', true),
],
```

#### Security Options Explained

| Option | Description | Recommended |
|--------|-------------|-------------|
| `validate_mime_type` | Check MIME type matches file extension | `true` |
| `validate_file_signature` | Verify file signature/magic bytes | `true` |
| `sanitize_filename` | Remove dangerous characters from filenames | `true` |
| `generate_unique_names` | Generate unique filenames to prevent conflicts | `true` |
| `scan_for_malware` | Enable virus scanning (requires ClamAV) | `true` (production) |
| `scan_for_malicious_content` | Check for embedded scripts/malicious content | `true` |
| `check_file_entropy` | Analyze file entropy for suspicious patterns | `true` |
| `detect_polyglot_files` | Detect files that are valid in multiple formats | `true` |
| `validate_image_metadata` | Check image metadata for anomalies | `true` |
| `check_steganography` | Basic steganography detection | `false` (performance impact) |

### Virus Scanning Configuration

```php
'virus_scanning' => [
    'enabled' => env('VIRUS_SCANNING_ENABLED', false),
    'engine' => env('VIRUS_SCANNING_ENGINE', 'clamav'),
    'timeout' => env('VIRUS_SCANNING_TIMEOUT', 30),
    'quarantine_infected' => env('QUARANTINE_INFECTED_FILES', true),
    'block_on_scan_failure' => env('BLOCK_ON_SCAN_FAILURE', false),
],
```

#### Virus Scanning Setup

1. **Install ClamAV**:
```bash
# Ubuntu/Debian
sudo apt-get install clamav clamav-daemon

# CentOS/RHEL
sudo yum install clamav clamav-update

# Update virus definitions
sudo freshclam
```

2. **Configure ClamAV**:
```bash
# Start ClamAV daemon
sudo systemctl start clamav-daemon
sudo systemctl enable clamav-daemon

# Test scanning
clamscan --version
```

## Performance Configuration

### Basic Performance Settings

```php
'performance' => [
    'chunk_size' => env('UPLOAD_CHUNK_SIZE', 1024), // KB
    'memory_limit' => env('UPLOAD_MEMORY_LIMIT', '256M'),
    'timeout' => env('UPLOAD_TIMEOUT', 300), // seconds
    'cleanup_failed_uploads' => true,
    'cleanup_interval' => 3600, // seconds
    'buffer_size' => env('UPLOAD_BUFFER_SIZE', 8192), // bytes
    'max_in_memory_size' => env('UPLOAD_MAX_IN_MEMORY_SIZE', 10 * 1024 * 1024), // 10MB
    'hash_streaming_threshold' => env('UPLOAD_HASH_STREAMING_THRESHOLD', 10 * 1024 * 1024), // 10MB
    'memory_limit_threshold' => env('UPLOAD_MEMORY_LIMIT_THRESHOLD', 0.8), // 80%
],
```

#### Performance Options Explained

| Option | Description | Recommended Value |
|--------|-------------|-------------------|
| `chunk_size` | Size of chunks for processing (KB) | `1024` (1MB) |
| `memory_limit` | PHP memory limit for uploads | `256M` or higher |
| `timeout` | Maximum upload processing time | `300` seconds |
| `cleanup_failed_uploads` | Auto-cleanup failed uploads | `true` |
| `cleanup_interval` | Cleanup interval in seconds | `3600` (1 hour) |
| `buffer_size` | File read buffer size | `8192` bytes |
| `max_in_memory_size` | Max file size to process in memory | `10MB` |
| `hash_streaming_threshold` | File size threshold for streaming hash | `10MB` |
| `memory_limit_threshold` | Memory usage threshold (0.0-1.0) | `0.8` (80%) |

### Chunked Upload Configuration

```php
'chunked' => [
    'enabled' => env('CHUNKED_UPLOAD_ENABLED', true),
    'chunk_size' => env('CHUNKED_UPLOAD_CHUNK_SIZE', 5 * 1024 * 1024), // 5MB
    'max_file_size' => env('CHUNKED_UPLOAD_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
    'threshold' => env('CHUNKED_UPLOAD_THRESHOLD', 10 * 1024 * 1024), // 10MB
    'expiration_minutes' => env('CHUNKED_UPLOAD_EXPIRATION_MINUTES', 60),
    'disk' => env('CHUNKED_UPLOAD_DISK', 'local'),
],
```

#### Chunked Upload Benefits

- **Large File Support**: Handle files larger than PHP upload limits
- **Resume Capability**: Resume interrupted uploads
- **Better User Experience**: Show progress for large uploads
- **Memory Efficiency**: Process large files without memory issues

## Storage Disk Configuration

### Local Storage

```php
// config/filesystems.php
'disks' => [
    'images' => [
        'driver' => 'local',
        'root' => storage_path('app/public/images'),
        'url' => env('APP_URL').'/storage/images',
        'visibility' => 'public',
        'throw' => false,
    ],
],
```

### S3 Storage

```php
'disks' => [
    'images' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'root' => 'uploads/images',
        'visibility' => 'public',
        'options' => [
            'CacheControl' => 'max-age=31536000',
            'Metadata' => [
                'uploaded-by' => 'flexible-image-upload-system',
            ],
        ],
    ],
],
```

### Google Cloud Storage

```php
'disks' => [
    'images' => [
        'driver' => 'gcs',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', 'uploads'),
        'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        'visibility' => 'public',
    ],
],
```

## Environment-Specific Configuration

### Development Environment

```env
# Development settings
APP_ENV=local
APP_DEBUG=true

# Local storage
IMAGE_STORAGE_DRIVER=local
AVATAR_STORAGE_DRIVER=local

# Relaxed security
UPLOAD_SCAN_MALWARE=false
UPLOAD_SCAN_MALICIOUS_CONTENT=false

# Smaller limits for testing
AVATAR_MAX_SIZE=1024
ASSIGNMENT_MAX_SIZE=5120
```

### Staging Environment

```env
# Staging settings
APP_ENV=staging
APP_DEBUG=false

# Cloud storage
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3

# Enhanced security
UPLOAD_SCAN_MALWARE=true
UPLOAD_SCAN_MALICIOUS_CONTENT=true

# Production-like limits
AVATAR_MAX_SIZE=2048
ASSIGNMENT_MAX_SIZE=10240
```

### Production Environment

```env
# Production settings
APP_ENV=production
APP_DEBUG=false

# Cloud storage with CDN
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3
AWS_CLOUDFRONT_URL=https://your-cdn-domain.cloudfront.net

# Maximum security
UPLOAD_SCAN_MALWARE=true
UPLOAD_SCAN_MALICIOUS_CONTENT=true
UPLOAD_CHECK_FILE_ENTROPY=true
UPLOAD_DETECT_POLYGLOT_FILES=true
UPLOAD_VALIDATE_IMAGE_METADATA=true

# Production limits
AVATAR_MAX_SIZE=2048
ASSIGNMENT_MAX_SIZE=10240
GENERAL_MAX_SIZE=5120
```

## Advanced Configuration

### Custom Validation Rules

```php
// Custom context with advanced validation
'professional_photos' => [
    'max_size' => 20480, // 20MB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/tiff'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'tiff'],
    'directory' => 'professional',
    'generate_thumbnails' => true,
    'public' => false,
    'disk' => 'professional_storage',
    'validation_rules' => [
        'min_width' => 1920,
        'min_height' => 1080,
        'max_width' => 8000,
        'max_height' => 6000,
        'min_dpi' => 300,
        'color_profile' => 'sRGB',
        'max_compression_ratio' => 0.1,
    ],
],
```

### Thumbnail Configuration

```php
'thumbnail_settings' => [
    'enabled' => true,
    'driver' => 'imagick', // or 'gd'
    'quality' => 85,
    'sizes' => [
        'small' => ['width' => 150, 'height' => 150, 'crop' => true],
        'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
        'large' => ['width' => 800, 'height' => 600, 'crop' => false],
    ],
    'formats' => [
        'webp' => ['quality' => 80, 'enabled' => true],
        'avif' => ['quality' => 70, 'enabled' => false],
    ],
],
```

### CDN Integration

```php
'cdn' => [
    'enabled' => env('CDN_ENABLED', false),
    'provider' => env('CDN_PROVIDER', 'cloudfront'), // cloudfront, cloudflare, etc.
    'url' => env('CDN_URL'),
    'cache_control' => 'public, max-age=31536000, immutable',
    'invalidation' => [
        'enabled' => true,
        'batch_size' => 100,
        'delay' => 300, // seconds
    ],
],
```

## Configuration Validation

### Validation Service

```php
class UploadConfigValidator
{
    public function validate(array $config): array
    {
        $errors = [];
        
        // Validate contexts
        foreach ($config['contexts'] as $context => $settings) {
            if (empty($settings['max_size']) || $settings['max_size'] <= 0) {
                $errors[] = "Context '{$context}': max_size must be positive";
            }
            
            if (empty($settings['allowed_types'])) {
                $errors[] = "Context '{$context}': allowed_types cannot be empty";
            }
            
            if (!Storage::disk($settings['disk'])->exists('')) {
                $errors[] = "Context '{$context}': disk '{$settings['disk']}' is not accessible";
            }
        }
        
        return $errors;
    }
}
```

### Configuration Testing

```php
// Artisan command to test configuration
class TestUploadConfigCommand extends Command
{
    protected $signature = 'upload:test-config';
    
    public function handle()
    {
        $validator = new UploadConfigValidator();
        $errors = $validator->validate(config('uploads'));
        
        if (empty($errors)) {
            $this->info('Upload configuration is valid');
        } else {
            $this->error('Configuration errors found:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }
    }
}
```

This configuration documentation provides comprehensive coverage of all available options and settings for the flexible image upload system.
