<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingAccount;
use RuntimeException;

final class RemoveEmptyBillingAccountAction
{
    /** @param array{student_id: int} $data */
    public static function run(array $data): void
    {
        $account = BillingAccount::query()
            ->where('student_id', $data['student_id'])
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            return;
        }

        if ($account->financeObligations()->exists()
            || $account->financeCreditEntitlements()->exists()
            || $account->financeDiscountEntitlements()->exists()) {
            throw new RuntimeException('This student already has financial activity and cannot be revoked.');
        }

        $account->delete();
    }
}
