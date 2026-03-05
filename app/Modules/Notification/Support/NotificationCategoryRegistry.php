<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

final class NotificationCategoryRegistry
{
    private const CATEGORIES = [
        'query' => [
            'label' => 'Query',
            'color' => 'blue',
            'variant' => 'outline',
        ],
        'finance' => [
            'label' => 'Finance',
            'color' => 'green',
            'variant' => 'outline',
        ],
        'academic' => [
            'label' => 'Academic',
            'color' => 'purple',
            'variant' => 'outline',
        ],
        'system' => [
            'label' => 'System',
            'color' => 'gray',
            'variant' => 'secondary',
        ],
        'urgent' => [
            'label' => 'Urgent',
            'color' => 'red',
            'variant' => 'destructive',
        ],
        'announcement' => [
            'label' => 'Announcement',
            'color' => 'yellow',
            'variant' => 'outline',
        ],
    ];

    /**
     * @return array{key: string, label: string, color: string, variant: string}|null
     */
    public function resolve(string $key): ?array
    {
        $category = self::CATEGORIES[$key] ?? null;
        if ($category === null) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $category['label'],
            'color' => $category['color'],
            'variant' => $category['variant'],
        ];
    }

    /**
     * @return array<string, array{label: string, color: string, variant: string}>
     */
    public function all(): array
    {
        return self::CATEGORIES;
    }

    /**
     * @return array<int, string>
     */
    public function availableKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }
}
