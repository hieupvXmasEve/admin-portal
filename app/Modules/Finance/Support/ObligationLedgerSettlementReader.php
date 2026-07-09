<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use Illuminate\Support\Facades\DB;

class ObligationLedgerSettlementReader implements ObligationSettlementReader
{
    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

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

        if ($obligation instanceof FinanceObligation) {
            return $this->settlementFromChargeScope(
                sourceSystem: $sourceSystem,
                sourceKind: $sourceKind,
                sourceRef: $sourceRef,
                obligationType: $obligationType,
                financeObligationId: (int) $obligation->id,
                chargeIds: FinanceCharge::query()
                    ->where('finance_obligation_id', $obligation->id)
                    ->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->pluck('id')
                    ->all(),
            );
        }

        // Transitional fallback: legacy retake/resit charges still keyed by morph source.
        $legacyChargeIds = $this->legacyChargeIds($sourceKind, $sourceRef, $obligationType);
        if ($legacyChargeIds === []) {
            return ObligationSettlementResult::missing($sourceSystem, $sourceKind, $sourceRef, $obligationType);
        }

        return $this->settlementFromChargeScope(
            sourceSystem: $sourceSystem,
            sourceKind: $sourceKind,
            sourceRef: $sourceRef,
            obligationType: $obligationType,
            financeObligationId: null,
            chargeIds: $legacyChargeIds,
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

    public function isSettledOrExternallyPaid(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): bool {
        if ($this->isSettled($sourceSystem, $sourceKind, $sourceRef, $obligationType)) {
            return true;
        }

        // Paid DNG before payment bridge counts for display/cancel UX only.
        return $this->hasPaidDngForChargeIds($this->chargeIdsForSource(
            $sourceSystem,
            $sourceKind,
            $sourceRef,
            $obligationType,
        ));
    }

    /**
     * @param  list<int>  $chargeIds
     */
    private function settlementFromChargeScope(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        ?int $financeObligationId,
        array $chargeIds,
    ): ObligationSettlementResult {
        $chargeIds = array_values(array_unique(array_filter(array_map('intval', $chargeIds))));

        if ($chargeIds === []) {
            return ObligationSettlementResult::missing($sourceSystem, $sourceKind, $sourceRef, $obligationType);
        }

        $lineIds = DB::table('invoice_lines as il')
            ->whereIn('il.charge_id', $chargeIds)
            ->where('il.status', 'active')
            ->pluck('il.id');

        $payable = (float) DB::table('invoice_lines as il')
            ->whereIn('il.charge_id', $chargeIds)
            ->where('il.status', 'active')
            ->selectRaw('COALESCE(SUM(CASE WHEN il.amount_snapshot > 0 THEN il.amount_snapshot ELSE 0 END), 0) as payable')
            ->value('payable');

        $paid = 0.0;
        $discount = 0.0;
        $credit = 0.0;

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

            $credit = (float) DB::table('credit_applications as ca')
                ->whereIn('ca.invoice_line_id', $lineIds)
                ->sum('ca.amount');
        }

        $payable = max(0.0, $payable);
        $paid = max(0.0, $paid);
        $discount = max(0.0, $discount);
        $credit = max(0.0, $credit);
        $netPayable = max(0.0, $payable - $discount);
        $outstanding = max(0.0, $netPayable - $paid - $credit);

        return new ObligationSettlementResult(
            source_system: $sourceSystem,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            obligation_type: $obligationType,
            finance_obligation_id: $financeObligationId,
            settlement_state: $this->deriveState($payable, $paid, $discount, $credit, $netPayable, $outstanding),
            payable: $payable,
            paid: $paid,
            discount: $discount,
            outstanding: $outstanding,
        );
    }

    /**
     * @return list<int>
     */
    private function chargeIdsForSource(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
    ): array {
        $obligation = FinanceObligation::query()
            ->where('source_system', $sourceSystem)
            ->where('source_kind', $sourceKind)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', $obligationType)
            ->first();

        if ($obligation instanceof FinanceObligation) {
            return FinanceCharge::query()
                ->where('finance_obligation_id', $obligation->id)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->legacyChargeIds($sourceKind, $sourceRef, $obligationType);
    }

    /**
     * @return list<int>
     */
    private function legacyChargeIds(string $sourceKind, string $sourceRef, string $obligationType): array
    {
        $sourceType = match ($sourceKind) {
            'course_retake_registration' => CourseRetakeRegistration::class,
            'exam_resit_attempt' => ExamResitAttempt::class,
            default => null,
        };

        if ($sourceType === null) {
            return [];
        }

        $sourceId = $this->parseLegacySourceId($sourceKind, $sourceRef);
        if ($sourceId === null) {
            return [];
        }

        return FinanceCharge::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('charge_type', $obligationType)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function parseLegacySourceId(string $sourceKind, string $sourceRef): ?int
    {
        $prefix = match ($sourceKind) {
            'course_retake_registration' => 'retake:',
            'exam_resit_attempt' => 'exam-resit:',
            default => null,
        };

        if ($prefix === null || ! str_starts_with($sourceRef, $prefix)) {
            return null;
        }

        $id = (int) substr($sourceRef, strlen($prefix));

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<int>  $chargeIds
     */
    private function hasPaidDngForChargeIds(array $chargeIds): bool
    {
        $chargeIds = array_values(array_unique(array_filter(array_map('intval', $chargeIds))));
        if ($chargeIds === []) {
            return false;
        }

        $direct = DngPaymentRequest::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereIn('status', self::PAID_DNG_STATUSES)
            ->exists();

        if ($direct) {
            return true;
        }

        return DngPaymentRequestCharge::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereHas('dngPaymentRequest', fn ($q) => $q->whereIn('status', self::PAID_DNG_STATUSES))
            ->exists();
    }

    private function deriveState(
        float $payable,
        float $paid,
        float $discount,
        float $credit,
        float $netPayable,
        float $outstanding,
    ): string {
        if ($paid > $netPayable) {
            return ObligationSettlementResult::STATE_OVERPAID;
        }

        // Fully reduced by discount and/or credit applications with no cash.
        if ($payable > 0.0 && $outstanding <= 0.0 && $paid <= 0.0 && ($discount + $credit) > 0.0) {
            return ObligationSettlementResult::STATE_SETTLED_BY_DISCOUNT_OR_CREDIT;
        }

        if ($payable > 0.0 && $outstanding <= 0.0 && $paid > 0.0) {
            return ObligationSettlementResult::STATE_PAID;
        }

        if ($paid > 0.0 || $credit > 0.0) {
            return ObligationSettlementResult::STATE_PARTIALLY_PAID;
        }

        return ObligationSettlementResult::STATE_UNPAID;
    }
}
