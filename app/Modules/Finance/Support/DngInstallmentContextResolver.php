<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\SettlementPositionReader;

/**
 * Resolve installment metadata for a DNG payment request that was pushed
 * from a split FinanceChargeInstallment. Returns null when the DNG is a
 * full-term push (no linked installment row), so callers can fall back to
 * the standard payment_reminder template.
 */
class DngInstallmentContextResolver
{
    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
    ) {}

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

        $lineIds = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $position = $this->settlementPositionReader->forPayableLines($lineIds);

        if (! $position->isValid() || $position->amounts === null) {
            return null;
        }

        return [
            'installment_no' => (int) $installment->installment_no,
            'installment_total' => $totalInstallments,
            'installment_amount_formatted' => number_format((float) $installment->amount, 0, ',', '.'),
            'remaining_balance_formatted' => number_format((float) $position->amounts->remaining->amount, 0, ',', '.'),
        ];
    }

    /**
     * @return array{amount: ?float, issue_codes: list<string>}
     */
    public function currentBalance(DngPaymentRequest $dngRequest): array
    {
        $lineIds = $dngRequest->reservationTargets()
            ->pluck('invoice_line_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($lineIds === []) {
            return [
                'amount' => null,
                'issue_codes' => ['settlement_position.missing_payable_line'],
            ];
        }

        $position = $this->settlementPositionReader->forPayableLines($lineIds);
        if (! $position->isValid() || $position->amounts === null) {
            return [
                'amount' => null,
                'issue_codes' => array_values(array_unique(array_map(
                    static fn ($issue): string => $issue->code,
                    $position->issues,
                ))),
            ];
        }

        return [
            'amount' => (float) $position->amounts->remaining->amount,
            'issue_codes' => [],
        ];
    }
}
