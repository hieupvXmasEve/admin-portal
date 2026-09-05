<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Queries\GetStudentChargesQuery;
use App\Modules\Finance\Queries\GetStudentChargeSummaryQuery;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Finance charge read/void facade.
 *
 * Wave 7: debit/credit generation no longer lives here — callers use
 * FinanceIntakeContract. createEgcDeferCredits routes through credit intake.
 */
class FinanceChargeService
{
    public function __construct(
        protected VoidFinanceChargeAction $voidChargeAction,
        protected GetStudentChargesQuery $getStudentChargesQuery,
        protected GetStudentChargeSummaryQuery $getStudentChargeSummaryQuery,
        protected FinanceIntakeContract $intake,
    ) {}

    /**
     * Void an existing charge.
     */
    public function voidCharge(int $chargeId, string $reason, ?int $userId = null): array
    {
        return $this->voidChargeAction->handle($chargeId, $reason, $userId);
    }

    /**
     * @deprecated Wave 7 — use FinanceIntakeContract. Kept only to fail loudly.
     */
    public function createCharge(array $data): FinanceCharge
    {
        throw new RuntimeException(
            'Direct charge creation is retired. Use FinanceIntakeContract / CreateStaffDebitAction.'
        );
    }

    /**
     * @deprecated Wave 7 — use Batch Studio / SubmitTuitionTermDebitAction.
     */
    public function generateTuitionCharge(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Tuition Fee',
        ?int $billingCycleId = null
    ): FinanceCharge {
        throw new RuntimeException('generateTuitionCharge is retired. Use tuition_term intake.');
    }

    /**
     * @deprecated Wave 7 — use EGC batch intake.
     */
    public function generateEgcLevelCharge(
        int $studentId,
        int $semesterId,
        int $level,
        float $amount
    ): FinanceCharge {
        throw new RuntimeException('generateEgcLevelCharge is retired. Use egc_level_fee intake.');
    }

    /**
     * @deprecated Wave 7 — use CourseRetakeRegistration + Academic intake.
     */
    public function generateRetakeCharge(object $registration): ?FinanceCharge
    {
        throw new RuntimeException(
            'generateRetakeCharge is retired. Use course_retake_registration intake (CreateRetakeCourseRegistrationAction).'
        );
    }

    /**
     * @deprecated Wave 7 — use credit entitlement intake (defer_settlement).
     */
    public function generateDeferCredit(DeferCase $deferCase): ?FinanceCharge
    {
        throw new RuntimeException('generateDeferCredit is retired. Use FinanceCreditEntitlement intake.');
    }

    /**
     * @deprecated Wave 7 — use credit/discount entitlement intake.
     */
    public function generateScholarshipCredit(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Scholarship Credit',
        $source = null
    ): FinanceCharge {
        throw new RuntimeException('generateScholarshipCredit is retired. Use scholarship entitlement intake.');
    }

    /**
     * Get active charges for a student in a semester.
     */
    public function getStudentCharges(int $studentId, ?int $semesterId = null): Collection
    {
        return $this->getStudentChargesQuery->handle($studentId, $semesterId);
    }

    /**
     * Student charges API summary: total_charges, total_credits, net_amount.
     *
     * @return array{total_charges: float, total_credits: float, net_amount: float}
     */
    public function getChargeSummary(int $studentId, ?int $semesterId = null): array
    {
        return $this->getStudentChargeSummaryQuery->handle($studentId, $semesterId);
    }

    /**
     * Calculate total charges (positive amounts) for a student.
     */
    public function getTotalCharges(int $studentId, ?int $semesterId = null): float
    {
        return $this->getStudentChargeSummaryQuery->totalCharges($studentId, $semesterId);
    }

    /**
     * Calculate total credits for a student from entitlement carriers
     * (discount allocations + credit applications).
     */
    public function getTotalCredits(int $studentId, ?int $semesterId = null): float
    {
        return $this->getStudentChargeSummaryQuery->totalCredits($studentId, $semesterId);
    }

    /**
     * Get net amount (charges - credits) for a student.
     */
    public function getNetAmount(int $studentId, ?int $semesterId = null): float
    {
        return $this->getStudentChargeSummaryQuery->netAmount($studentId, $semesterId);
    }

    /**
     * Check if a student has been charged for a semester.
     */
    public function hasActiveCharges(int $studentId, int $semesterId): bool
    {
        return FinanceCharge::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->exists();
    }

