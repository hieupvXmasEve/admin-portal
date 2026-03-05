<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Student;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Notification Service V2 - Uses NotificationMessage model instead of legacy Notifiable trait.
 */
class NotificationService
{
    /**
     * Get student's notifications from V2 notification_messages table.
     */
    public function getNotifications(Student $student, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($student);

        $this->applyNotificationFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 20;
        $perPage = min(max($perPage, 5), 100);

        return $query->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get student's unread notifications count.
     */
    public function getUnreadCount(Student $student): int
    {
        return $this->baseQuery($student)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get notification summary.
     */
    public function getNotificationSummary(Student $student): array
    {
        $baseQuery = $this->baseQuery($student);

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
    public function markAsRead(Student $student, int $notificationId): bool
    {
        $updated = $this->baseQuery($student)
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $updated > 0;
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(Student $student, array $notificationIds): int
    {
        return $this->baseQuery($student)
            ->whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Student $student): int
    {
        return $this->baseQuery($student)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Archive (soft-delete equivalent) notification.
     */
    public function deleteNotification(Student $student, int $notificationId): bool
    {
        $updated = $this->baseQuery($student)
            ->where('id', $notificationId)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return $updated > 0;
    }

    /**
     * Base query scoped to student's user_id and campus_id.
     */
    protected function baseQuery(Student $student): Builder
    {
        return NotificationMessage::query()
            ->where('recipient_user_id', $student->user_id)
            ->where('campus_id', $student->campus_id)
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
