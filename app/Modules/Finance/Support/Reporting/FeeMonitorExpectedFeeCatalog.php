<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Models\FinanceCharge;

final class FeeMonitorExpectedFeeCatalog
{
    public const SOURCE_TUITION_PLAN = 'tuition_plan';

    public const SOURCE_EGC_TUITION = 'egc_tuition';

    public const SOURCE_ADMISSION_ENROLLMENT = 'admission_enrollment';

    public const SOURCE_BHYT = 'bhyt_health_insurance';

    public const SOURCE_COURSE_RETAKE = 'course_retake';

    public const SOURCE_EXAM_RESIT = 'exam_resit';

    /**
     * @return array<string, array{charge_type: string, label: string, missing_inference: bool}>
     */
    public static function sources(): array
    {
        return [
            self::SOURCE_TUITION_PLAN => [
                'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
                'label' => 'Học phí theo kế hoạch',
                'missing_inference' => true,
            ],
            self::SOURCE_EGC_TUITION => [
                'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
                'label' => 'Phí EGC',
                'missing_inference' => true,
            ],
            self::SOURCE_ADMISSION_ENROLLMENT => [
                'charge_type' => FinanceCharge::TYPE_ADMISSION_FEE,
                'label' => 'Lệ phí tuyển sinh / nhập học',
                'missing_inference' => true,
            ],
            self::SOURCE_BHYT => [
                'charge_type' => FinanceCharge::TYPE_BHYT,
                'label' => 'BHYT',
                'missing_inference' => true,
            ],
            self::SOURCE_COURSE_RETAKE => [
                'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                'label' => 'Phí học lại',
                'missing_inference' => FeeMonitorAcadRetGate::missingInferenceEnabled(),
            ],
            self::SOURCE_EXAM_RESIT => [
                'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
                'label' => 'Phí thi lại',
                'missing_inference' => FeeMonitorAcadRetGate::missingInferenceEnabled(),
            ],
        ];
    }

    public static function chargeTypeForSource(string $source): ?string
    {
        return self::sources()[$source]['charge_type'] ?? null;
    }

    public static function sourceForChargeType(string $chargeType): ?string
    {
        foreach (self::sources() as $source => $meta) {
            if ($meta['charge_type'] === $chargeType) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function firstPassMissingInferenceSources(): array
    {
        return collect(self::sources())
            ->filter(fn (array $meta): bool => $meta['missing_inference'])
            ->keys()
            ->values()
            ->all();
    }
}
