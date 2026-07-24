<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Models\UserEmailPreference;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesWriter;
use Illuminate\Support\Facades\DB;

final class UpdateEventNotificationPreferencesAction implements StudentEventNotificationPreferencesWriter
{
    /**
     * @param  array{user_id: int, preferences: array<string, array{enabled?: bool, frequency?: string}>}  $data
     */
    public static function run(array $data): void
    {
        app(self::class)->updateForUser($data['user_id'], $data['preferences']);
    }

    /** @param array<string, array{enabled?: bool, frequency?: string}> $preferences */
    public function updateForUser(int $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences): void {
            foreach (self::eventTypes() as $type) {
                if (! isset($preferences[$type])) {
                    continue;
                }

                UserEmailPreference::setUserPreference(
                    $userId,
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
