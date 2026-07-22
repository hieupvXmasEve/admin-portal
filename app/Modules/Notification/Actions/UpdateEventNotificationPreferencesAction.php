<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Models\User;
use App\Models\UserEmailPreference;
use Illuminate\Support\Facades\DB;

class UpdateEventNotificationPreferencesAction
{
    /**
     * @param  array<string, array{enabled?: bool, frequency?: string}>  $preferences
     */
    public static function run(User $user, array $preferences): void
    {
        DB::transaction(function () use ($user, $preferences): void {
            foreach (self::eventTypes() as $type) {
                if (! isset($preferences[$type])) {
                    continue;
                }

                UserEmailPreference::setUserPreference(
                    $user->id,
                    $type,
                    $preferences[$type]['enabled'] ?? true,
                    $preferences[$type]['frequency'] ?? UserEmailPreference::FREQUENCY_IMMEDIATE,
                );
            }
        });
    }

    /** @return array<int, string> */
    public static function eventTypes(): array
    {
        return [
            UserEmailPreference::TYPE_EVENT_PUBLICATION,
            UserEmailPreference::TYPE_EVENT_REGISTRATION,
            UserEmailPreference::TYPE_EVENT_CHECKIN,
            UserEmailPreference::TYPE_EVENT_GOLD_REWARD,
            UserEmailPreference::TYPE_EVENT_CANCELLATION,
            UserEmailPreference::TYPE_EVENT_UPDATE,
        ];
    }
}
