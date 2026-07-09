<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Modules\Finance\Support\ObligationType\ObligationTypeDefinition;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;

/**
 * Fee Monitor expected-fee catalog.
 *
 * Per-type mandatory / missing-inference / charge_type / label facts are owned
 * by ObligationTypeRegistry (ADR-0027). This class exposes the Fee Monitor
 * source-key API and applies the ACAD-RET gate at read time.
 */
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
     *
     * `missing_inference` is the effective switch the query honours:
     * mandatory AND not otherwise gated. Retake/resit stay mandatory but are
     * held off by the ACAD-RET-001 gate until that source contract is accepted.
     *
     * @return array<string, array{charge_type: string, label: string, mandatory: bool, missing_inference: bool}>
     */
    public static function sources(): array
    {
        $acadRetGateEnabled = FeeMonitorAcadRetGate::missingInferenceEnabled();
        $bySourceKey = [];

        foreach (ObligationTypeRegistry::feeMonitorTracked() as $definition) {
            $sourceKey = $definition->feeMonitorSourceKey;
            if ($sourceKey === null) {
                continue;
            }

            $bySourceKey[$sourceKey] = self::metaFromDefinition($definition, $acadRetGateEnabled);
        }

        // Stable staff-visible order (pre-registry catalog order).
        $orderedKeys = [
            self::SOURCE_TUITION_PLAN,
            self::SOURCE_EGC_TUITION,
            self::SOURCE_ADMISSION_ENROLLMENT,
            self::SOURCE_BHYT,
            self::SOURCE_COURSE_RETAKE,
            self::SOURCE_EXAM_RESIT,
        ];

        $sources = [];
        foreach ($orderedKeys as $key) {
            if (isset($bySourceKey[$key])) {
                $sources[$key] = $bySourceKey[$key];
                unset($bySourceKey[$key]);
            }
        }

        // Any future tracked sources append after the known set.
        foreach ($bySourceKey as $key => $meta) {
            $sources[$key] = $meta;
        }

        return $sources;
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

    /**
     * @return array{charge_type: string, label: string, mandatory: bool, missing_inference: bool}
     */
    private static function metaFromDefinition(ObligationTypeDefinition $definition, bool $acadRetGateEnabled): array
    {
        return [
            'charge_type' => $definition->type,
            'label' => $definition->label,
            'mandatory' => $definition->feeMonitorMandatory,
            'missing_inference' => $definition->resolveFeeMonitorMissingInference($acadRetGateEnabled),
        ];
    }
}
