<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use App\Modules\AI\Support\Tools\QueryMetricsExecutionContext;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use Illuminate\Support\Collection;

class FinanceFeeMonitorMetricResolver extends AbstractMetricResolver implements MetricResolver
{
    public function __construct(private readonly AiFinanceMetricReader $reader) {}

    public function resolve(array $validation, array $metric, QueryMetricsExecutionContext $context): array
    {
        $semesterId = (int) ($validation['normalized_filters']['semester_id'] ?? 0);
        $result = $this->reader->feeMonitor($semesterId, $this->sourceFilters($validation));
        $summary = $result['summary'] ?? [];
        $rows = $this->rowCollection($result['rows'] ?? []);

        return [
            'summary' => $summary,
            'groups' => $this->groups($rows, $validation['group_by'] ?? []),
            'source_references' => $this->sourceReferences($validation, $metric),
            'record_count' => (int) ($summary['total_count'] ?? $rows->count()),
            'freshness' => $this->freshness((string) ($metric['freshness_rule'] ?? 'computed_at_request_time')),
            'warnings' => [],
            'confidence' => ['level' => 'high', 'basis' => 'source_report_parity'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $groupBy
     * @return list<array<string, mixed>>
     */
    private function groups(Collection $rows, array $groupBy): array
    {
        $groups = [];

        foreach ($groupBy as $key) {
            $field = match ($key) {
                'expected_fee_type' => 'expected_source',
                'generation_state' => 'generation_state',
                'payment_state' => 'payment_state',
                'program' => 'program_code',
                default => null,
            };

            if ($field === null) {
                continue;
            }

            $items = $rows
                ->groupBy(fn (array $row) => (string) ($row[$field] ?? 'unknown'))
                ->map(fn (Collection $group, string $value) => [
                    'key' => $key,
                    'value' => $value,
                    'label' => $value,
                    'metrics' => [
                        'count' => $group->count(),
                        'amount' => round((float) $group->sum('amount'), 2),
                        'outstanding_amount' => round((float) $group->sum('outstanding_amount'), 2),
                    ],
                ])
                ->sortByDesc(fn (array $item) => $item['metrics']['count'])
                ->values()
                ->all();

            array_push($groups, ...$items);
        }

        return $groups;
    }
}
