<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface StudentEventNotificationPreferencesWriter
{
    /** @param array<string, array{enabled?: bool, frequency?: string}> $preferences */
    public function updateForUser(int $userId, array $preferences): void;
}
