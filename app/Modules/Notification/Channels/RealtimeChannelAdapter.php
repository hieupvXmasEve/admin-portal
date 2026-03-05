<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Channels\Contracts\ChannelAdapter;
use App\Modules\Notification\Events\NotificationDeliveryBroadcast;
use App\Modules\Notification\Models\NotificationDelivery;

class RealtimeChannelAdapter implements ChannelAdapter
{
    public function send(NotificationDelivery $delivery): array
    {
        $message = $delivery->message()->firstOrFail();
        broadcast(new NotificationDeliveryBroadcast($message));

        return [
            'provider_message_id' => null,
            'email_log_id' => null,
        ];
    }
}
