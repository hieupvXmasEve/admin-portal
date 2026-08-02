<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

final class NotificationTypeRegistry
{
    private const TYPES = [
        'query_submitted' => [
            'event_name' => 'query.ticket_submitted',
            'category' => 'query',
            'icon' => 'message-square-plus',
            'action_text' => 'View Query',
            'action_type' => 'query.admin_inbox',
            'channels' => ['email', 'realtime'],
        ],
        'query_reply_created' => [
            'event_name' => 'query.reply_created',
            'category' => 'query',
            'icon' => 'message-square-reply',
            'action_text' => 'View Reply',
            'action_type' => 'query.admin_inbox',
            'channels' => ['email', 'realtime'],
        ],
        'query_staff_reply' => [
            'event_name' => 'query.staff_reply_created',
            'category' => 'query',
            'icon' => 'message-square-reply',
            'action_text' => 'View Reply',
            'action_type' => 'query.student_detail',
            'channels' => ['email', 'realtime'],
        ],
        'query_assigned' => [
            'event_name' => 'query.assigned',
            'category' => 'query',
            'icon' => 'user-check',
            'action_text' => 'View Query',
            'action_type' => 'query.admin_inbox',
            'channels' => ['email', 'realtime'],
        ],
        'invoice_paid' => [
            'event_name' => 'finance.invoice_paid',
            'category' => 'finance',
            'icon' => 'receipt',
            'action_text' => 'View Invoice',
            'action_type' => 'finance.invoice',
            'channels' => ['email', 'realtime'],
        ],
        'enrollment_confirmed' => [
            'event_name' => 'academic.enrollment_confirmed',
            'category' => 'academic',
            'icon' => 'graduation-cap',
            'action_text' => 'View Enrollment',
            'action_type' => 'academic.enrollment',
            'channels' => ['email', 'realtime'],
        ],
        'manual_notification' => [
            'event_name' => 'manual.notification_sent',
            'category' => 'announcement',
            'icon' => 'bell',
            'action_text' => 'View Details',
            'action_type' => null,
            'channels' => ['email', 'realtime'],
        ],
        'dng_payment_pushed' => [
            'event_name' => 'finance.dng_payment_pushed',
            'category' => 'finance',
            'icon' => 'credit-card',
            'action_text' => 'Xem chi tiết',
            'action_type' => 'finance.dng_payment_request',
            'channels' => ['email', 'realtime'],
        ],
        // Merchandise redemption orders (Phase 3). Wired via
        // App\Modules\Merchandise\Support\RedemptionNotificationPublisher.
        // In-app only (realtime) — no email channel for this category.
        'redemption_order_submitted' => [
            'event_name' => 'merchandise.redemption_order_submitted',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_pending_review' => [
            'event_name' => 'merchandise.redemption_order_pending_review',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'Review Order',
            'action_type' => 'merchandise.redemption_order_review',
            'channels' => ['realtime'],
        ],
        'redemption_order_approved' => [
            'event_name' => 'merchandise.redemption_order_approved',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_rejected' => [
            'event_name' => 'merchandise.redemption_order_rejected',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_ready_for_collection' => [
            'event_name' => 'merchandise.redemption_order_ready_for_collection',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_shipped' => [
            'event_name' => 'merchandise.redemption_order_shipped',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_cancelled' => [
            'event_name' => 'merchandise.redemption_order_cancelled',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_collected' => [
            'event_name' => 'merchandise.redemption_order_collected',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_overdue' => [
            'event_name' => 'merchandise.redemption_order_overdue',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_cancellation_requested' => [
            'event_name' => 'merchandise.redemption_order_cancellation_requested',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'Review Request',
            'action_type' => 'merchandise.redemption_order_review',
            'channels' => ['realtime'],
        ],
        'redemption_order_cancellation_rejected' => [
            'event_name' => 'merchandise.redemption_order_cancellation_rejected',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        'redemption_order_deadline_extended' => [
            'event_name' => 'merchandise.redemption_order_deadline_extended',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Order',
            'action_type' => 'merchandise.order',
            'channels' => ['realtime'],
        ],
        // New catalog item published (Merchandise::store, status=active only).
        // Broadcast to every active student — see MerchandiseCatalogNotificationPublisher.
        'merchandise_catalog_item_published' => [
            'event_name' => 'merchandise.catalog_item_published',
            'category' => 'merchandise',
            'icon' => 'shopping-bag',
            'action_text' => 'View Item',
            'action_type' => 'merchandise.catalog_item',
            'channels' => ['realtime'],
        ],
    ];

    /**
     * @return array{event_name: string, category: string, icon: string, action_text: string, action_type: ?string, channels: array<int, string>}|null
     */
    public function get(string $typeKey): ?array
    {
        return self::TYPES[$typeKey] ?? null;
    }

    /**
     * @return array<string, array{event_name: string, category: string, icon: string, action_text: string, action_type: ?string, channels: array<int, string>}>
     */
    public function all(): array
    {
        return self::TYPES;
    }

    /**
     * @return array<int, string>
     */
    public function availableTypes(): array
    {
        return array_keys(self::TYPES);
    }

    public function getDefaultActionText(string $typeKey): string
    {
        return self::TYPES[$typeKey]['action_text'] ?? 'View Details';
    }

    public function getDefaultActionType(string $typeKey): ?string
    {
        return self::TYPES[$typeKey]['action_type'] ?? null;
    }

    public function getDefaultCategory(string $typeKey): ?string
    {
        return self::TYPES[$typeKey]['category'] ?? null;
    }

    public function getDefaultIcon(string $typeKey): ?string
    {
        return self::TYPES[$typeKey]['icon'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultChannels(string $typeKey): array
    {
        return self::TYPES[$typeKey]['channels'] ?? ['email', 'realtime'];
    }
}
