<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\Unit;
use App\Models\VoucherDefinition;

class VoucherDiscountAmountResolver
{
    /**
     * Resolve canonical voucher amounts for one student in one semester.
     *
     * @return array{base_amount: float|null, discount_amount: float}
     */
    public function resolveAmounts(VoucherDefinition $voucher, Student $student, int $semesterId): array
    {
        $baseAmount = $this->resolveBaseAmount($student, $semesterId);

        if ($voucher->voucher_type !== 'discount') {
            return [
                'base_amount' => $baseAmount,
                'discount_amount' => 0.0,
            ];
        }

        $discountAmount = 0.0;

        if ($voucher->discount_type === 'fixed_amount') {
            $discountAmount = (float) ($voucher->discount_value ?? 0);
        } elseif ($voucher->discount_type === 'percentage' && $baseAmount !== null && $baseAmount > 0) {
            $discountAmount = ($baseAmount * (float) ($voucher->discount_value ?? 0)) / 100;

            if ($voucher->max_discount_amount !== null && $discountAmount > (float) $voucher->max_discount_amount) {
                $discountAmount = (float) $voucher->max_discount_amount;
            }
        }

        return [
            'base_amount' => $baseAmount,
            'discount_amount' => round(max(0, $discountAmount), 2),
        ];
    }

    private function resolveBaseAmount(Student $student, int $semesterId): ?float
    {
        return match ($student->status) {
            'intake_pre_uni_gc' => $this->getEgcFee($student),
            'intake_course' => $this->getTuitionFee($student, $semesterId),
            default => null,
        };
    }

    private function getEgcFee(Student $student): ?float
    {
        $startLevel = $student->gc_current_level ?? 1;
        $totalLevels = $student->gc_total_levels ?? 6;

        $levelsToCharge = [$startLevel];

        if (($startLevel + 1) < $totalLevels) {
            $levelsToCharge[] = $startLevel + 1;
        }

        $amount = 0.0;

        foreach ($levelsToCharge as $level) {
            $unit = Unit::query()
                ->where('unit_type', 'egc')
                ->where('level', $level)
                ->first();

            if ($unit) {
                $amount += (float) $unit->base_fee;
            }
        }

        return $amount > 0 ? $amount : null;
    }

    private function getTuitionFee(Student $student, int $semesterId): ?float
    {
        $intakeMajor = $student->intake_major;

        if (! $intakeMajor || $semesterId < $intakeMajor) {
            return null;
        }

        $intakeSemester = Semester::find($intakeMajor);
        $targetSemester = Semester::find($semesterId);

        if (! $intakeSemester || ! $targetSemester) {
            return null;
        }

        if ($targetSemester->start_date < $intakeSemester->start_date) {
            return null;
        }

        $termNumber = Semester::query()
            ->where('start_date', '>=', $intakeSemester->start_date)
            ->where('start_date', '<=', $targetSemester->start_date)
            ->count();

        $plan = TuitionPlan::query()
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->first();

        if (! $plan) {
            return null;
        }

        $term = TuitionPlanTerm::query()
            ->where('tuition_plan_id', $plan->id)
            ->where('term_number', $termNumber)
            ->first();

        return $term ? (float) $term->amount : null;
    }
}
