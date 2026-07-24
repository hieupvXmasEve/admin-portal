<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface StudentEventNotificationPreferencesReader
{
    /** @return array<string, array{enabled: bool, frequency: string, label: string}> */
    public function forUser(int $userId): array;
}
