<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use InvalidArgumentException;

final class NotificationPayloadBuilder
{
    public function __construct(
        private NotificationTypeRegistry $typeRegistry,
        private NotificationUrlRegistry $urlRegistry,
        private NotificationCategoryRegistry $categoryRegistry
    ) {}

    /**
     * Build a standardized notification payload with resolved action_url and category.
     *
     * @param  array{
     *     title: string,
     *     body: string,
     *     action_type?: string,
     *     action_params?: array<string, mixed>,
     *     action_text?: string,
     *     category?: string,
     *     icon?: string,
     *     metadata?: array<string, mixed>
     * }  $data
     * @param  string  $platform  Platform for URL resolution (web, mobile)
     * @return array{
     *     title: string,
     *     body: string,
     *     action_type: ?string,
     *     action_params: array<string, mixed>,
     *     action_url: ?string,
     *     action_text: string,
     *     category: ?array{key: string, label: string, color: string, variant: string},
     *     icon: ?string,
     *     metadata: array<string, mixed>
     * }
     */
    public function build(string $typeKey, array $data, string $platform = 'web'): array
    {
        $this->validateRequiredFields($data);

        $typeDefaults = $this->typeRegistry->get($typeKey);

        $actionType = $data['action_type'] ?? $typeDefaults['action_type'] ?? null;
        $actionParams = $data['action_params'] ?? [];
        $actionText = $data['action_text'] ?? $typeDefaults['action_text'] ?? 'View Details';
        $categoryKey = $data['category'] ?? $typeDefaults['category'] ?? null;
        $icon = $data['icon'] ?? $typeDefaults['icon'] ?? null;

        // Resolve action_url from action_type + action_params
        $actionUrl = null;
        if ($actionType !== null && is_string($actionType)) {
            $actionUrl = $this->urlRegistry->resolve($actionType, $actionParams, $platform);
        }

        // Resolve category to full object
        $category = null;
        if ($categoryKey !== null && is_string($categoryKey)) {
            $category = $this->categoryRegistry->resolve($categoryKey);
        }

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'action_type' => $actionType,
            'action_params' => $actionParams,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
            'category' => $category,
            'icon' => $icon,
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    /**
     * Resolve action_url from action_type and action_params.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolveActionUrl(array $data, string $platform = 'web'): ?string
    {
        $actionType = $data['action_type'] ?? null;
        $actionParams = $data['action_params'] ?? [];

        if ($actionType === null || ! is_string($actionType)) {
            return null;
        }

        return $this->urlRegistry->resolve($actionType, $actionParams, $platform);
    }

    /**
     * Resolve category to full object with label, color, variant.
     *
     * @param  array<string, mixed>  $data
     * @return array{key: string, label: string, color: string, variant: string}|null
     */
    public function resolveCategory(array $data): ?array
    {
        $category = $data['category'] ?? null;

        if ($category === null) {
            return null;
        }

        // Already resolved to object
        if (is_array($category) && isset($category['key'])) {
            return $category;
        }

        // Resolve from string key
        if (is_string($category)) {
            return $this->categoryRegistry->resolve($category);
        }

        return null;
    }

    /**
     * Enrich data with resolved action_url and category object.
     * Handles both raw data (string category) and already-enriched data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrichForApi(array $data, string $platform = 'web'): array
    {
        $enriched = $data;

        // Only resolve action_url if not already present
        if (! isset($enriched['action_url'])) {
            $enriched['action_url'] = $this->resolveActionUrl($data, $platform);
        }

        // Resolve category (handles both string and already-resolved array)
        $enriched['category'] = $this->resolveCategory($data);

        return $enriched;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private function validateRequiredFields(array $data): void
    {
        if (! isset($data['title']) || ! is_string($data['title']) || $data['title'] === '') {
            throw new InvalidArgumentException('Notification payload requires a non-empty "title" field.');
        }

        if (! isset($data['body']) || ! is_string($data['body']) || $data['body'] === '') {
            throw new InvalidArgumentException('Notification payload requires a non-empty "body" field.');
        }
    }
}
