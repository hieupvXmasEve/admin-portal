<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;

class ObligationLedgerSettlementReader implements ObligationSettlementReader
{
    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
    ) {}

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
            return $this->settlementFromPosition(
                sourceSystem: $sourceSystem,
                sourceKind: $sourceKind,
                sourceRef: $sourceRef,
                obligationType: $obligationType,
                financeObligationId: (int) $obligation->id,
                position: $this->settlementPositionReader->forFinanceObligation((int) $obligation->id),
            );
        }

        // Transitional fallback: legacy retake/resit charges still keyed by morph source.
        $legacyChargeIds = $this->legacyChargeIds($sourceKind, $sourceRef, $obligationType);
        if ($legacyChargeIds === []) {
            return ObligationSettlementResult::missing($sourceSystem, $sourceKind, $sourceRef, $obligationType);
        }

        $lineIds = InvoiceLine::query()
            ->whereIn('charge_id', $legacyChargeIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if ($lineIds === []) {
            return ObligationSettlementResult::invalid(
                $sourceSystem,
                $sourceKind,
                $sourceRef,
                $obligationType,
                null,
                [SettlementPositionIssue::MISSING_PAYABLE_LINE],
            );
        }

        return $this->settlementFromPosition(
            sourceSystem: $sourceSystem,
            sourceKind: $sourceKind,
            sourceRef: $sourceRef,
            obligationType: $obligationType,
            financeObligationId: null,
            position: $this->settlementPositionReader->forPayableLines($lineIds),
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

    private function settlementFromPosition(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        ?int $financeObligationId,
        SettlementPosition $position,
    ): ObligationSettlementResult {
        if (! $position->isValid() || $position->amounts === null) {
            return ObligationSettlementResult::invalid(
                $sourceSystem,
                $sourceKind,
                $sourceRef,
                $obligationType,
                $financeObligationId,
                array_values(array_unique(array_map(
                    static fn ($issue): string => $issue->code,
                    $position->issues,
                ))),
            );
        }

        $amounts = $position->amounts;

        return new ObligationSettlementResult(
            source_system: $sourceSystem,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            obligation_type: $obligationType,
            finance_obligation_id: $financeObligationId,
            settlement_state: $this->settlementState($position),
            payable: (float) $amounts->gross->amount,
            paid: (float) $amounts->cash->amount,
            discount: (float) $amounts->discount->amount,
            outstanding: (float) $amounts->remaining->amount,
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

    private function settlementState(SettlementPosition $position): string
    {
        return match ($position->settlement_state) {
            SettlementPosition::STATE_UNPAID => ObligationSettlementResult::STATE_UNPAID,
            SettlementPosition::STATE_PARTIALLY_SETTLED => ObligationSettlementResult::STATE_PARTIALLY_PAID,
            SettlementPosition::STATE_SETTLED_BY_CASH => ObligationSettlementResult::STATE_PAID,
            SettlementPosition::STATE_SETTLED_BY_REDUCTION => ObligationSettlementResult::STATE_SETTLED_BY_DISCOUNT_OR_CREDIT,
            default => ObligationSettlementResult::STATE_INVALID_SETTLEMENT_POSITION,
        };
    }
}
