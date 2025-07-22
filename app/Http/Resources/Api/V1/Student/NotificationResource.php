<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'notifications' => $this->formatNotifications($this->resource['notifications']),
            'pagination' => $this->resource['pagination'],
            'summary' => $this->formatSummary($this->resource['summary']),
            'categories' => $this->formatCategories($this->resource['categories']),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format notifications
     */
    protected function formatNotifications(array $notifications): array
    {
        return collect($notifications)->map(function ($notification) {
            return [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'category' => [
                    'key' => $notification['category'],
                    'display' => $notification['category_display'],
                    'icon' => $notification['icon'],
                    'color' => $notification['color'],
                ],
                'type' => $notification['type'],
                'priority' => [
                    'level' => $notification['priority'],
                    'display' => $notification['priority_display'],
                    'color' => $notification['color'],
                ],
                'status' => [
                    'is_read' => $notification['is_read'],
                    'is_urgent' => $notification['is_urgent'],
                    'is_expired' => $notification['is_expired'],
                ],
                'timestamps' => [
                    'created_at' => $notification['created_at'],
                    'read_at' => $notification['read_at'],
                    'expires_at' => $notification['expires_at'],
                    'time_ago' => $notification['time_ago'],
                ],
                'action_url' => $notification['action_url'],
                'data' => $notification['data'],
                'display' => [
                    'icon' => $notification['icon'],
                    'color' => $notification['color'],
                    'badge' => $this->getNotificationBadge($notification),
                ],
            ];
        })->toArray();
    }

    /**
     * Format summary
     */
    protected function formatSummary(array $summary): array
    {
        return [
            'counts' => [
                'total' => $summary['total_notifications'],
                'unread' => $summary['unread_notifications'],
                'today' => $summary['today_notifications'],
                'urgent' => $summary['urgent_notifications'],
            ],
            'metrics' => [
                'read_percentage' => $summary['read_percentage'],
                'engagement_level' => $this->getEngagementLevel($summary['read_percentage']),
                'notification_frequency' => $this->getNotificationFrequency($summary['today_notifications']),
            ],
            'status_indicators' => [
                'has_unread' => $summary['unread_notifications'] > 0,
                'has_urgent' => $summary['urgent_notifications'] > 0,
                'needs_attention' => $summary['urgent_notifications'] > 0 || $summary['unread_notifications'] > 10,
            ],
        ];
    }

    /**
     * Format categories
     */
    protected function formatCategories(array $categories): array
    {
        return collect($categories)->map(function ($category) {
            return [
                'category' => $category['category'],
                'display' => $category['category_display'],
                'counts' => [
                    'total' => $category['total_count'],
                    'unread' => $category['unread_count'],
                ],
                'visual' => [
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'badge_count' => $category['unread_count'],
                ],
                'status' => [
                    'has_unread' => $category['unread_count'] > 0,
                    'activity_level' => $this->getCategoryActivityLevel($category['total_count']),
                ],
            ];
        })->toArray();
    }

    /**
     * Get notification badge information
     */
    protected function getNotificationBadge(array $notification): array
    {
        $badges = [];

        if ($notification['is_urgent']) {
            $badges[] = [
                'type' => 'urgent',
                'text' => 'Urgent',
                'color' => '#ef4444',
            ];
        }

        if (!$notification['is_read']) {
            $badges[] = [
                'type' => 'unread',
                'text' => 'New',
                'color' => '#3b82f6',
            ];
        }

        if ($notification['is_expired']) {
            $badges[] = [
                'type' => 'expired',
                'text' => 'Expired',
                'color' => '#6b7280',
            ];
        }

        // Add category-specific badges
        switch ($notification['category']) {
            case 'assessment':
                if (isset($notification['data']['days_until_due'])) {
                    $days = $notification['data']['days_until_due'];
                    if ($days <= 1) {
                        $badges[] = [
                            'type' => 'deadline',
                            'text' => $days === 0 ? 'Due Today' : 'Due Tomorrow',
                            'color' => '#ef4444',
                        ];
                    }
                }
                break;

            case 'grade':
                $badges[] = [
                    'type' => 'grade',
                    'text' => 'New Grade',
                    'color' => '#22c55e',
                ];
                break;
        }

        return $badges;
    }

    /**
     * Get engagement level based on read percentage
     */
    protected function getEngagementLevel(float $readPercentage): string
    {
        return match (true) {
            $readPercentage >= 90 => 'high',
            $readPercentage >= 70 => 'medium',
            $readPercentage >= 50 => 'low',
            default => 'very_low',
        };
    }

    /**
     * Get notification frequency level
     */
    protected function getNotificationFrequency(int $todayCount): string
    {
        return match (true) {
            $todayCount >= 10 => 'high',
            $todayCount >= 5 => 'medium',
            $todayCount >= 1 => 'low',
            default => 'none',
        };
    }

    /**
     * Get category activity level
     */
    protected function getCategoryActivityLevel(int $totalCount): string
    {
        return match (true) {
            $totalCount >= 20 => 'high',
            $totalCount >= 10 => 'medium',
            $totalCount >= 1 => 'low',
            default => 'none',
        };
    }
}
