<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use App\Modules\AI\Support\Tools\QueryMetricsExecutionContext;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;

class FinanceCollectionMetricResolver extends AbstractMetricResolver implements MetricResolver
{
    public function __construct(private readonly AiFinanceMetricReader $reader) {}

    public function resolve(array $validation, array $metric, QueryMetricsExecutionContext $context): array
    {
        $semesterId = (int) ($validation['normalized_filters']['semester_id'] ?? 0);
        $result = $this->reader->collectionProgress($semesterId, $this->sourceFilters($validation));
        $summary = $result['summary'] ?? [];

        return [
            'summary' => $summary,
            'groups' => $this->groups($result['breakdowns'] ?? [], $validation['group_by'] ?? []),
            'source_references' => $this->sourceReferences($validation, $metric),
            'record_count' => (int) ($summary['student_count'] ?? 0),
            'freshness' => $this->freshness((string) ($metric['freshness_rule'] ?? 'computed_at_request_time')),
            'warnings' => [],
            'confidence' => ['level' => 'high', 'basis' => 'source_report_parity'],
        ];
    }

    /**
     * @param  array<string, mixed>  $breakdowns
     * @param  list<string>  $groupBy
     * @return list<array<string, mixed>>
     */
    private function groups(array $breakdowns, array $groupBy): array
    {
        $groups = [];

        foreach ($groupBy as $key) {
            $breakdownKey = match ($key) {
                'program' => 'by_program',
                'fee_type' => 'by_fee_type',
                'balance_state' => 'by_balance_state',
                'aging_bucket' => 'by_aging_bucket',
                default => null,
            };

            if ($breakdownKey === null) {
                continue;
            }

            foreach (($breakdowns[$breakdownKey] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $groups[] = [
                    'key' => $key,
                    'value' => (string) ($item['key'] ?? ''),
                    'label' => (string) ($item['label'] ?? $item['key'] ?? ''),
                    'metrics' => $this->metricsFromBreakdownItem($item),
                ];
            }
        }

        return $groups;
    }
}
