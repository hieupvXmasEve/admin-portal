<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Import Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for user data import functionality
    |
    */

    'max_file_size' => env('IMPORT_MAX_FILE_SIZE', '10MB'),

    'allowed_extensions' => ['xlsx', 'xls'],

    'chunk_size' => env('IMPORT_CHUNK_SIZE', 1000),

    'max_processing_time' => env('IMPORT_MAX_PROCESSING_TIME', 300), // 5 minutes

    'default_password' => env('IMPORT_DEFAULT_PASSWORD', 'TempPassword123!'),

    'duplicate_handling' => env('IMPORT_DUPLICATE_HANDLING', 'update'), // update, skip, error

    'create_missing_campuses' => env('IMPORT_CREATE_MISSING_CAMPUSES', false),

    'notification_email' => env('IMPORT_NOTIFICATION_EMAIL'),

    'templates' => [
        'simple' => 'templates/import/users_simple_template.xlsx',
        'detailed' => 'templates/import/users_detailed_template.xlsx',
        'relationship' => 'templates/import/users_relationship_template.xlsx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'user' => [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ],

        'campus' => [
            'code' => 'required|string|exists:campuses,code',
            'name' => 'nullable|string|exists:campuses,name',
        ],

        'role' => [
            'code' => 'required|string|exists:roles,code',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'memory_limit' => '512M',
        'time_limit' => 300,
        'batch_size' => 100,
    ],
];
