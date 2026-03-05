<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationDelivery;

class RetryDeliveryAction
{
    public function run(NotificationDelivery $delivery): bool
    {
        if (! in_array($delivery->status, [NotificationDeliveryStatus::Failed, NotificationDeliveryStatus::Pending], true)) {
            return false;
        }

        $delivery->update([
            'status' => NotificationDeliveryStatus::Pending,
            'last_error' => null,
        ]);

        SendNotificationDeliveryJob::dispatch($delivery->id);

        return true;
    }
}
