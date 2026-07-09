<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;

/**
 * Resolve installment metadata for a DNG payment request that was pushed
 * from a split FinanceChargeInstallment. Returns null when the DNG is a
 * full-term push (no linked installment row), so callers can fall back to
 * the standard payment_reminder template.
 */
class DngInstallmentContextResolver
{
    /**
     * @return array{
     *   installment_no: int,
     *   installment_total: int,
     *   installment_amount_formatted: string,
     *   remaining_balance_formatted: string,
     * }|null
     */
    public function resolve(DngPaymentRequest $dngRequest): ?array
    {
        $installment = FinanceChargeInstallment::query()
            ->where('dng_payment_request_id', $dngRequest->id)
            ->first();

        if (! $installment) {
            return null;
        }

        $charge = FinanceCharge::query()->find($installment->finance_charge_id);
        if (! $charge) {
            return null;
        }

        $totalInstallments = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->count();

        $paidSum = (float) FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_PAID)
            ->sum('amount');

        $remaining = max(0.0, (float) $charge->amount - $paidSum);

        return [
            'installment_no' => (int) $installment->installment_no,
            'installment_total' => $totalInstallments,
            'installment_amount_formatted' => number_format((float) $installment->amount, 0, ',', '.'),
            'remaining_balance_formatted' => number_format($remaining, 0, ',', '.'),
        ];
    }
}
