<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Models\UserEmailPreference;

class NotificationPreferenceResolver
{
    public function allows(int $userId, string $typeKey, string $channel): bool
    {
        if ($channel !== 'email') {
            return true;
        }

        $preference = UserEmailPreference::getUserPreference($userId, $typeKey)
            ?? UserEmailPreference::getUserPreference($userId, UserEmailPreference::TYPE_ALL);

        return $preference?->canReceiveNotification() ?? true;
    }
}
