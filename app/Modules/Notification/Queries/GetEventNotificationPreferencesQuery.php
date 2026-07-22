<?php

declare(strict_types=1);

namespace App\Modules\Notification\Queries;

use App\Models\User;
use App\Models\UserEmailPreference;
use App\Modules\Notification\Actions\UpdateEventNotificationPreferencesAction;

class GetEventNotificationPreferencesQuery
{
    /** @return array<string, array{enabled: bool, frequency: string, label: string}> */
    public function handle(User $user): array
    {
        $preferences = [];

        foreach (UpdateEventNotificationPreferencesAction::eventTypes() as $type) {
            $preference = UserEmailPreference::getUserPreference($user->id, $type);
            $preferences[$type] = [
                'enabled' => $preference?->is_enabled ?? true,
                'frequency' => $preference?->frequency ?? UserEmailPreference::FREQUENCY_IMMEDIATE,
                'label' => UserEmailPreference::getNotificationTypes()[$type] ?? $type,
            ];
        }

        return $preferences;
    }
}
