<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use Closure;
use Illuminate\Support\Facades\DB;

/** Serializes a money mutation for one payer and advances its settlement version. */
final class SettlementMutationGuard
{
    /** @var array<int, array{billing_account: BillingAccount, changed: bool}> */
    private static array $activeMutations = [];

    /** @template T @param Closure(BillingAccount): T $mutation @return T */
    public function handle(int $billingAccountId, Closure $mutation): mixed
    {
        return $this->withinGuard($billingAccountId, $mutation, true);
    }

    /**
     * Whether a guarded mutation for this billing account is already open on
     * the call stack. A provider call must never run while this is true — it
     * means the enclosing DB transaction (opened by whichever call is
     * outermost) has not committed yet.
     */
    public function isActive(int $billingAccountId): bool
    {
        return isset(self::$activeMutations[$billingAccountId]);
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
        if (isset(self::$activeMutations[$billingAccountId])) {
            if ($incrementByDefault) {
                self::$activeMutations[$billingAccountId]['changed'] = true;
            }

            $markChanged = static function () use ($billingAccountId): void {
                self::$activeMutations[$billingAccountId]['changed'] = true;
            };

            return $mutation(self::$activeMutations[$billingAccountId]['billing_account'], $markChanged);
        }

        return DB::transaction(function () use ($billingAccountId, $incrementByDefault, $mutation): mixed {
            $billingAccount = BillingAccount::query()->lockForUpdate()->findOrFail($billingAccountId);
            self::$activeMutations[$billingAccountId] = [
                'billing_account' => $billingAccount,
                'changed' => $incrementByDefault,
            ];
            $markChanged = static function () use ($billingAccountId): void {
                self::$activeMutations[$billingAccountId]['changed'] = true;
            };

            try {
                $result = $mutation($billingAccount, $markChanged);
                if (self::$activeMutations[$billingAccountId]['changed']) {
                    $billingAccount->increment('settlement_version');
                }

                return $result;
            } finally {
                unset(self::$activeMutations[$billingAccountId]);
            }
        });
    }
}
