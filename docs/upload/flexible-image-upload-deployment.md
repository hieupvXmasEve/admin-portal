# Flexible Image Upload System - Deployment Configuration

## Overview

This document provides comprehensive deployment configuration for the flexible image upload system, including production-ready storage configurations, environment variable setup, and monitoring/logging configuration.

## Table of Contents

- [Environment Variables](#environment-variables)
- [Storage Configuration](#storage-configuration)
- [Production Setup](#production-setup)
- [Monitoring and Logging](#monitoring-and-logging)
- [Security Configuration](#security-configuration)
- [Performance Optimization](#performance-optimization)
- [Troubleshooting](#troubleshooting)

## Environment Variables

### Core Configuration

```env
# Application Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Default Upload Settings
DEFAULT_UPLOAD_MAX_SIZE=10240  # KB
IMAGE_STORAGE_DRIVER=s3
AVATAR_STORAGE_DRIVER=s3
ASSIGNMENT_STORAGE_DRIVER=s3

# Context-specific Size Limits
AVATAR_MAX_SIZE=2048           # KB (2MB)
ASSIGNMENT_MAX_SIZE=10240      # KB (10MB)
GENERAL_MAX_SIZE=5120          # KB (5MB)
```

### AWS S3 Configuration

```env
# AWS Credentials
AWS_ACCESS_KEY_ID=your_access_key_id
AWS_SECRET_ACCESS_KEY=your_secret_access_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-production-bucket
AWS_URL=https://your-bucket.s3.amazonaws.com
AWS_ENDPOINT=

# S3 Specific Settings
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_BUCKET_PREFIX=uploads/
AWS_CLOUDFRONT_URL=https://your-cloudfront-domain.cloudfront.net
```

### Google Cloud Storage Configuration

```env
# Google Cloud Storage
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_KEY_FILE=/path/to/service-account-key.json
GOOGLE_CLOUD_STORAGE_BUCKET=your-gcs-bucket
GOOGLE_CLOUD_STORAGE_PATH_PREFIX=uploads/
GOOGLE_CLOUD_STORAGE_API_URI=https://storage.googleapis.com
```

### Security Settings

```env
# File Validation
UPLOAD_SCAN_MALWARE=true
UPLOAD_SCAN_MALICIOUS_CONTENT=true
UPLOAD_CHECK_FILE_ENTROPY=true
UPLOAD_DETECT_POLYGLOT_FILES=true
UPLOAD_VALIDATE_IMAGE_METADATA=true
UPLOAD_CHECK_STEGANOGRAPHY=true

# Virus Scanning (ClamAV)
VIRUS_SCANNING_ENABLED=true
VIRUS_SCANNING_ENGINE=clamav
VIRUS_SCANNING_TIMEOUT=30
QUARANTINE_INFECTED_FILES=true
BLOCK_ON_SCAN_FAILURE=false
```

### Performance Settings

```env
# Upload Performance
UPLOAD_CHUNK_SIZE=1024         # KB
UPLOAD_MEMORY_LIMIT=512M
UPLOAD_TIMEOUT=300             # seconds
UPLOAD_BUFFER_SIZE=8192        # bytes
UPLOAD_MAX_IN_MEMORY_SIZE=10485760  # 10MB in bytes
UPLOAD_HASH_STREAMING_THRESHOLD=10485760  # 10MB in bytes
UPLOAD_MEMORY_LIMIT_THRESHOLD=0.8  # 80%

# Chunked Upload Settings
CHUNKED_UPLOAD_ENABLED=true
CHUNKED_UPLOAD_CHUNK_SIZE=5242880    # 5MB in bytes
CHUNKED_UPLOAD_MAX_FILE_SIZE=104857600  # 100MB in bytes
CHUNKED_UPLOAD_THRESHOLD=10485760    # 10MB in bytes
CHUNKED_UPLOAD_EXPIRATION_MINUTES=60
CHUNKED_UPLOAD_DISK=local
```

### Logging and Monitoring

```env
# Logging Configuration
LOG_CHANNEL=stack
LOG_LEVEL=info
LOG_UPLOAD_ACTIVITIES=true
LOG_UPLOAD_ERRORS=true
LOG_UPLOAD_PERFORMANCE=true

# Monitoring
UPLOAD_METRICS_ENABLED=true
UPLOAD_METRICS_DRIVER=prometheus
UPLOAD_HEALTH_CHECK_ENABLED=true
UPLOAD_HEALTH_CHECK_INTERVAL=300  # seconds
```

## Storage Configuration

### Local Storage (Development/Testing)

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
    'avatars' => [
        'driver' => 'local',
        'root' => storage_path('app/public/avatars'),
        'url' => env('APP_URL').'/storage/avatars',
        'visibility' => 'public',
        'throw' => false,
    ],
    'assignments' => [
        'driver' => 'local',
        'root' => storage_path('app/private/assignments'),
        'visibility' => 'private',
        'throw' => false,
    ],
],
```

### AWS S3 Production Configuration

```php
// config/filesystems.php
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
        'root' => 'images',
        'visibility' => 'public',
        'options' => [
            'CacheControl' => 'max-age=31536000', // 1 year
            'Metadata' => [
                'uploaded-by' => 'flexible-image-upload-system',
            ],
        ],
    ],
    'avatars' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_CLOUDFRONT_URL', env('AWS_URL')),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'root' => 'avatars',
        'visibility' => 'public',
        'options' => [
            'CacheControl' => 'max-age=31536000',
            'ACL' => 'public-read',
        ],
    ],
    'assignments' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'root' => 'assignments',
        'visibility' => 'private',
        'options' => [
            'ServerSideEncryption' => 'AES256',
        ],
    ],
],
```

### Google Cloud Storage Configuration

```php
// config/filesystems.php
'disks' => [
    'images' => [
        'driver' => 'gcs',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', 'images'),
        'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        'visibility' => 'public',
        'metadata' => [
            'cacheControl' => 'public, max-age=31536000',
        ],
    ],
    'avatars' => [
        'driver' => 'gcs',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', 'avatars'),
        'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        'visibility' => 'public',
        'metadata' => [
            'cacheControl' => 'public, max-age=31536000',
        ],
    ],
],
```

## Production Setup

### 1. Server Requirements

```bash
# Minimum server specifications
CPU: 2+ cores
RAM: 4GB+ (8GB recommended)
Storage: SSD with sufficient space for temporary files
PHP: 8.4+
Extensions: gd, imagick, fileinfo, exif
```

### 2. PHP Configuration

```ini
; php.ini production settings
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 512M
max_input_vars = 3000

; Enable OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### 3. Web Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/public;
    index index.php;

    # Upload size limits
    client_max_body_size 100M;
    client_body_timeout 300s;
    client_header_timeout 300s;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # Handle uploads
    location /api/images/upload {
        try_files $uri $uri/ /index.php?$query_string;
        
        # Increase timeouts for large uploads
        proxy_read_timeout 300s;
        proxy_connect_timeout 300s;
        proxy_send_timeout 300s;
    }

    # Static file serving with caching
    location ~* \.(jpg|jpeg|png|gif|webp|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
        access_log off;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Upload timeouts
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/public
    
    # Upload limits
    LimitRequestBody 104857600  # 100MB
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    
    # Static file caching
    <LocationMatch "\.(jpg|jpeg|png|gif|webp|svg)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header set Cache-Control "public, immutable"
    </LocationMatch>
    
    # PHP configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

### 4. Queue Configuration

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => null,
    ],
],

// Supervisor configuration for queue workers
// /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

### 5. Cron Jobs

```bash
# Add to crontab
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1

# Cleanup orphaned files (runs daily)
0 2 * * * cd /var/www/html && php artisan uploads:cleanup >> /var/log/upload-cleanup.log 2>&1
```

## Monitoring and Logging

### 1. Application Logging

```php
// config/logging.php
'channels' => [
    'uploads' => [
        'driver' => 'daily',
        'path' => storage_path('logs/uploads.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 30,
        'replace_placeholders' => true,
    ],
    
    'upload_errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/upload-errors.log'),
        'level' => 'error',
        'days' => 90,
    ],
    
    'upload_security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/upload-security.log'),
        'level' => 'warning',
        'days' => 365,
    ],
],
```

### 2. Metrics Collection

```php
// Custom metrics service
class UploadMetricsService
{
    public function recordUpload(string $context, int $fileSize, float $duration): void
    {
        // Prometheus metrics
        $this->incrementCounter('uploads_total', ['context' => $context]);
        $this->recordHistogram('upload_duration_seconds', $duration, ['context' => $context]);
        $this->recordHistogram('upload_file_size_bytes', $fileSize, ['context' => $context]);
    }
    
    public function recordError(string $context, string $errorType): void
    {
        $this->incrementCounter('upload_errors_total', [
            'context' => $context,
            'error_type' => $errorType
        ]);
    }
}
```

### 3. Health Checks

```php
// Health check endpoint
Route::get('/health/uploads', function () {
    $checks = [
        'storage_writable' => is_writable(storage_path('app')),
        'temp_dir_writable' => is_writable(sys_get_temp_dir()),
        'memory_available' => memory_get_usage(true) < (1024 * 1024 * 400), // 400MB
        'disk_space' => disk_free_space(storage_path()) > (1024 * 1024 * 1024), // 1GB
    ];
    
    $healthy = !in_array(false, $checks, true);
    
    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ], $healthy ? 200 : 503);
});
```

### 4. Monitoring Dashboard

```yaml
# Grafana dashboard configuration
dashboard:
  title: "Image Upload System"
  panels:
    - title: "Upload Rate"
      type: "graph"
      targets:
        - expr: "rate(uploads_total[5m])"
    
    - title: "Error Rate"
      type: "graph"
      targets:
        - expr: "rate(upload_errors_total[5m])"
    
    - title: "Upload Duration"
      type: "graph"
      targets:
        - expr: "histogram_quantile(0.95, upload_duration_seconds_bucket)"
    
    - title: "File Size Distribution"
      type: "histogram"
      targets:
        - expr: "upload_file_size_bytes_bucket"
```

## Security Configuration

### 1. File Validation Service

```php
// Enhanced security configuration
return [
    'security' => [
        'validate_mime_type' => true,
        'validate_file_signature' => true,
        'sanitize_filename' => true,
        'generate_unique_names' => true,
        'scan_for_malware' => env('UPLOAD_SCAN_MALWARE', true),
        'scan_for_malicious_content' => true,
        'check_file_entropy' => true,
        'detect_polyglot_files' => true,
        'validate_image_metadata' => true,
        'check_steganography' => true,
        'max_pixel_count' => 50000000, // 50MP limit
        'blocked_extensions' => [
            'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
            'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js'
        ],
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 
            'image/webp', 'image/svg+xml'
        ],
    ],
];
```

### 2. Rate Limiting

```php
// Rate limiting configuration
Route::middleware(['throttle:uploads'])->group(function () {
    Route::post('/api/images/upload', [ImageUploadController::class, 'upload']);
});

// config/cache.php - Rate limiting store
'stores' => [
    'upload_throttle' => [
        'driver' => 'redis',
        'connection' => 'default',
        'prefix' => 'upload_throttle',
    ],
],
```

### 3. Content Security Policy

```php
// Middleware for CSP headers
class ContentSecurityPolicyMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        $csp = [
            "default-src 'self'",
            "img-src 'self' data: https:",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "connect-src 'self' https://api.your-domain.com",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
        
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        
        return $response;
    }
}
```

## Performance Optimization

### 1. CDN Configuration

```php
// CloudFront distribution settings
return [
    'cdn' => [
        'enabled' => env('CDN_ENABLED', true),
        'url' => env('CDN_URL', 'https://your-cloudfront-domain.cloudfront.net'),
        'cache_control' => 'public, max-age=31536000, immutable',
        'compress' => true,
        'origins' => [
            's3' => [
                'domain' => env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com',
                'path' => '/uploads',
            ],
        ],
    ],
];
```

### 2. Image Optimization

```php
// Image processing configuration
return [
    'image_optimization' => [
        'enabled' => env('IMAGE_OPTIMIZATION_ENABLED', true),
        'quality' => [
            'jpeg' => 85,
            'webp' => 80,
            'png' => 9, // compression level
        ],
        'auto_orient' => true,
        'strip_metadata' => true,
        'progressive_jpeg' => true,
        'thumbnails' => [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 800, 'height' => 600],
        ],
    ],
];
```

### 3. Caching Strategy

```php
// Redis caching for upload metadata
class UploadCacheService
{
    public function cacheUploadRecord(UploadRecord $upload): void
    {
        Cache::put(
            "upload:{$upload->id}",
            $upload->toArray(),
            now()->addHours(24)
        );
    }
    
    public function getCachedUpload(string $id): ?array
    {
        return Cache::get("upload:{$id}");
    }
}
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Upload Timeouts

**Problem**: Large file uploads timing out

**Solution**:
```bash
# Increase PHP timeouts
echo "max_execution_time = 300" >> /etc/php/8.4/fpm/php.ini
echo "max_input_time = 300" >> /etc/php/8.4/fpm/php.ini

# Increase web server timeouts
# Nginx
echo "client_body_timeout 300s;" >> /etc/nginx/sites-available/default
echo "client_header_timeout 300s;" >> /etc/nginx/sites-available/default

# Restart services
systemctl restart php8.4-fpm nginx
```

#### 2. Memory Issues

**Problem**: Out of memory errors during upload processing

**Solution**:
```php
// Implement streaming for large files
class StreamingUploadProcessor
{
    public function processLargeFile(UploadedFile $file): void
    {
        $stream = fopen($file->getPathname(), 'r');
        $chunks = [];
        
        while (!feof($stream)) {
            $chunk = fread($stream, 8192); // 8KB chunks
            $chunks[] = $this->processChunk($chunk);
            
            // Free memory periodically
            if (count($chunks) > 100) {
                $this->flushChunks($chunks);
                $chunks = [];
            }
        }
        
        fclose($stream);
    }
}
```

#### 3. Storage Permission Issues

**Problem**: Permission denied errors when writing to storage

**Solution**:
```bash
# Fix storage permissions
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Create symbolic link for public storage
php artisan storage:link

# Verify permissions
ls -la /var/www/html/storage
```

#### 4. S3 Connection Issues

**Problem**: Cannot connect to S3 or upload fails

**Solution**:
```bash
# Test S3 connectivity
aws s3 ls s3://your-bucket --region us-east-1

# Verify IAM permissions
aws iam get-user
aws iam list-attached-user-policies --user-name your-user

# Test from application
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello World');
>>> Storage::disk('s3')->exists('test.txt');
```

### Monitoring Commands

```bash
# Monitor upload logs
tail -f storage/logs/uploads.log

# Check disk usage
df -h

# Monitor memory usage
free -h

# Check PHP-FPM status
systemctl status php8.4-fpm

# Monitor queue workers
supervisorctl status laravel-worker:*

# Check Redis connection
redis-cli ping
```

### Performance Monitoring

```bash
# Monitor upload performance
grep "upload_duration" storage/logs/uploads.log | tail -20

# Check slow queries
grep "slow" storage/logs/laravel.log

# Monitor system resources
htop
iotop
```

This deployment configuration provides a comprehensive setup for production environments with proper security, monitoring, and performance optimization.
