<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class AbstractMetricResolver
{
    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $metric
     * @return list<array<string, mixed>>
     */
    protected function sourceReferences(array $validation, array $metric): array
    {
        return [
            [
                'source_report' => (string) ($validation['source_report'] ?? $metric['source_report'] ?? ''),
                'source_reference_policy' => (string) ($validation['source_reference_policy'] ?? $metric['source_reference_policy'] ?? ''),
                'freshness' => (string) ($metric['freshness_rule'] ?? 'computed_at_request_time'),
                'permission_scope' => (string) ($metric['required_domain_permission'] ?? $metric['required_permission'] ?? ''),
                'source_reader' => (string) ($metric['source_reader'] ?? ''),
                'catalog_version' => (string) $validation['catalog_version'],
                'tool_schema_version' => (string) $validation['tool_schema_version'],
                'metric' => (string) $validation['metric'],
                'filters' => $validation['normalized_filters'] ?? [],
                'group_by' => $validation['group_by'] ?? [],
                'campus_scope_snapshot' => $validation['campus_scope_snapshot'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validation
     * @return array<string, mixed>
     */
    protected function sourceFilters(array $validation): array
    {
        $filters = $validation['normalized_filters'] ?? [];
        unset($filters['semester_id']);

        $filters['per_page'] = (int) ($validation['max_record_limit'] ?? 100);
        $filters['page'] = 1;

        return $filters;
    }

    /**
     * @return array<string, string>
     */
    protected function freshness(string $rule): array
    {
        return [
            'computed_at' => now()->toIso8601String(),
            'rule' => $rule,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $excluded
     * @return array<string, mixed>
     */
    protected function metricsFromBreakdownItem(array $item, array $excluded = ['key', 'label']): array
    {
        return collect($item)
            ->reject(fn (mixed $value, string $key) => in_array($key, $excluded, true))
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function rowCollection(mixed $rows): Collection
    {
        if ($rows instanceof LengthAwarePaginator) {
            return collect($rows->items());
        }

        if ($rows instanceof Collection) {
            return $rows->values();
        }

        if (is_array($rows)) {
            return collect($rows)->values();
        }

        return collect();
    }
}
