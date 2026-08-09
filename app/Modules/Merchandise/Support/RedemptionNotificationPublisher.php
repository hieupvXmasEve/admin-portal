<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\RedemptionOrder;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Fires redemption order lifecycle notifications through the Shared Contract
 * domain-event seam (RT-4) — this module never imports
 * App\Modules\Notification directly (DomainBoundaryArchitectureTest forbids
 * it). Every publish call goes through DomainEventPublisher::publishAfterCommit,
 * which itself no-ops unless `notification.v2_enabled` + a `dual`/`v2`/
 * `v2_only` write mode are configured, and only actually dispatches once the
 * OUTERMOST transaction commits — safe to call from inside
 * RedemptionService's locked transitions.
 *
 * Covers every redemption order lifecycle transition, in-app only
 * (`channels: ['realtime']` — no email, per product decision). Staff-facing
 * types (`pendingStaffReview`, `cancellationRequested`) target everyone with
 * the matching permission at the order's own campus_id, resolved through
 * CampusPermissionReader rather than a local query, so Identity stays the only
 * owner of campus_user_roles (RT-13, RedemptionOrderPolicy's own
 * campus+permission model) — their notification
 * body deliberately omits `shipping_address` (PII), staff see it on the
 * order detail page itself once they open it.
 */
class RedemptionNotificationPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly NotificationPayloadFactory $payloadFactory,
        private readonly CampusPermissionReader $permissions,
    ) {}

    public function orderSubmitted(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_submitted', $order, [
            'title' => 'Redemption order placed',
            'body' => "Your order {$order->code} has been placed and is awaiting review.",
        ]);

        $this->pendingStaffReview($order);
    }

    public function orderApproved(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_approved', $order, [
            'title' => 'Redemption order approved',
            'body' => "Your order {$order->code} has been approved.",
        ]);
    }

    public function orderRejected(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_rejected', $order, [
            'title' => 'Redemption order rejected',
            'body' => "Your order {$order->code} was rejected and your Gold has been refunded.",
        ]);
    }

    public function orderReadyForCollection(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_ready_for_collection', $order, [
            'title' => 'Ready for collection',
            'body' => "Your order {$order->code} is ready for pickup at {$order->collection_location}.",
        ]);
    }

    public function orderShipped(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_shipped', $order, [
            'title' => 'Redemption order shipped',
            'body' => "Your order {$order->code} has shipped.",
        ]);
    }

    public function orderCancelled(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_cancelled', $order, [
            'title' => 'Redemption order cancelled',
            'body' => "Your order {$order->code} was cancelled and your Gold has been refunded.",
        ]);
    }

    public function orderCollected(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_collected', $order, [
            'title' => 'Order collected',
            'body' => "Your order {$order->code} has been marked as collected. Enjoy!",
        ]);
    }

    public function orderOverdue(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_overdue', $order, [
            'title' => 'Pickup overdue',
            'body' => "Your order {$order->code} is now overdue for collection — please pick it up soon or it may be cancelled.",
        ]);
    }

    public function deadlineExtended(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_deadline_extended', $order, [
            'title' => 'Pickup deadline extended',
            'body' => "The collection deadline for your order {$order->code} has been extended.",
        ]);
    }

    public function cancellationRejected(RedemptionOrder $order): void
    {
        $this->toStudent('redemption_order_cancellation_rejected', $order, [
            'title' => 'Cancellation request rejected',
            'body' => "Your cancellation request for order {$order->code} was rejected — the order remains active.",
        ]);
    }

    /** Staff-facing: everyone with cancel_redemption_order at the order's own campus. */
    public function cancellationRequested(RedemptionOrder $order): void
    {
        $this->toStaff($order, 'cancel_redemption_order', 'redemption_order_cancellation_requested', [
            'title' => 'Cancellation requested',
            'body' => "Student requested cancellation of order {$order->code}.",
            'action_type' => 'merchandise.redemption_order_review',
        ]);
    }

    /** Staff-facing: everyone with approve_redemption_order at the order's own campus. */
    private function pendingStaffReview(RedemptionOrder $order): void
    {
        $this->toStaff($order, 'approve_redemption_order', 'redemption_order_pending_review', [
            'title' => 'New redemption order to review',
            'body' => "Order {$order->code} is awaiting review.",
            // Staff review this in the admin app (routes/web.php
            // redemption-orders.show), NOT the student portal deep-link
            // ('merchandise.order' below resolves into FE/student-nuxt).
            'action_type' => 'merchandise.redemption_order_review',
        ]);
    }

    private function toStaff(RedemptionOrder $order, string $permission, string $typeKey, array $data): void
    {
        $userIds = $this->permissions->userIdsWithPermissionAtCampus($permission, (int) $order->campus_id);

        if ($userIds === []) {
            return;
        }

        $targets = array_map(fn (int $id) => ['type' => 'user', 'id' => $id], $userIds);

        $this->publish($typeKey, $order, $targets, $data);
    }

    private function toStudent(string $typeKey, RedemptionOrder $order, array $data): void
    {
        $this->publish($typeKey, $order, [['type' => 'student', 'id' => (int) $order->student_id]], $data);
    }

    /** @param  list<array{type:string,id:int}>  $targets */
    private function publish(string $typeKey, RedemptionOrder $order, array $targets, array $data): void
    {
        $payload = $this->payloadFactory->build($typeKey, array_merge([
            'action_type' => 'merchandise.order',
        ], $data, [
            'action_params' => ['id' => $order->id],
        ]));

        $event = new DomainEvent(
            // Deterministic — each transition can only ever happen once per
            // order (the state guard in RedemptionService::transition()
            // prevents re-entry), so this key is stable and safe to dedupe on.
            name: 'merchandise.'.$typeKey,
            deduplicationKey: $typeKey.':'.$order->id,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'redemption_order',
            aggregateId: (string) $order->id,
            campusId: (int) $order->campus_id,
            actorUserId: Auth::id(),
            payload: [
                'type_key' => $typeKey,
                'recipient_targets' => $targets,
                // In-app only — no email channel for this category (product
                // decision). NotificationTypeRegistry's per-type `channels`
                // key is NOT read by this pipeline; EventIntentMapper reads
                // this DomainEvent payload field directly.
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }
}
