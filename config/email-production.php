<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Production Email Configuration Template
    |--------------------------------------------------------------------------
    |
    | This file contains production-ready email configuration templates
    | for the SMTP email system. Copy and customize these settings
    | for your production environment.
    |
    */

    'smtp_configurations' => [

        // Gmail/Google Workspace Configuration
        'gmail' => [
            'name' => 'Gmail SMTP',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('GMAIL_USERNAME'), // your-email@gmail.com
            'password' => env('GMAIL_PASSWORD'), // App-specific password
            'from_address' => env('GMAIL_FROM_ADDRESS'),
            'from_name' => env('GMAIL_FROM_NAME', 'Academic System'),
            'daily_limit' => 500, // Gmail daily sending limit
            'rate_limit' => 100, // Emails per hour
        ],

        // Microsoft 365/Outlook Configuration
        'outlook' => [
            'name' => 'Microsoft 365 SMTP',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('OUTLOOK_USERNAME'), // your-email@outlook.com
            'password' => env('OUTLOOK_PASSWORD'),
            'from_address' => env('OUTLOOK_FROM_ADDRESS'),
            'from_name' => env('OUTLOOK_FROM_NAME', 'Academic System'),
            'daily_limit' => 10000, // Office 365 daily sending limit
            'rate_limit' => 300, // Emails per hour
        ],

        // SendGrid Configuration
        'sendgrid' => [
            'name' => 'SendGrid SMTP',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => env('SENDGRID_API_KEY'),
            'from_address' => env('SENDGRID_FROM_ADDRESS'),
            'from_name' => env('SENDGRID_FROM_NAME', 'Academic System'),
            'daily_limit' => 40000, // SendGrid daily limit (varies by plan)
            'rate_limit' => 1000, // Emails per hour
        ],

        // Mailgun Configuration
        'mailgun' => [
            'name' => 'Mailgun SMTP',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('MAILGUN_USERNAME'),
            'password' => env('MAILGUN_PASSWORD'),
            'from_address' => env('MAILGUN_FROM_ADDRESS'),
            'from_name' => env('MAILGUN_FROM_NAME', 'Academic System'),
            'daily_limit' => 10000, // Mailgun daily limit (varies by plan)
            'rate_limit' => 300, // Emails per hour
        ],

        // Amazon SES Configuration
        'ses' => [
            'name' => 'Amazon SES SMTP',
            'host' => env('SES_HOST', 'email-smtp.us-east-1.amazonaws.com'),
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('SES_USERNAME'),
            'password' => env('SES_PASSWORD'),
            'from_address' => env('SES_FROM_ADDRESS'),
            'from_name' => env('SES_FROM_NAME', 'Academic System'),
            'daily_limit' => 200, // SES default daily limit (can be increased)
            'rate_limit' => 14, // SES default rate limit (can be increased)
        ],

        // Custom SMTP Server Configuration
        'custom' => [
            'name' => 'Custom SMTP Server',
            'host' => env('CUSTOM_SMTP_HOST'),
            'port' => env('CUSTOM_SMTP_PORT', 587),
            'encryption' => env('CUSTOM_SMTP_ENCRYPTION', 'tls'),
            'username' => env('CUSTOM_SMTP_USERNAME'),
            'password' => env('CUSTOM_SMTP_PASSWORD'),
            'from_address' => env('CUSTOM_SMTP_FROM_ADDRESS'),
            'from_name' => env('CUSTOM_SMTP_FROM_NAME', 'Academic System'),
            'daily_limit' => env('CUSTOM_SMTP_DAILY_LIMIT', 1000),
            'rate_limit' => env('CUSTOM_SMTP_RATE_LIMIT', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('EMAIL_QUEUE_CONNECTION', 'redis'),
        'queue' => env('EMAIL_QUEUE_NAME', 'emails'),
        'retry_after' => env('EMAIL_RETRY_AFTER', 300), // 5 minutes
        'max_tries' => env('EMAIL_MAX_TRIES', 3),
        'backoff' => [60, 300, 900], // 1min, 5min, 15min
        'timeout' => env('EMAIL_TIMEOUT', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Monitoring Configuration
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('EMAIL_MONITORING_ENABLED', true),
        'log_retention_days' => env('EMAIL_LOG_RETENTION_DAYS', 90),
        'alert_thresholds' => [
            'failure_rate' => env('EMAIL_ALERT_FAILURE_RATE', 10), // percentage
            'queue_backlog' => env('EMAIL_ALERT_QUEUE_BACKLOG', 1000), // number of emails
            'daily_limit_usage' => env('EMAIL_ALERT_DAILY_LIMIT_USAGE', 80), // percentage
        ],
        'alert_recipients' => explode(',', env('EMAIL_ALERT_RECIPIENTS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encryption_key' => env('EMAIL_ENCRYPTION_KEY', env('APP_KEY')),
        'allowed_domains' => explode(',', env('EMAIL_ALLOWED_DOMAINS', '')),
        'blocked_domains' => explode(',', env('EMAIL_BLOCKED_DOMAINS', '')),
        'rate_limiting' => [
            'enabled' => env('EMAIL_RATE_LIMITING_ENABLED', true),
            'max_per_minute' => env('EMAIL_MAX_PER_MINUTE', 60),
            'max_per_hour' => env('EMAIL_MAX_PER_HOUR', 1000),
            'max_per_day' => env('EMAIL_MAX_PER_DAY', 10000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Template Configuration
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'default_language' => env('EMAIL_DEFAULT_LANGUAGE', 'en'),
        'supported_languages' => explode(',', env('EMAIL_SUPPORTED_LANGUAGES', 'en,vi')),
        'cache_enabled' => env('EMAIL_TEMPLATE_CACHE_ENABLED', true),
        'cache_ttl' => env('EMAIL_TEMPLATE_CACHE_TTL', 3600), // 1 hour
        'versioning_enabled' => env('EMAIL_TEMPLATE_VERSIONING_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'academic_events' => [
            'course_registration' => env('NOTIFY_COURSE_REGISTRATION', true),
            'grade_published' => env('NOTIFY_GRADE_PUBLISHED', true),
            'academic_hold' => env('NOTIFY_ACADEMIC_HOLD', true),
            'enrollment_confirmed' => env('NOTIFY_ENROLLMENT_CONFIRMED', true),
            'assessment_deadline' => env('NOTIFY_ASSESSMENT_DEADLINE', true),
        ],
        'reminder_schedule' => [
            'assessment_deadline_days' => env('REMINDER_ASSESSMENT_DEADLINE_DAYS', '7,3,1'),
            'registration_deadline_days' => env('REMINDER_REGISTRATION_DEADLINE_DAYS', '14,7,3,1'),
            'grade_submission_days' => env('REMINDER_GRADE_SUBMISSION_DAYS', '5,2,1'),
        ],
    ],
];
