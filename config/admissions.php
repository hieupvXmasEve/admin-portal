<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CRM ingestion (server-to-server)
    |--------------------------------------------------------------------------
    |
    | Settings for the admissions-CRM ingestion path (ADR-0004). The CRM calls
    | the /api/v1 admissions endpoints with a scoped Sanctum token; these knobs
    | harden that surface.
    |
    */
    'ingest' => [
        // Dedicated service-account identity. The account is created on demand
        // when a token is minted and can never log into the web UI.
        'service_account' => [
            'email' => env('ADMISSIONS_INGEST_SERVICE_EMAIL', 'admissions-crm@service.swinx.local'),
            'name' => env('ADMISSIONS_INGEST_SERVICE_NAME', 'Admissions CRM (service)'),
        ],

        // Per-minute throttle for the ingestion group, keyed by caller.
        'rate_limit' => [
            'max_attempts' => (int) env('ADMISSIONS_INGEST_RATE_MAX', 120),
            'decay_minutes' => (int) env('ADMISSIONS_INGEST_RATE_DECAY', 1),
        ],

        // CRM source IPs. Comma-separated in env; an empty list allows any IP
        // (the default for local/dev/test, where no allowlist is configured).
        'ip_allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMISSIONS_INGEST_IP_ALLOWLIST', '')),
        ))),
    ],
];
