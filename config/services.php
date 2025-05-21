<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'google' => [
        'client_id' => '345290200984-nlncugvfbd8bsrrt57surrbbq9o00an6.apps.googleusercontent.com',
        'client_secret' => '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',

        'redirect' => 'http://127.0.0.1:8000/auth/google/callback',
        'drive_folder_id_event' => env('GOOGLE_DRIVE_FOLDER_ID_EVENT'),
        'drive_folder_id_query' => env('GOOGLE_DRIVE_FOLDER_ID_QUERY'),
        'drive_folder_id_club' => env('GOOGLE_DRIVE_FOLDER_ID_CLUB'),
        'drive_folder_id_guidline' => env('GOOGLE_DRIVE_FOLDER_ID_GUIDLINE'),
        'drive_folder_id_qrcode' => env('GOOGLE_DRIVE_FOLDER_ID_QRCODE'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
