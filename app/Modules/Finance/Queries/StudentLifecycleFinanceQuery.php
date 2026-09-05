<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Models\DeferCaseItem;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Shared\Contracts\Finance\DTO\StudentLifecycleFinanceCharge;
use App\Shared\Contracts\Finance\DTO\StudentLifecyclePreservePreview;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;

final class StudentLifecycleFinanceQuery implements StudentLifecycleFinanceReader
{
    public function __construct(private readonly FinanceChargeService $financeCharges) {}

    public function preservePreview(
        int $studentId,
        int $semesterId,
        ?int $existingActionLogId = null,
    ): StudentLifecyclePreservePreview {
        if ($existingActionLogId !== null) {
            $deferCase = DeferCase::query()
                ->where('student_action_log_id', $existingActionLogId)
                ->first(['preserve_amount', 'fee_policy']);

            if ($deferCase !== null) {
                return new StudentLifecyclePreservePreview(
                    source: 'existing_defer_case',
                    preserveAmount: (float) ($deferCase->preserve_amount ?? 0),
                    feePolicy: $deferCase->fee_policy,
                );
            }
        }

        return new StudentLifecyclePreservePreview(
            source: 'estimated_from_active_charges',
            preserveAmount: $this->financeCharges->getTotalCharges($studentId, $semesterId),
            feePolicy: DeferCase::POLICY_PRESERVE,
        );
    }

    public function activeEgcCharges(int $studentId, ?int $semesterId = null): array
    {
        return FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->orderBy('effective_at')
            ->get()
            ->map(static fn (FinanceCharge $charge): StudentLifecycleFinanceCharge => new StudentLifecycleFinanceCharge(
                id: (int) $charge->id,
                semesterId: $charge->semester_id !== null ? (int) $charge->semester_id : null,
                amount: (float) $charge->amount,
                description: $charge->description,
                effectiveAt: $charge->effective_at?->toDateString(),
                paidAmount: $charge->paid_amount,
                isFullyPaid: $charge->is_fully_paid,
            ))
            ->values()
            ->all();
    }

    public function egcPreserveValidationError(int $chargeId, int $studentId, ?int $semesterId): ?string
    {
        return $this->financeCharges->validateEgcChargeForPreserve($chargeId, $studentId, $semesterId);
    }

    public function deferredCourseRegistrationIds(int $studentId, int $semesterId): array
    {
        return DeferCaseItem::query()
            ->whereHas('deferCase', static fn ($query) => $query
                ->where('student_id', $studentId)
                ->where('semester_id', $semesterId))
            ->orderBy('id')
            ->pluck('course_registration_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    public function paidCashForSemester(int $studentId, int $semesterId): float
    {
        return $this->financeCharges->paidCashForSemester($studentId, $semesterId);
    }
}
