<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Gates admin write actions on FinanceCharge that aren't already covered by
 * legacy controllers. Permission keys live in config/permission.php under
 * `access.finances`. Registered via Gate::policy in FinanceServiceProvider.
 */
class FinanceChargePolicy
{
    use HandlesAuthorization;

    public function splitInstallment(User $user, FinanceCharge $charge): bool
    {
        // Only allow on ACTIVE charges (voided charges have no installment plan).
        if ($charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return false;
        }

        return $user->hasPermission('split_installment_finance_charges');
    }
}
