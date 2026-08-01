<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Models\RedemptionOrder;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
 * Covers 6 of the 7 planned notification types. The 7th — notifying staff
 * that a STUDENT requested a cancellation — is intentionally NOT wired here:
 * the order detail carries `shipping_address` (PII), and who should receive
 * that (which permission, campus-scoped how) is a product decision for a
 * Notification-module owner, not something to guess at. `pendingStaffReview`
 * below is the one staff-facing type that IS wired, since "who approves
 * orders at this campus" is unambiguous from RedemptionOrderPolicy's own
 * campus+permission model.
 */
class RedemptionNotificationPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly NotificationPayloadFactory $payloadFactory,
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

    /** Staff-facing: everyone with approve_redemption_order at the order's own campus. */
    private function pendingStaffReview(RedemptionOrder $order): void
    {
        $userIds = $this->staffWithPermissionAtCampus((int) $order->campus_id, 'approve_redemption_order');

        if ($userIds === []) {
            return;
        }

        $targets = array_map(fn (int $id) => ['type' => 'user', 'id' => $id], $userIds);

        $this->publish('redemption_order_pending_review', $order, $targets, [
            'title' => 'New redemption order to review',
            'body' => "Order {$order->code} is awaiting review.",
        ]);
    }

    private function toStudent(string $typeKey, RedemptionOrder $order, array $data): void
    {
        $this->publish($typeKey, $order, [['type' => 'student', 'id' => (int) $order->student_id]], $data);
    }

    /** @param  list<array{type:string,id:int}>  $targets */
    private function publish(string $typeKey, RedemptionOrder $order, array $targets, array $data): void
    {
        $payload = $this->payloadFactory->build($typeKey, array_merge($data, [
            'action_type' => 'merchandise.order',
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
                'channels' => ['email', 'realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }

    /** @return list<int> */
    private function staffWithPermissionAtCampus(int $campusId, string $permission): array
    {
        return DB::table('campus_user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('campus_user_roles.campus_id', $campusId)
            ->where('permissions.code', $permission)
            ->distinct()
            ->pluck('campus_user_roles.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
