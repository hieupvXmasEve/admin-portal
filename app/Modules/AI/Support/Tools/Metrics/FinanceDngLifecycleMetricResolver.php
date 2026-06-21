<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use App\Modules\AI\Support\Tools\QueryMetricsExecutionContext;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use Illuminate\Support\Collection;

class FinanceDngLifecycleMetricResolver extends AbstractMetricResolver implements MetricResolver
{
    public function __construct(private readonly AiFinanceMetricReader $reader) {}

    public function resolve(array $validation, array $metric, QueryMetricsExecutionContext $context): array
    {
        $filters = $this->sourceFilters($validation);
        $semesterId = isset($validation['normalized_filters']['semester_id'])
            ? (int) $validation['normalized_filters']['semester_id']
            : null;
        $result = $this->reader->dngLifecycle($semesterId, $filters);
        $summary = $result['summary'] ?? [];
        $rows = $this->rowCollection($result['rows'] ?? []);
        $warnings = ($result['meta']['truncated'] ?? false) === true ? ['source_result_truncated'] : [];

        return [
            'summary' => $summary,
            'groups' => $this->groups($rows, $result['breakdowns'] ?? [], $validation['group_by'] ?? []),
            'source_references' => $this->sourceReferences($validation, $metric),
            'record_count' => (int) ($summary['total_count'] ?? $rows->count()),
            'freshness' => $this->freshness((string) ($metric['freshness_rule'] ?? 'computed_at_request_time')),
            'warnings' => $warnings,
            'confidence' => ['level' => $warnings === [] ? 'high' : 'partial', 'basis' => 'source_report_parity'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $breakdowns
     * @param  list<string>  $groupBy
     * @return list<array<string, mixed>>
     */
    private function groups(Collection $rows, array $breakdowns, array $groupBy): array
    {
        $groups = [];

        foreach ($groupBy as $key) {
            if ($key === 'attention_bucket') {
                foreach (($breakdowns['by_attention_bucket'] ?? []) as $item) {
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

                continue;
            }

            $field = match ($key) {
                'flow_state' => 'flow_state',
                'fee_type' => 'fee_type',
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
