<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

enum NotificationOutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Dispatched = 'dispatched';
    case Failed = 'failed';
}
