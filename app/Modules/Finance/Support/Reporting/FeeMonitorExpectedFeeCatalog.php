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
     * Fee source catalog for the Fee Monitor.
     *
     * `mandatory` declares the business rule: only mandatory fees may report a
     * "missing" row when no charge exists yet. Optional fees (admission, BHYT)
     * never report missing — they only surface once a charge already exists.
     * Add future mandatory fees here with `mandatory => true`.
     *
     * `missing_inference` is the effective switch the query honours:
     * mandatory AND not otherwise gated. Retake/resit stay mandatory but are
     * held off by the ACAD-RET-001 gate until that source contract is accepted.
     *
     * @return array<string, array{charge_type: string, label: string, mandatory: bool, missing_inference: bool}>
     */
    public static function sources(): array
    {
        return [
            self::SOURCE_TUITION_PLAN => [
                'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
                'label' => 'Học phí theo kế hoạch',
                'mandatory' => true,
                'missing_inference' => true,
            ],
            self::SOURCE_EGC_TUITION => [
                'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
                'label' => 'Phí EGC',
                'mandatory' => true,
                'missing_inference' => true,
            ],
            self::SOURCE_ADMISSION_ENROLLMENT => [
                'charge_type' => FinanceCharge::TYPE_ADMISSION_FEE,
                'label' => 'Lệ phí tuyển sinh / nhập học',
                'mandatory' => false,
                'missing_inference' => false,
            ],
            self::SOURCE_BHYT => [
                'charge_type' => FinanceCharge::TYPE_BHYT,
                'label' => 'BHYT',
                'mandatory' => false,
                'missing_inference' => false,
            ],
            self::SOURCE_COURSE_RETAKE => [
                'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                'label' => 'Phí học lại',
                'mandatory' => true,
                'missing_inference' => FeeMonitorAcadRetGate::missingInferenceEnabled(),
            ],
            self::SOURCE_EXAM_RESIT => [
                'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
                'label' => 'Phí thi lại',
                'mandatory' => true,
                'missing_inference' => FeeMonitorAcadRetGate::missingInferenceEnabled(),
            ],
        ];
    }

    /**
     * Whether a fee source is mandatory (i.e. may report a missing row).
     */
    public static function isMandatory(string $source): bool
    {
        return self::sources()[$source]['mandatory'] ?? false;
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
