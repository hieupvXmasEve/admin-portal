<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Student;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Get student's notifications
     */
    public function getNotifications(Student $student, array $filters = []): array
    {
        $cacheKey = "notifications:student:{$student->id}:" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($student, $filters) {
            $query = $student->notifications()->with(['notificationType']);

            // Apply filters
            $this->applyNotificationFilters($query, $filters);

            $notifications = $query->orderBy('created_at', 'desc')
                                  ->paginate($filters['per_page'] ?? 20);

            return [
                'notifications' => $this->formatNotifications($notifications->items()),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
                'summary' => $this->getNotificationSummary($student),
                'categories' => $this->getNotificationCategories($student),
            ];
        });
    }

    /**
     * Get notification summary
     */
    public function getNotificationSummary(Student $student): array
    {
        $totalNotifications = $student->notifications()->count();
        $unreadNotifications = $student->notifications()->where('is_read', false)->count();
        $todayNotifications = $student->notifications()
            ->whereDate('created_at', today())
            ->count();

        $urgentNotifications = $student->notifications()
            ->where('is_read', false)
            ->where('priority', 'high')
            ->count();

        return [
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'today_notifications' => $todayNotifications,
            'urgent_notifications' => $urgentNotifications,
            'read_percentage' => $totalNotifications > 0 
                ? round((($totalNotifications - $unreadNotifications) / $totalNotifications) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get notification categories with counts
     */
    public function getNotificationCategories(Student $student): array
    {
        $categories = $student->notifications()
            ->selectRaw('category, COUNT(*) as count, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count')
            ->groupBy('category')
            ->get();

        return $categories->map(function ($category) {
            return [
                'category' => $category->category,
                'category_display' => $this->getCategoryDisplay($category->category),
                'total_count' => $category->count,
                'unread_count' => $category->unread_count,
                'icon' => $this->getCategoryIcon($category->category),
                'color' => $this->getCategoryColor($category->category),
            ];
        })->toArray();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Student $student, int $notificationId): bool
    {
        $notification = $student->notifications()->findOrFail($notificationId);
        
        $updated = $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

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
            ->where('is_read', false)
            ->update([
                'is_read' => true,
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
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if ($updated > 0) {
            $this->clearNotificationCache($student);
        }

        return $updated;
    }

    /**
     * Delete notification
     */
    public function deleteNotification(Student $student, int $notificationId): bool
    {
        $notification = $student->notifications()->findOrFail($notificationId);
        $deleted = $notification->delete();

        if ($deleted) {
            $this->clearNotificationCache($student);
        }

        return $deleted;
    }

    /**
     * Get notification preferences
     */
    public function getNotificationPreferences(Student $student): array
    {
        $preferences = NotificationPreference::where('student_id', $student->id)->get();
        
        $defaultPreferences = $this->getDefaultPreferences();
        
        // Merge with existing preferences
        foreach ($preferences as $preference) {
            $key = $preference->notification_type . '.' . $preference->channel;
            if (isset($defaultPreferences[$preference->notification_type][$preference->channel])) {
                $defaultPreferences[$preference->notification_type][$preference->channel]['enabled'] = $preference->is_enabled;
            }
        }

        return [
            'preferences' => $defaultPreferences,
            'global_settings' => $this->getGlobalSettings($student),
        ];
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(Student $student, array $preferences): bool
    {
        return DB::transaction(function () use ($student, $preferences) {
            foreach ($preferences as $notificationType => $channels) {
                foreach ($channels as $channel => $settings) {
                    NotificationPreference::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'notification_type' => $notificationType,
                            'channel' => $channel,
                        ],
                        [
                            'is_enabled' => $settings['enabled'] ?? false,
                            'settings' => $settings['settings'] ?? [],
                        ]
                    );
                }
            }

            $this->clearNotificationCache($student);
            return true;
        });
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
            ->with(['courseOffering.curriculumUnit.unit'])
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
                    'message' => "{$assessment->assessmentComponentDetail->name} for {$assessment->courseOffering->curriculumUnit->unit->code} is due in {$daysUntilDue} day(s)",
                    'category' => 'assessment',
                    'type' => 'deadline',
                    'priority' => $urgency,
                    'data' => [
                        'assessment_id' => $assessment->id,
                        'course_code' => $assessment->courseOffering->curriculumUnit->unit->code,
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
            ->with(['courseOffering.curriculumUnit.unit'])
            ->get();

        $notificationsCreated = 0;

        foreach ($recentGrades as $grade) {
            $this->createNotification($student, [
                'title' => 'New Grade Available',
                'message' => "Your grade for {$grade->assessmentComponentDetail->name} in {$grade->courseOffering->curriculumUnit->unit->code} is now available",
                'category' => 'grade',
                'type' => 'grade_release',
                'priority' => 'medium',
                'data' => [
                    'assessment_id' => $grade->id,
                    'course_code' => $grade->courseOffering->curriculumUnit->unit->code,
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
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Format notifications for response
     */
    protected function formatNotifications(array $notifications): array
    {
        return collect($notifications)->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'category' => $notification->category,
                'category_display' => $this->getCategoryDisplay($notification->category),
                'type' => $notification->type,
                'priority' => $notification->priority,
                'priority_display' => $this->getPriorityDisplay($notification->priority),
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at->toISOString(),
                'read_at' => $notification->read_at?->toISOString(),
                'expires_at' => $notification->expires_at?->toISOString(),
                'action_url' => $notification->action_url,
                'data' => $notification->data,
                'time_ago' => $notification->created_at->diffForHumans(),
                'is_urgent' => $notification->priority === 'high',
                'is_expired' => $notification->expires_at && $notification->expires_at->isPast(),
                'icon' => $this->getNotificationIcon($notification->category, $notification->type),
                'color' => $this->getNotificationColor($notification->priority),
            ];
        })->toArray();
    }

    /**
     * Get category display name
     */
    protected function getCategoryDisplay(string $category): string
    {
        return match ($category) {
            'assessment' => 'Assessments',
            'grade' => 'Grades',
            'attendance' => 'Attendance',
            'enrollment' => 'Enrollment',
            'academic' => 'Academic',
            'system' => 'System',
            'announcement' => 'Announcements',
            default => ucfirst($category),
        };
    }

    /**
     * Get category icon
     */
    protected function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'assessment' => 'clipboard-document-list',
            'grade' => 'academic-cap',
            'attendance' => 'user-check',
            'enrollment' => 'user-plus',
            'academic' => 'book-open',
            'system' => 'cog-6-tooth',
            'announcement' => 'megaphone',
            default => 'bell',
        };
    }

    /**
     * Get category color
     */
    protected function getCategoryColor(string $category): string
    {
        return match ($category) {
            'assessment' => '#f59e0b', // Amber
            'grade' => '#22c55e',      // Green
            'attendance' => '#3b82f6', // Blue
            'enrollment' => '#8b5cf6', // Purple
            'academic' => '#06b6d4',   // Cyan
            'system' => '#6b7280',     // Gray
            'announcement' => '#ef4444', // Red
            default => '#6b7280',      // Gray
        };
    }

    /**
     * Get priority display name
     */
    protected function getPriorityDisplay(string $priority): string
    {
        return match ($priority) {
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent',
            default => ucfirst($priority),
        };
    }

    /**
     * Get notification icon
     */
    protected function getNotificationIcon(string $category, string $type): string
    {
        return match ($type) {
            'deadline' => 'clock',
            'grade_release' => 'star',
            'attendance_alert' => 'exclamation-triangle',
            'enrollment_reminder' => 'calendar',
            'system_update' => 'arrow-path',
            default => $this->getCategoryIcon($category),
        };
    }

    /**
     * Get notification color based on priority
     */
    protected function getNotificationColor(string $priority): string
    {
        return match ($priority) {
            'low' => '#6b7280',    // Gray
            'medium' => '#3b82f6', // Blue
            'high' => '#f59e0b',   // Amber
            'urgent' => '#ef4444', // Red
            default => '#6b7280',  // Gray
        };
    }

    /**
     * Get default notification preferences
     */
    protected function getDefaultPreferences(): array
    {
        return [
            'assessment' => [
                'email' => ['enabled' => true, 'settings' => []],
                'push' => ['enabled' => true, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
            'grade' => [
                'email' => ['enabled' => true, 'settings' => []],
                'push' => ['enabled' => true, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
            'attendance' => [
                'email' => ['enabled' => true, 'settings' => []],
                'push' => ['enabled' => true, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
            'enrollment' => [
                'email' => ['enabled' => true, 'settings' => []],
                'push' => ['enabled' => false, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
            'academic' => [
                'email' => ['enabled' => true, 'settings' => []],
                'push' => ['enabled' => false, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
            'system' => [
                'email' => ['enabled' => false, 'settings' => []],
                'push' => ['enabled' => true, 'settings' => []],
                'sms' => ['enabled' => false, 'settings' => []],
            ],
        ];
    }

    /**
     * Get global notification settings
     */
    protected function getGlobalSettings(Student $student): array
    {
        return [
            'email_notifications' => true,
            'push_notifications' => true,
            'sms_notifications' => false,
            'quiet_hours' => [
                'enabled' => false,
                'start_time' => '22:00',
                'end_time' => '08:00',
            ],
            'digest_frequency' => 'daily', // daily, weekly, never
        ];
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
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }
}
