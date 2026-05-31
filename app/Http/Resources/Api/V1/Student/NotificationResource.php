<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student Notification Resource V2.
 * Transforms NotificationMessage for API; includes category and compact UI hints for FE.
 *
 * @mixin NotificationMessage
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        // Handle category as either string or array (enriched object)
        $rawCategory = $data['category'] ?? 'system';
        $categoryKey = is_array($rawCategory) ? ($rawCategory['key'] ?? 'system') : (string) $rawCategory;

        $isImportant = (bool) ($data['is_important'] ?? false);
        $readAt = $this->read_at;
        $isRead = $readAt !== null;
        $expiresAt = $this->expires_at;
        $isExpired = $expiresAt !== null && $expiresAt->isPast();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->body,
            'category' => [
                'key' => $categoryKey,
                'display' => $this->getCategoryDisplay($categoryKey),
            ],
            'type_key' => $this->type_key,
            'event_name' => $this->event_name,
            'is_read' => $isRead,
            'is_important' => $isImportant,
            'time_ago' => $this->created_at?->diffForHumans(),
            'timestamps' => [
                'created_at' => $this->created_at?->toISOString(),
                'read_at' => $readAt?->toISOString(),
                'expires_at' => $expiresAt?->toISOString(),
            ],
            'data' => $data,
            'ui' => [
                'icon' => $this->getNotificationIcon($categoryKey, $this->type_key ?? ''),
                'color' => $isImportant ? '#ef4444' : $this->getCategoryColor($categoryKey),
                'badges' => $this->getNotificationBadges($isRead, $isImportant, $isExpired, $data),
            ],
        ];
    }

    protected function getCategoryDisplay(string $category): string
    {
        return match ($category) {
            'academic' => 'Academic',
            'system' => 'System',
            'finance' => 'Finance',
            'personal' => 'Personal',
            'event' => 'Event',
            'club' => 'Club',
            'administrative' => 'Administrative',
            'assessment' => 'Assessment',
            'grade' => 'Grade',
            'attendance' => 'Attendance',
            'enrollment' => 'Enrollment',
            'announcement' => 'Announcement',
            default => 'System',
        };
    }

    protected function getCategoryColor(string $category): string
    {
        return match ($category) {
            'academic' => '#06b6d4',
            'system' => '#6b7280',
            'finance' => '#22c55e',
            'personal' => '#8b5cf6',
            'event' => '#f59e0b',
            'club' => '#3b82f6',
            'administrative' => '#ef4444',
            'assessment' => '#06b6d4',
            'grade' => '#22c55e',
            'attendance' => '#8b5cf6',
            'enrollment' => '#3b82f6',
            'announcement' => '#f59e0b',
            default => '#6b7280',
        };
    }

    protected function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'academic' => 'book-open',
            'system' => 'cog-6-tooth',
            'finance' => 'banknotes',
            'personal' => 'user',
            'event' => 'calendar-days',
            'club' => 'user-group',
            'administrative' => 'building-office',
            'assessment' => 'clipboard-list',
            'grade' => 'star',
            'attendance' => 'check-square',
            'enrollment' => 'user-plus',
            'announcement' => 'megaphone',
            default => 'bell',
        };
    }

    /**
     * @return array<int, array{type: string, text: string, color: string}>
     */
    protected function getNotificationBadges(bool $isRead, bool $isImportant, bool $isExpired, array $data): array
    {
        $badges = [];

        if ($isImportant) {
            $badges[] = ['type' => 'important', 'text' => 'Important', 'color' => '#ef4444'];
        }
        if (! $isRead) {
            $badges[] = ['type' => 'unread', 'text' => 'New', 'color' => '#3b82f6'];
        }
        if ($isExpired) {
            $badges[] = ['type' => 'expired', 'text' => 'Expired', 'color' => '#6b7280'];
        }
        if (isset($data['days_until_due'])) {
            $days = (int) $data['days_until_due'];
            if ($days <= 1) {
                $badges[] = [
                    'type' => 'deadline',
                    'text' => $days === 0 ? 'Due Today' : 'Due Tomorrow',
                    'color' => '#ef4444',
                ];
            }
        }
        $catValue = $data['category'] ?? '';
        $catKey = is_array($catValue) ? ($catValue['key'] ?? '') : (string) $catValue;
        if ($catKey === 'finance') {
            $badges[] = ['type' => 'finance', 'text' => 'Financial', 'color' => '#22c55e'];
        }
        $warningType = (string) ($data['warning_type'] ?? '');
        if ($warningType === 'academic_standing_warning' || $warningType === 'attendance_early_warning') {
            $badges[] = ['type' => 'warning', 'text' => 'Warning', 'color' => '#f59e0b'];
        }
        if ($warningType === 'attendance_limit_exceeded') {
            $badges[] = ['type' => 'limit_exceeded', 'text' => 'Limit Exceeded', 'color' => '#ef4444'];
        }

        return $badges;
    }

    protected function getNotificationIcon(string $category, string $typeKey): string
    {
        return match ($typeKey) {
            'academic_standing_warning',
            'attendance_early_warning',
            'attendance_limit_exceeded' => 'alert-triangle',
            'deadline' => 'clock',
            'grade_release' => 'star',
            'payment_due' => 'credit-card',
            'system_update' => 'arrow-path',
            'event_reminder' => 'calendar',
            'manual_notification' => $this->getCategoryIcon($category),
            default => $this->getCategoryIcon($category),
        };
    }
}
