<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\ProcessNotificationOutboxJob;
use App\Modules\Notification\Models\NotificationEventOutbox;

class RetryOutboxAction
{
    public function run(NotificationEventOutbox $outbox): bool
    {
        if (! in_array($outbox->status, [NotificationOutboxStatus::Failed, NotificationOutboxStatus::Pending], true)) {
            return false;
        }

        $outbox->update([
            'status' => NotificationOutboxStatus::Pending,
            'last_error' => null,
        ]);

        ProcessNotificationOutboxJob::dispatch();

        return true;
    }
}
