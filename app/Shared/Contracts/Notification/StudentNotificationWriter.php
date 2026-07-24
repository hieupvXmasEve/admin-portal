<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface StudentNotificationWriter
{
    public function markAsRead(int $recipientUserId, int $campusId, int $notificationId): bool;

    /** @param list<int> $notificationIds */
    public function markMultipleAsRead(int $recipientUserId, int $campusId, array $notificationIds): int;

    public function markAllAsRead(int $recipientUserId, int $campusId): int;

    public function archive(int $recipientUserId, int $campusId, int $notificationId): bool;
}
