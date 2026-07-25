<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface LecturerNotificationWriter
{
    public function markAsRead(int $recipientUserId, int $campusId, int $notificationId): bool;

    public function markAllAsRead(int $recipientUserId, int $campusId): int;
}
