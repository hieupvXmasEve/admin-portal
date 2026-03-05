<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use Illuminate\Support\Facades\Log;

class NotificationAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function unresolvedRecipient(array $context): void
    {
        Log::warning('notification.recipient_unresolved', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function deliveryFailed(array $context): void
    {
        Log::error('notification.delivery_failed', $context);
    }
}