    public function paidCashForSemester(int $studentId, int $semesterId): float
    {
        return (float) FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->get()
            ->sum(static fn (FinanceCharge $charge): float => (float) $charge->paid_amount);
    }

    /**
     * Active EGC level fee charges for Academic lifecycle form options (read DTOs only).
     *
     * @return list<array{id:int,semester_id:int|null,amount:float|string,description:string|null,effective_at:string|null,paid_amount:float,is_fully_paid:bool}>
     */
    public function listActiveEgcChargesForStudent(int $studentId, ?int $semesterId = null): array
    {
        return FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->orderBy('effective_at')
            ->get()
            ->map(fn (FinanceCharge $charge): array => [
                'id' => (int) $charge->id,
                'semester_id' => $charge->semester_id !== null ? (int) $charge->semester_id : null,
                'amount' => $charge->amount,
                'description' => $charge->description,
                'effective_at' => $charge->effective_at?->toDateString(),
                'paid_amount' => $charge->paid_amount,
                'is_fully_paid' => $charge->is_fully_paid,
            ])
            ->values()
            ->all();
    }

    /**
     * Validate an EGC charge may be preserved as a defer credit.
     *
     * @return string|null validation error message, or null when valid
     */
    public function validateEgcChargeForPreserve(int $chargeId, int $studentId, ?int $semesterId): ?string
    {
        $charge = FinanceCharge::query()->find($chargeId);
        if (! $charge) {
            return 'Selected EGC charge was not found.';
        }

        if ((int) $charge->student_id !== $studentId
            || $charge->charge_type !== FinanceCharge::TYPE_EGC_LEVEL_FEE
            || $charge->status !== FinanceCharge::STATUS_ACTIVE
        ) {
            return 'Selected EGC charge is not an active EGC level fee for this student.';
        }

        if ($semesterId !== null && (int) $charge->semester_id !== $semesterId) {
            return 'Selected EGC charge does not belong to the chosen semester.';
        }

        if (! $charge->is_fully_paid) {
            return 'EGC fee must be fully paid before preserve is allowed.';
        }

        $sourceRef = 'egc-defer-credit:'.$charge->id;

        $hasCredit = FinanceCreditEntitlement::query()
            ->where('source_system', 'finance')
            ->where('source_kind', 'defer_settlement')
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceEntitlementType::DeferCredit)
            ->exists();

        if ($hasCredit) {
            return 'Selected EGC level has already been preserved.';
        }

        return null;
    }

    /**
     * Create defer-credit entitlements for selected paid EGC level fees via intake.
     *
     * @param  list<int>  $chargeIds
     */
    public function createEgcDeferCredits(
        int $studentId,
        ?int $fromSemesterId,
        array $chargeIds,
        ?\DateTimeInterface $effectiveAt,
        int $userId,
    ): void {
        $chargeIds = array_values(array_unique(array_filter(array_map('intval', $chargeIds))));
        if ($chargeIds === []) {
            return;
        }

        $charges = FinanceCharge::query()
            ->whereIn('id', $chargeIds)
            ->where('student_id', $studentId)
            ->where('semester_id', $fromSemesterId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->get();

        foreach ($charges as $charge) {
            $sourceRef = 'egc-defer-credit:'.$charge->id;

            $exists = FinanceCreditEntitlement::query()
                ->where('source_system', 'finance')
                ->where('source_kind', 'defer_settlement')
                ->where('source_ref', $sourceRef)
                ->where('entitlement_type', FinanceEntitlementType::DeferCredit)
                ->exists();

            if ($exists) {
                continue;
            }

            $lineId = $charge->invoiceLines()
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id');

            $facts = [
                'student_id' => $studentId,
                'semester_id' => (int) $charge->semester_id,
                'amount' => abs((float) $charge->amount),
                'description' => 'EGC Defer Credit: '.($charge->description ?? 'EGC Level Fee'),
                'effective_at' => ($effectiveAt ?? now())->format('Y-m-d H:i:s'),
            ];

            if ($lineId !== null) {
                $facts['invoice_line_id'] = (int) $lineId;
            }

            $this->intake->requestCredit(new FinanceIntakeData(
                source_system: 'finance',
                source_kind: 'defer_settlement',
                source_ref: $sourceRef,
                financial_effect: FinancialEffect::Credit,
                obligation_type: FinanceEntitlementType::DeferCredit,
                facts: $facts,
            ));
        }
    }
}
