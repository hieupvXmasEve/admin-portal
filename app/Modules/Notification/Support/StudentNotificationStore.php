<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Http\Resources\StudentNotificationResource;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Contracts\Notification\StudentNotificationReader;
use App\Shared\Contracts\Notification\StudentNotificationWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Notification Service V2 - Uses NotificationMessage model instead of legacy Notifiable trait.
 */
final class StudentNotificationStore implements StudentNotificationReader, StudentNotificationWriter
{
    /**
     * Get student's notifications from V2 notification_messages table.
     */
    public function listForRecipient(int $recipientUserId, int $campusId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($recipientUserId, $campusId);

        $this->applyNotificationFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 20;
        $perPage = min(max($perPage, 5), 100);

        $notifications = $query->orderByDesc('created_at')->paginate($perPage);
        $notifications->getCollection()->transform(
            static fn (NotificationMessage $message): array => (new StudentNotificationResource($message))->resolve(),
        );

        return $notifications;
    }

    /**
     * Get student's unread notifications count.
     */
    public function unreadCount(int $recipientUserId, int $campusId): int
    {
        return $this->baseQuery($recipientUserId, $campusId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get notification summary.
     */
    public function summaryForRecipient(int $recipientUserId, int $campusId): array
    {
        $baseQuery = $this->baseQuery($recipientUserId, $campusId);

        $totalNotifications = (clone $baseQuery)->count();
        $unreadNotifications = (clone $baseQuery)->whereNull('read_at')->count();
        $todayNotifications = (clone $baseQuery)->whereDate('created_at', today())->count();

        return [
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'today_notifications' => $todayNotifications,
            'read_percentage' => $totalNotifications > 0
                ? round((($totalNotifications - $unreadNotifications) / $totalNotifications) * 100, 1)
                : 0,
        ];
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $recipientUserId, int $campusId, int $notificationId): bool
    {
        $updated = $this->baseQuery($recipientUserId, $campusId)
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $updated > 0;
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(int $recipientUserId, int $campusId, array $notificationIds): int
    {
        return $this->baseQuery($recipientUserId, $campusId)
            ->whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(int $recipientUserId, int $campusId): int
    {
        return $this->baseQuery($recipientUserId, $campusId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Archive (soft-delete equivalent) notification.
     */
    public function archive(int $recipientUserId, int $campusId, int $notificationId): bool
    {
        $updated = $this->baseQuery($recipientUserId, $campusId)
            ->where('id', $notificationId)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return $updated > 0;
    }

    /**
     * Base query scoped to student's user_id and campus_id.
     */
    protected function baseQuery(int $recipientUserId, int $campusId): Builder
    {
        return NotificationMessage::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('campus_id', $campusId)
            ->where('status', 'active')
            ->whereNull('archived_at');
    }

    /**
     * Apply notification filters.
     */
    protected function applyNotificationFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['type_key'])) {
            $query->where('type_key', $filters['type_key']);
        }

        if (isset($filters['is_read'])) {
            if ($filters['is_read']) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!isset($filters['include_expired']) || !$filters['include_expired']) {
            $query->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
        }
    }
}
