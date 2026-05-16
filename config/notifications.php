<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Use DB-backed Email Templates
    |--------------------------------------------------------------------------
    |
    | When true, the 4 finance EmailContentRegistry keys resolve to
    | DbEmailContentProvider, which reads notification_email_templates rows
    | by (type_key, campus_id) and renders via HasTemplateRendering.
    |
    | When false, the registry falls back to the legacy hard-coded
    | App\Modules\Notification\EmailContent\Types\*EmailContent classes.
    |
    | Sunset: 30 days after P2 admin-editor ships, assuming zero rendering
    | failures, this flag + this config file + the legacy classes are
    | removed in a follow-up cleanup commit.
    |
    */
    'use_db_templates' => env('NOTIFICATIONS_USE_DB_TEMPLATES', true),
];
