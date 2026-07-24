<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

use Illuminate\Pagination\LengthAwarePaginator;

interface StudentNotificationReader
{
    /** @param array<string, mixed> $filters */
    public function listForRecipient(int $recipientUserId, int $campusId, array $filters = []): LengthAwarePaginator;

    public function unreadCount(int $recipientUserId, int $campusId): int;

    /** @return array{total_notifications: int, unread_notifications: int, today_notifications: int, read_percentage: float|int} */
    public function summaryForRecipient(int $recipientUserId, int $campusId): array;
}
