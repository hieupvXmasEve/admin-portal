<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Notification;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Get student's notifications
     */
    public function getNotifications(Student $student, array $filters = [])
    {
        $query = $student->notifications();

        // Apply filters
        $this->applyNotificationFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 20;
        $perPage = min(max($perPage, 5), 100); // Ensure between 5 and 100

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get student's unread notifications count
     */
    public function getUnreadCount(Student $student): int
    {
        return $student->notifications()->unread()->count();
    }

    /**
     * Get notification summary
     */
    public function getNotificationSummary(Student $student): array
    {
        $totalNotifications = $student->notifications()->count();
        $unreadNotifications = $this->getUnreadCount($student);
        $todayNotifications = $student->notifications()
            ->whereDate('created_at', today())
            ->count();

        $importantNotifications = $student->notifications()
            ->unread()
            ->where('is_important', true)
            ->count();

        return [
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'today_notifications' => $todayNotifications,
            'important_notifications' => $importantNotifications,
            'read_percentage' => $totalNotifications > 0
                ? round((($totalNotifications - $unreadNotifications) / $totalNotifications) * 100, 1)
                : 0,
        ];
    }


    /**
     * Mark notification as read
     */
    public function markAsRead(Student $student, int $notificationId): bool
    {
        $notification = $student->notifications()->findOrFail($notificationId);

        $updated = $notification->markAsRead();

        if ($updated) {
            $this->clearNotificationCache($student);
        }

        return $updated;
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Student $student, array $notificationIds): int
    {
        $updated = $student->notifications()
            ->whereIn('id', $notificationIds)
            ->unread()
            ->update([
                'read_at' => now(),
            ]);

        if ($updated > 0) {
            $this->clearNotificationCache($student);
        }

        return $updated;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Student $student): int
    {
        $updated = $student->notifications()
            ->unread()
            ->update([
                'read_at' => now(),
            ]);

        if ($updated > 0) {
            $this->clearNotificationCache($student);
        }

        return $updated;
    }

    /**
     * Delete notification (soft delete)
     */
    public function deleteNotification(Student $student, int $notificationId): bool
    {
        $notification = $student->notifications()->findOrFail($notificationId);
        $deleted = $notification->delete(); // This will be a soft delete

        if ($deleted) {
            $this->clearNotificationCache($student);
        }

        return $deleted;
    }


    /**
     * Create notification for student
     */
    public function createNotification(Student $student, array $data): Notification
    {
        $notification = $student->notifications()->create([
            'title' => $data['title'],
            'message' => $data['message'],
            'category' => $data['category'],
            'type' => $data['type'] ?? 'info',
            'priority' => $data['priority'] ?? 'medium',
            'data' => $data['data'] ?? [],
            'action_url' => $data['action_url'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->clearNotificationCache($student);

        // Send notification through enabled channels
        $this->sendNotificationThroughChannels($student, $notification);

        return $notification;
    }

    /**
     * Get upcoming deadlines and create notifications
     */
    public function generateUpcomingDeadlineNotifications(Student $student): int
    {
        $upcomingAssessments = $student->assessmentComponentDetailScores()
            ->whereNull('achieved_score')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->with(['courseOffering.unit'])
            ->get();

        $notificationsCreated = 0;

        foreach ($upcomingAssessments as $assessment) {
            $daysUntilDue = now()->diffInDays($assessment->due_date, false);

            // Create notification based on urgency
            if (in_array($daysUntilDue, [7, 3, 1])) {
                $urgency = match ($daysUntilDue) {
                    7 => 'low',
                    3 => 'medium',
                    1 => 'high',
                };

                $this->createNotification($student, [
                    'title' => 'Assessment Due Soon',
                    'message' => "{$assessment->assessmentComponentDetail->name} for {$assessment->courseOffering->unit->code} is due in {$daysUntilDue} day(s)",
                    'category' => 'assessment',
                    'type' => 'deadline',
                    'priority' => $urgency,
                    'data' => [
                        'assessment_id' => $assessment->id,
                        'course_code' => $assessment->courseOffering->unit->code,
                        'due_date' => $assessment->due_date->toDateString(),
                        'days_until_due' => $daysUntilDue,
                    ],
                    'action_url' => "/assessments/{$assessment->id}",
                ]);

                $notificationsCreated++;
            }
        }

        return $notificationsCreated;
    }

    /**
     * Generate attendance alert notifications
     */
    public function generateAttendanceAlertNotifications(Student $student): int
    {
        // This would integrate with the AttendanceService to check for low attendance
        // and create appropriate notifications
        return 0;
    }

    /**
     * Generate grade release notifications
     */
    public function generateGradeReleaseNotifications(Student $student): int
    {
        $recentGrades = $student->assessmentComponentDetailScores()
            ->whereNotNull('achieved_score')
            ->where('graded_at', '>=', now()->subDays(1))
            ->with(['courseOffering.unit'])
            ->get();

        $notificationsCreated = 0;

        foreach ($recentGrades as $grade) {
            $this->createNotification($student, [
                'title' => 'New Grade Available',
                'message' => "Your grade for {$grade->assessmentComponentDetail->name} in {$grade->courseOffering->unit->code} is now available",
                'category' => 'grade',
                'type' => 'grade_release',
                'priority' => 'medium',
                'data' => [
                    'assessment_id' => $grade->id,
                    'course_code' => $grade->courseOffering->unit->code,
                    'achieved_score' => $grade->achieved_score,
                    'max_score' => $grade->max_score,
                ],
                'action_url' => "/grades/assessment/{$grade->id}",
            ]);

            $notificationsCreated++;
        }

        return $notificationsCreated;
    }

    /**
     * Apply notification filters
     */
    protected function applyNotificationFilters($query, array $filters): void
    {
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_important'])) {
            $query->where('is_important', $filters['is_important']);
        }

        if (isset($filters['is_read'])) {
            if ($filters['is_read']) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Filter out expired notifications by default unless specifically requested
        if (!isset($filters['include_expired']) || !$filters['include_expired']) {
            $query->notExpired();
        }
    }




    /**
     * Send notification through enabled channels
     */
    protected function sendNotificationThroughChannels(Student $student, Notification $notification): void
    {
        // This would integrate with email, push notification, and SMS services
        // Implementation depends on your notification infrastructure
    }

    /**
     * Clear notification cache for student
     */
    protected function clearNotificationCache(Student $student): void
    {
        $pattern = "notifications:student:{$student->id}:*";

        // Clear all cached notification data for this student
        $keys = Cache::getRedis()->keys($pattern);
        if (! empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }
}
