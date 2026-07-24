<?php

declare(strict_types=1);

namespace App\Modules\Notification\Queries;

use App\Models\UserEmailPreference;
use App\Modules\Notification\Actions\UpdateEventNotificationPreferencesAction;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesReader;

final class GetEventNotificationPreferencesQuery implements StudentEventNotificationPreferencesReader
{
    /** @return array<string, array{enabled: bool, frequency: string, label: string}> */
    public function forUser(int $userId): array
    {
        $preferences = [];

        foreach (UpdateEventNotificationPreferencesAction::eventTypes() as $type) {
            $preference = UserEmailPreference::getUserPreference($userId, $type);
            $preferences[$type] = [
                'enabled' => $preference?->is_enabled ?? true,
                'frequency' => $preference?->frequency ?? UserEmailPreference::FREQUENCY_IMMEDIATE,
                'label' => UserEmailPreference::getNotificationTypes()[$type] ?? $type,
            ];
        }

        return $preferences;
    }
}
