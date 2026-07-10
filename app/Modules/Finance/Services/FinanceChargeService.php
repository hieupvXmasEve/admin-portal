<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\GetStudentChargesQuery;
use App\Modules\Finance\Queries\GetStudentChargeSummaryQuery;
use Illuminate\Support\Collection;

class FinanceChargeService
{
    public function __construct(
        protected CreateFinanceChargeAction $createChargeAction,
        protected VoidFinanceChargeAction $voidChargeAction,
        protected GetStudentChargesQuery $getStudentChargesQuery,
        protected GetStudentChargeSummaryQuery $getStudentChargeSummaryQuery,
        protected DeferChargeResolver $deferChargeResolver
    ) {}

    /**
     * Create a new finance charge and assign it to an invoice.
     */
    public function createCharge(array $data): FinanceCharge
    {
        return $this->createChargeAction->handle($data);
    }

    /**
     * Void an existing charge.
     */
    public function voidCharge(int $chargeId, string $reason, ?int $userId = null): array
    {
        return $this->voidChargeAction->handle($chargeId, $reason, $userId);
    }

    /**
     * Generate tuition charges for a student in a semester.
     */
    public function generateTuitionCharge(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Tuition Fee',
        ?int $billingCycleId = null
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'billing_cycle_id' => $billingCycleId,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => $amount,
            'description' => $description,
            'effective_at' => now(),
        ]);
    }

    /**
     * Generate EGC level fee charge.
     */
    public function generateEgcLevelCharge(
        int $studentId,
        int $semesterId,
        int $level,
        float $amount
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => $amount,
            'description' => "EGC Level {$level} Fee",
            'effective_at' => now(),
        ]);
    }

    /**
     * Generate retake fee charge from course registration.
     */
    public function generateRetakeCharge(CourseRegistration $registration): ?FinanceCharge
    {
        if (! $registration->is_retake) {
            throw new \InvalidArgumentException('Registration is not marked as retake');
        }

        $deferItem = $this->deferChargeResolver->findApplicableCourseItem($registration);

        if ($deferItem) {
            $this->deferChargeResolver->markItemApplied($deferItem, $registration->semester_id);

            return null;
        }

        $amount = $registration->retake_fee ?? 0;
        $courseName = $registration->courseOffering?->unit?->name ?? 'Unknown Course';

        return $this->createCharge([
            'student_id' => $registration->student_id,
            'semester_id' => $registration->semester_id,
            'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
            'amount' => $amount,
            'description' => "Retake Fee: {$courseName}",
            'effective_at' => now(),
            'source_type' => CourseRegistration::class,
            'source_id' => $registration->id,
        ]);
    }

    /**
     * Generate defer credit charge from a defer case.
     */
    public function generateDeferCredit(DeferCase $deferCase): ?FinanceCharge
    {
        // Only create credit for PRESERVE or PARTIAL policies
        if ($deferCase->fee_policy === DeferCase::POLICY_FORFEIT) {
            return null;
        }

        $amount = $deferCase->preserve_amount ?? 0;
        if ($amount <= 0) {
            return null;
        }

        return $this->createCharge([
            'student_id' => $deferCase->student_id,
            'semester_id' => $deferCase->semester_id,
            'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
            'amount' => -abs($amount), // Negative for credit
            'description' => "Defer Credit ({$deferCase->fee_policy})",
            'effective_at' => $deferCase->effective_at,
            'source_type' => DeferCase::class,
            'source_id' => $deferCase->id,
            'created_by_user_id' => $deferCase->changed_by_user_id,
        ]);
    }

    /**
     * Generate scholarship credit.
     */
    public function generateScholarshipCredit(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Scholarship Credit',
        $source = null
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
            'amount' => -abs($amount), // Negative for credit
            'description' => $description,
            'effective_at' => now(),
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->id,
        ]);
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
     * (discount allocations + credit applications + legacy backstop).
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

        $hasCredit = FinanceCharge::query()
            ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
            ->where('source_type', FinanceCharge::class)
            ->where('source_id', $charge->id)
            ->exists();

        if ($hasCredit) {
            return 'Selected EGC level has already been preserved.';
        }

        return null;
    }

    /**
     * Create defer-credit charges for selected paid EGC level fees.
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
            $exists = FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                ->where('source_type', FinanceCharge::class)
                ->where('source_id', $charge->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->createCharge([
                'student_id' => $studentId,
                'semester_id' => $charge->semester_id,
                'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
                'amount' => -abs((float) $charge->amount),
                'description' => 'EGC Defer Credit: '.($charge->description ?? 'EGC Level Fee'),
                'effective_at' => $effectiveAt ?? now(),
                'source_type' => FinanceCharge::class,
                'source_id' => $charge->id,
                'created_by_user_id' => $userId,
            ]);
        }
    }
}
