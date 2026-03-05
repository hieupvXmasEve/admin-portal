<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

enum NotificationDeliveryChannel: string
{
    case Email = 'email';
    case Realtime = 'realtime';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
