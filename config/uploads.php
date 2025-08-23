<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Contexts
    |--------------------------------------------------------------------------
    |
    | Define different upload contexts with their specific configurations.
    | Each context can have different size limits, allowed file types,
    | directory structures, and access permissions.
    |
    */
    'contexts' => [
        'avatar' => [
            'max_size' => env('AVATAR_MAX_SIZE', 2048), // KB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'directory' => '', // Empty since avatars disk already points to avatars directory
            'generate_thumbnails' => true,
            'public' => true,
            'disk' => 'avatars',
        ],
        'assignment' => [
            'max_size' => env('ASSIGNMENT_MAX_SIZE', 10240), // KB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'directory' => 'assignments',
            'generate_thumbnails' => false,
            'public' => false,
            'disk' => 'assignments',
        ],
        'general' => [
            'max_size' => env('GENERAL_MAX_SIZE', 5120), // KB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'directory' => 'general',
            'generate_thumbnails' => true,
            'public' => true,
            'disk' => 'images',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration values that apply to all contexts unless
    | specifically overridden in the context configuration.
    |
    */
    'defaults' => [
        'max_size' => env('DEFAULT_UPLOAD_MAX_SIZE', 10240), // KB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'generate_thumbnails' => false,
        'public' => true,
        'disk' => 'images',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for file uploads including validation
    | and file processing settings.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Virus Scanning Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for virus scanning integration.
    |
    */
    'virus_scanning' => [
        'enabled' => env('VIRUS_SCANNING_ENABLED', false),
        'engine' => env('VIRUS_SCANNING_ENGINE', 'clamav'),
        'timeout' => env('VIRUS_SCANNING_TIMEOUT', 30),
        'quarantine_infected' => env('QUARANTINE_INFECTED_FILES', true),
        'block_on_scan_failure' => env('BLOCK_ON_SCAN_FAILURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance-related configuration for handling uploads and file
    | processing operations.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Chunked Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for chunked file uploads for large files.
    |
    */
    'chunked' => [
        'enabled' => env('CHUNKED_UPLOAD_ENABLED', true),
        'chunk_size' => env('CHUNKED_UPLOAD_CHUNK_SIZE', 5 * 1024 * 1024), // 5MB
        'max_file_size' => env('CHUNKED_UPLOAD_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'threshold' => env('CHUNKED_UPLOAD_THRESHOLD', 10 * 1024 * 1024), // 10MB
        'expiration_minutes' => env('CHUNKED_UPLOAD_EXPIRATION_MINUTES', 60),
        'disk' => env('CHUNKED_UPLOAD_DISK', 'local'),
    ],
];
