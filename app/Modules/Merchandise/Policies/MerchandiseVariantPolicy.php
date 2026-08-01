<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Policies;

use App\Models\MerchandiseVariant;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Campus-scoped authorization for merchandise variant mutation.
 *
 * A staff member may edit a variant or adjust its stock only when they hold
 * the matching permission AT THE VARIANT'S CAMPUS — not merely at their
 * currently-selected session campus (the route `can:` middleware is
 * session-scoped and is only a coarse first gate). Mirrors
 * App\Policies\StudentApplicationPolicy's record-from-campus resolution.
 */
class MerchandiseVariantPolicy
{
    public function __construct(private readonly CampusPermissionReader $permissionReader) {}

    public function update(User $user, MerchandiseVariant $variant): bool
    {
        return $this->hasPermissionAtVariantCampus($user, $variant, 'manage_merchandise_variant');
    }

    public function adjustStock(User $user, MerchandiseVariant $variant): bool
    {
        return $this->hasPermissionAtVariantCampus($user, $variant, 'adjust_merchandise_stock');
    }

    public function viewStockMovements(User $user, MerchandiseVariant $variant): bool
    {
        return $this->hasPermissionAtVariantCampus($user, $variant, 'view_merchandise_audit');
    }

    private function hasPermissionAtVariantCampus(User $user, MerchandiseVariant $variant, string $permission): bool
    {
        $campusId = $variant->campus_id;

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
