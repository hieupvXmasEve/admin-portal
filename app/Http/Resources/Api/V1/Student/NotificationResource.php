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
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'category' => [
                'key' => $this->category->value,
                'display' => $this->category->label(),
//                'icon' => $this->getCategoryIcon($this->category->value),
                'color' => $this->getCategoryColor($this->category->value),
            ],
//            'type' => $this->type,
//            'importance' => [
//                'level' => $this->is_important ? 'high' : 'normal',
//                'display' => $this->is_important ? 'Important' : 'Normal',
//                'color' => $this->is_important ? '#ef4444' : '#6b7280',
//            ],
            'is_read' => $this->is_read,
            'is_important' => $this->is_important,
//            'status' => [
//                'is_expired' => $this->is_expired,
//            ],
            'time_ago' => $this->created_at->diffForHumans(),
//            'timestamps' => [
//                'created_at' => $this->created_at->toISOString(),
//                'read_at' => $this->read_at?->toISOString(),
//                'expires_at' => $this->expires_at?->toISOString(),

//            ],
            'data' => $this->data ?? [],
//            'channels' => $this->channels ?? [],
//            'display' => [
//                'icon' => $this->getNotificationIcon($this->category->value, $this->type),
//                'color' => $this->is_important ? '#ef4444' : '#6b7280',
//                'badge' => $this->getNotificationBadge(),
//            ],
        ];
    }


    /**
     * Get notification badge information
     */
    protected function getNotificationBadge(): array
    {
        $badges = [];

        if ($this->is_important) {
            $badges[] = [
                'type' => 'important',
                'text' => 'Important',
                'color' => '#ef4444',
            ];
        }

        if (!$this->is_read) {
            $badges[] = [
                'type' => 'unread',
                'text' => 'New',
                'color' => '#3b82f6',
            ];
        }

        if ($this->is_expired) {
            $badges[] = [
                'type' => 'expired',
                'text' => 'Expired',
                'color' => '#6b7280',
            ];
        }

        // Add category-specific badges
        switch ($this->category->value) {
            case 'academic':
                if (isset($this->data['days_until_due'])) {
                    $days = $this->data['days_until_due'];
                    if ($days <= 1) {
                        $badges[] = [
                            'type' => 'deadline',
                            'text' => $days === 0 ? 'Due Today' : 'Due Tomorrow',
                            'color' => '#ef4444',
                        ];
                    }
                }
                break;

            case 'finance':
                $badges[] = [
                    'type' => 'finance',
                    'text' => 'Financial',
                    'color' => '#22c55e',
                ];
                break;
        }

        return $badges;
    }


    /**
     * Get category icon
     */
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
            default => 'bell',
        };
    }

    /**
     * Get category color
     */
    protected function getCategoryColor(string $category): string
    {
        return match ($category) {
            'academic' => '#06b6d4',   // Cyan
            'system' => '#6b7280',     // Gray
            'finance' => '#22c55e',    // Green
            'personal' => '#8b5cf6',   // Purple
            'event' => '#f59e0b',      // Amber
            'club' => '#3b82f6',       // Blue
            'administrative' => '#ef4444', // Red
            default => '#6b7280',      // Gray
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
            'payment_due' => 'credit-card',
            'system_update' => 'arrow-path',
            'event_reminder' => 'calendar',
            default => $this->getCategoryIcon($category),
        };
    }

}
