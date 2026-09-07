<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\FinanceCharge;

/**
 * Student/parent labels for portal payloads. Staff screens keep internal keys.
 */
final class StudentFinanceLearnerVocabulary
{
    public static function chargeLabel(string $chargeType, ?string $description = null): string
    {
        return match ($chargeType) {
            FinanceCharge::TYPE_TUITION_TERM => 'Học phí kỳ',
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'Học phí chương trình dự bị',
            FinanceCharge::TYPE_RETAKE_FEE => 'Phí học lại môn',
            FinanceCharge::TYPE_EXAM_RESIT_FEE => 'Phí thi lại',
            FinanceCharge::TYPE_BHYT => 'Bảo hiểm y tế',
            FinanceCharge::TYPE_MANUAL_FEE,
            FinanceCharge::TYPE_ADMISSION_FEE,
            FinanceCharge::TYPE_ADJUSTMENT => self::otherCharge($description),
            default => self::otherCharge($description),
        };
    }

    /**
     * @return array{unapplied_cash: string, cash: string, credit: string}
     */
    public static function balanceTerms(): array
    {
        return [
            'unapplied_cash' => 'Số dư của bạn',
            'cash' => 'Bạn đã nộp',
            'credit' => 'Nhà trường đã giảm',
        ];
    }

    private static function otherCharge(?string $description): string
    {
        $description = trim((string) $description);

        return $description !== '' ? 'Khoản thu khác · '.$description : 'Khoản thu khác';
    }
}
