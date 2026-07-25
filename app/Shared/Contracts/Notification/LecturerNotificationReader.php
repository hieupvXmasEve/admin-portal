<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

use Illuminate\Pagination\LengthAwarePaginator;

interface LecturerNotificationReader
{
    /** @param array<string, mixed> $filters */
    public function listForRecipient(int $recipientUserId, int $campusId, array $filters = []): LengthAwarePaginator;

    public function unreadCount(int $recipientUserId, int $campusId): int;
}
