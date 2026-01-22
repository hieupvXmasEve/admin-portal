<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Models\CourseRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceChargeService
{
    /**
     * Create a new finance charge.
     */
    public function createCharge(array $data): FinanceCharge
    {
        return FinanceCharge::create([
            'student_id' => $data['student_id'],
            'semester_id' => $data['semester_id'],
            'billing_cycle_id' => $data['billing_cycle_id'] ?? null,
            'charge_type' => $data['charge_type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'effective_at' => $data['effective_at'] ?? now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Void an existing charge.
     */
    public function voidCharge(int $chargeId, string $reason, ?int $userId = null): FinanceCharge
    {
        $charge = FinanceCharge::findOrFail($chargeId);
        
        $charge->update([
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $userId ?? auth()->id(),
            'void_reason' => $reason,
        ]);

        return $charge->fresh();
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
    public function generateRetakeCharge(CourseRegistration $registration): FinanceCharge
    {
        if (!$registration->is_retake) {
            throw new \InvalidArgumentException('Registration is not marked as retake');
        }

        $amount = $registration->retake_fee ?? 0;
        $courseName = $registration->courseOffering?->curriculumUnit?->unit?->name ?? 'Unknown Course';

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
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->orderBy('effective_at')->get();
    }

    /**
     * Calculate total charges (positive amounts) for a student.
     */
    public function getTotalCharges(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Calculate total credits (negative amounts) for a student.
     */
    public function getTotalCredits(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '<', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return abs((float) $query->sum('amount'));
    }

    /**
     * Get net amount (charges - credits) for a student.
     */
    public function getNetAmount(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return (float) $query->sum('amount');
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
}
