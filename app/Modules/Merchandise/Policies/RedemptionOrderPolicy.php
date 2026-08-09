<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Policies;

use App\Models\User;
use App\Modules\Merchandise\Models\RedemptionOrder;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Campus-scoped authorization for staff redemption order actions. Resolves
 * the campus from the order's SNAPSHOT campus_id (RT-13) — not the staff
 * member's currently-selected session campus, and not the student's live
 * campus (which may have changed since the order was placed). This is what
 * keeps an in-flight order inside the queue of the campus it was placed at,
 * even after the student transfers. Mirrors MerchandiseVariantPolicy.
 */
class RedemptionOrderPolicy
{
    public function __construct(private readonly CampusPermissionReader $permissionReader) {}

    public function view(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'view_redemption_order');
    }

    public function approve(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'approve_redemption_order');
    }

    /** Also covers set-collection-location/deadline and extend-deadline — the approval pipeline. */
    public function process(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'approve_redemption_order');
    }

    public function reject(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'reject_redemption_order');
    }

    /** Covers handle-cancellation and the staff-direct overdue cancel. */
    public function cancel(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'cancel_redemption_order');
    }

    /** Also covers marking an order pickup-overdue — the collection desk's job. */
    public function confirmCollection(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'confirm_redemption_collection');
    }

    public function markShipped(User $user, RedemptionOrder $order): bool
    {
        return $this->hasPermissionAtOrderCampus($user, $order, 'mark_redemption_shipped');
    }

    private function hasPermissionAtOrderCampus(User $user, RedemptionOrder $order, string $permission): bool
    {
        $campusId = $order->campus_id;

        if ($campusId === null) {
            return false;
        }

        return in_array(
            $permission,
            $this->permissionReader->permissionCodesForUserId((int) $user->id, (int) $campusId),
            true
        );
    }
}
