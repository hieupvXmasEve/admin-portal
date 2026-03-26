<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Models\Unit;
use App\Models\VoucherDefinition;

class VoucherDiscountAmountResolver
{
    public function __construct(private readonly StudentChargeTimingResolver $studentChargeTimingResolver) {}

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
        if ($this->studentChargeTimingResolver->shouldGenerateTuitionForSemester($student, $semesterId)) {
            return $this->getTuitionFee($student, $semesterId);
        }

        if ($this->studentChargeTimingResolver->shouldGenerateEgcForSemester($student, $semesterId)) {
            return $this->getEgcFee($student);
        }

        return null;
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
        return $this->studentChargeTimingResolver->getTuitionTermData($student, $semesterId)['amount'];
    }
}
