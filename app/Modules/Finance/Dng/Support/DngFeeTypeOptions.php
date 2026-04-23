<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

use App\Models\FinanceCharge;

final class DngFeeTypeOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        return [
            ['value' => 'HP', 'label' => 'HP: Học phí'],
            ['value' => 'BHYT', 'label' => 'BHYT: Bảo hiểm y tế'],
            ['value' => 'HL', 'label' => 'HL: Học lại'],
            ['value' => 'PRE', 'label' => 'PRE: Lệ phí xét tuyển'],
            ['value' => 'KHAC', 'label' => 'KHAC: Các loại phí đào tạo khác'],
            ['value' => 'THHB', 'label' => 'THHB: Thu hồi học bổng'],
            ['value' => 'GC', 'label' => 'GC: Học phí GC'],
            ['value' => 'F1', 'label' => 'F1: Phí giữ chỗ học bổng'],
            ['value' => 'PTL', 'label' => 'PTL: Phí Thi lại'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::all(), 'value');
    }

    /**
     * Map a FinanceCharge charge_type to a DNG fee_type.
     * Only positive billable charge types are explicitly mapped.
     * Credit types (scholarship_credit, defer_credit, etc.) are not mapped here —
     * callers must filter lines by amount_snapshot > 0 before calling this method.
     */
    public static function fromChargeType(string $chargeType): string
    {
        return match ($chargeType) {
            FinanceCharge::TYPE_TUITION_TERM,
            FinanceCharge::TYPE_COURSE_FEE => 'HP',
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'GC',
            FinanceCharge::TYPE_RETAKE_FEE => 'PTL',
            FinanceCharge::TYPE_MANUAL_FEE,
            FinanceCharge::TYPE_ADJUSTMENT => 'KHAC',
            default => 'KHAC',
        };
    }
}
