<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

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
        ];
    }
}
