<?php

return [
    'v2_enabled' => (bool) env('NOTIFICATION_V2_ENABLED', false),

    'read_mode' => env('NOTIFICATION_V2_READ_MODE', 'legacy'),
    'write_mode' => env('NOTIFICATION_V2_WRITE_MODE', 'v2'),

    'outbox' => [
        'push_enabled' => (bool) env('NOTIFICATION_OUTBOX_PUSH_ENABLED', true),
        'batch_size' => (int) env('NOTIFICATION_OUTBOX_BATCH_SIZE', 100),
        'max_attempts' => (int) env('NOTIFICATION_OUTBOX_MAX_ATTEMPTS', 8),
        'retry_seconds' => [10, 30, 60, 300, 900],
    ],

    'delivery' => [
        'max_attempts' => (int) env('NOTIFICATION_DELIVERY_MAX_ATTEMPTS', 5),
        'retry_seconds' => [10, 30, 60, 300, 900],
    ],

    'campus' => [
        'strict_isolation' => true,
        'global_event_allowlist' => [
            'system.security',
            'system.announcement.global',
            'finance.dng_payment_allocated',
        ],
    ],

    'channels' => [
        'allowed' => ['email', 'realtime'],
    ],
];
