<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use Closure;
use Illuminate\Support\Facades\DB;

/** Serializes a money mutation for one payer and advances its settlement version. */
final class SettlementMutationGuard
{
    /** @var array<int, true> */
    private static array $activeBillingAccountIds = [];

    /** @template T @param Closure(BillingAccount): T $mutation @return T */
    public function handle(int $billingAccountId, Closure $mutation): mixed
    {
        return $this->withinGuard($billingAccountId, $mutation, true);
    }

    /**
     * Run a guarded operation that may be a no-op without advancing the
     * settlement version. The mutation must call $markChanged after it writes.
     *
     * @template T
     *
     * @param  Closure(BillingAccount, Closure(): void): T  $mutation
     * @return T
     */
    public function handleIfChanged(int $billingAccountId, Closure $mutation): mixed
    {
        return $this->withinGuard($billingAccountId, $mutation, false);
    }

    /** @template T @param Closure(BillingAccount): T $mutation @return T */
    private function withinGuard(int $billingAccountId, Closure $mutation, bool $incrementByDefault): mixed
    {
        if (isset(self::$activeBillingAccountIds[$billingAccountId])) {
            return $mutation(
                BillingAccount::query()->findOrFail($billingAccountId),
                static function (): void {},
            );
        }

        return DB::transaction(function () use ($billingAccountId, $incrementByDefault, $mutation): mixed {
            $billingAccount = BillingAccount::query()->lockForUpdate()->findOrFail($billingAccountId);
            $changed = $incrementByDefault;
            $markChanged = static function () use (&$changed): void {
                $changed = true;
            };

            self::$activeBillingAccountIds[$billingAccountId] = true;

            try {
                $result = $mutation($billingAccount, $markChanged);
                if ($changed) {
                    $billingAccount->increment('settlement_version');
                }

                return $result;
            } finally {
                unset(self::$activeBillingAccountIds[$billingAccountId]);
            }
        });
    }
}
