<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

class MetricResolverRegistry
{
    public function __construct(
        private readonly AcademicStatusMetricResolver $academicStatusResolver,
        private readonly FinanceCollectionMetricResolver $financeCollectionResolver,
        private readonly FinanceFeeMonitorMetricResolver $financeFeeMonitorResolver,
        private readonly FinanceDngLifecycleMetricResolver $financeDngLifecycleResolver,
    ) {}

    public function resolverFor(string $metric): ?MetricResolver
    {
        return match ($metric) {
            'academic_student_status_count', 'academic_defer_count' => $this->academicStatusResolver,
            'finance_collection_summary' => $this->financeCollectionResolver,
            'finance_fee_monitor_summary' => $this->financeFeeMonitorResolver,
            'finance_dng_lifecycle_attention' => $this->financeDngLifecycleResolver,
            default => null,
        };
    }
}
