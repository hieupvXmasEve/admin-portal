<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Excel Export Memory Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for memory-efficient Excel export operations
    |
    */

    // Memory limit for Excel exports (will be restored after export)
    'memory_limit' => env('EXCEL_MEMORY_LIMIT', '512M'),
    
    // Chunk size for processing large datasets
    'chunk_size' => env('EXCEL_CHUNK_SIZE', 100),
    
    // Maximum number of students before requiring chunked processing
    'max_students_direct' => env('EXCEL_MAX_STUDENTS_DIRECT', 500),
    
    // Temporary file cleanup settings
    'cleanup_temp_files' => env('EXCEL_CLEANUP_TEMP_FILES', true),
    'temp_file_lifetime_hours' => env('EXCEL_TEMP_FILE_LIFETIME', 24),
    
    // Memory usage monitoring
    'log_memory_usage' => env('EXCEL_LOG_MEMORY_USAGE', false),
    'memory_warning_threshold' => env('EXCEL_MEMORY_WARNING_THRESHOLD', '400M'),
];