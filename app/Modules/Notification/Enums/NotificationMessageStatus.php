<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

enum NotificationMessageStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
