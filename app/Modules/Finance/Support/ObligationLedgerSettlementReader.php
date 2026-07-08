<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use Illuminate\Support\Facades\DB;

class ObligationLedgerSettlementReader implements ObligationSettlementReader
{
    public function getSettlement(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): ObligationSettlementResult {
        $obligation = FinanceObligation::query()
            ->where('source_system', $sourceSystem)
            ->where('source_kind', $sourceKind)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', $obligationType)
            ->first();

        if (! $obligation instanceof FinanceObligation) {
            return ObligationSettlementResult::missing($sourceSystem, $sourceKind, $sourceRef, $obligationType);
        }

        $lineIds = DB::table('invoice_lines as il')
            ->join('finance_charges as fc', 'fc.id', '=', 'il.charge_id')
            ->where('fc.finance_obligation_id', $obligation->id)
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->where('il.status', 'active')
            ->pluck('il.id');

        $payable = (float) DB::table('invoice_lines as il')
            ->join('finance_charges as fc', 'fc.id', '=', 'il.charge_id')
            ->where('fc.finance_obligation_id', $obligation->id)
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->where('il.status', 'active')
            ->selectRaw('COALESCE(SUM(CASE WHEN il.amount_snapshot > 0 THEN il.amount_snapshot ELSE 0 END), 0) as payable')
            ->value('payable');

        $paid = 0.0;
        $discount = 0.0;

        if ($lineIds->isNotEmpty()) {
            $paid = (float) DB::table('payment_applications as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->whereIn('pa.invoice_line_id', $lineIds)
                ->where('p.status', Payment::STATUS_COMPLETED)
                ->sum('pa.amount');

            $discount = (float) DB::table('discount_allocations as da')
                ->leftJoin('invoice_discounts as idc', 'idc.id', '=', 'da.invoice_discount_id')
                ->whereIn('da.invoice_line_id', $lineIds)
                ->where(function ($query): void {
                    $query->whereNull('idc.id')
                        ->orWhere('idc.status', '!=', 'reversed');
                })
                ->sum('da.amount');
        }

        $payable = max(0.0, $payable);
        $paid = max(0.0, $paid);
        $discount = max(0.0, $discount);
        $netPayable = max(0.0, $payable - $discount);
        $outstanding = max(0.0, $netPayable - $paid);

        return new ObligationSettlementResult(
            source_system: $sourceSystem,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            obligation_type: $obligationType,
            finance_obligation_id: (int) $obligation->id,
            settlement_state: $this->deriveState($payable, $paid, $discount, $netPayable, $outstanding),
            payable: $payable,
            paid: $paid,
            discount: $discount,
            outstanding: $outstanding,
        );
    }

    public function isSettled(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): bool {
        return $this->getSettlement($sourceSystem, $sourceKind, $sourceRef, $obligationType)->isSettled();
    }

    private function deriveState(
        float $payable,
        float $paid,
        float $discount,
        float $netPayable,
        float $outstanding,
    ): string {
        if ($paid > $netPayable) {
            return ObligationSettlementResult::STATE_OVERPAID;
        }

        if ($netPayable <= 0.0 && $discount >= $payable) {
            return ObligationSettlementResult::STATE_SETTLED_BY_DISCOUNT_OR_CREDIT;
        }

        if ($outstanding <= 0.0 && $paid > 0.0) {
            return ObligationSettlementResult::STATE_PAID;
        }

        if ($paid > 0.0) {
            return ObligationSettlementResult::STATE_PARTIALLY_PAID;
        }

        return ObligationSettlementResult::STATE_UNPAID;
    }
}
