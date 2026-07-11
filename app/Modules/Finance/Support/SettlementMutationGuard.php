<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use Closure;
use Illuminate\Support\Facades\DB;

/** Serializes a money mutation for one payer and advances its settlement version. */
final class SettlementMutationGuard
{
    /** @template T @param Closure(BillingAccount): T $mutation @return T */
    public function handle(int $billingAccountId, Closure $mutation): mixed
    {
        return DB::transaction(function () use ($billingAccountId, $mutation): mixed {
            $billingAccount = BillingAccount::query()->lockForUpdate()->findOrFail($billingAccountId);
            $result = $mutation($billingAccount);
            $billingAccount->increment('settlement_version');

            return $result;
        });
    }
}
