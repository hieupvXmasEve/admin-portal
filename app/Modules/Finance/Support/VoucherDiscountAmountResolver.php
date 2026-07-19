<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\VoucherDefinition;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;

class VoucherDiscountAmountResolver
{
    public function __construct(private readonly StudentChargeTimingResolver $studentChargeTimingResolver) {}

    /**
     * Resolve canonical voucher amounts for one student in one semester.
     *
     * @return array{base_amount: float|null, discount_amount: float}
     */
    public function resolveAmounts(VoucherDefinition $voucher, int|object $student, int $semesterId): array
    {
        $studentId = is_int($student) ? $student : (int) ($student->id ?? 0);
        if ($studentId <= 0) {
            throw new \InvalidArgumentException('Voucher pricing requires a student identifier.');
        }

        $baseAmount = $this->resolveBaseAmount(
            app(ProgramEnrollmentReader::class)->forStudentId($studentId),
            $semesterId,
        );

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

    private function resolveBaseAmount(ProgramEnrollmentSummary $enrollment, int $semesterId): ?float
    {
        if ($this->studentChargeTimingResolver->shouldGenerateTuitionForSemester($enrollment, $semesterId)) {
            return $this->getTuitionFee($enrollment, $semesterId);
        }

        if ($this->studentChargeTimingResolver->shouldGenerateEgcForSemester($enrollment, $semesterId)) {
            return $this->getEgcFee($enrollment);
        }

        return null;
    }

    private function getEgcFee(ProgramEnrollmentSummary $enrollment): ?float
    {
        $startLevel = $enrollment->egcCurrentLevel ?? 1;
        $totalLevels = $enrollment->egcTotalLevels ?? 6;

        $levelsToCharge = [$startLevel];

        if (($startLevel + 1) < $totalLevels) {
            $levelsToCharge[] = $startLevel + 1;
        }

        $amount = collect($levelsToCharge)
            ->sum(fn (int $level): float => app(EgcLevelFeeResolver::class)->resolve($level));

        return $amount > 0 ? $amount : null;
    }

    private function getTuitionFee(ProgramEnrollmentSummary $enrollment, int $semesterId): ?float
    {
        return $this->studentChargeTimingResolver->getTuitionTermData($enrollment, $semesterId)['amount'];
    }
}
