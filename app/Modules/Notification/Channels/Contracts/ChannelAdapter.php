<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels\Contracts;

use App\Modules\Notification\Models\NotificationDelivery;

interface ChannelAdapter
{
    /**
     * @return array{provider_message_id:?string,email_log_id:?int}
     */
    public function send(NotificationDelivery $delivery): array;
}
