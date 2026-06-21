<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use App\Modules\AI\Support\Tools\QueryMetricsExecutionContext;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use Illuminate\Support\Collection;

class AcademicStatusMetricResolver extends AbstractMetricResolver implements MetricResolver
{
    public function __construct(private readonly AiAcademicMetricReader $reader) {}

    public function resolve(array $validation, array $metric, QueryMetricsExecutionContext $context): array
    {
        $filters = $validation['normalized_filters'] ?? [];
        $semesterId = (int) $filters['semester_id'];
        $currentStatus = isset($filters['student_status']) ? (string) $filters['student_status'] : null;
        $rows = $this->reader->studentStatusBySemesterExport($semesterId, $context->campus?->id, $currentStatus);

        if (($validation['metric'] ?? null) === 'academic_defer_count') {
            $rows = $rows->filter(fn (array $row) => $this->isDeferred($row))->values();
        }

        $groupBy = $validation['group_by'] ?? [];

        return [
            'summary' => $this->summary($rows, (string) $validation['metric']),
            'groups' => $this->groups($rows, $groupBy),
            'source_references' => $this->sourceReferences($validation, $metric),
            'record_count' => $rows->count(),
            'freshness' => $this->freshness((string) ($metric['freshness_rule'] ?? 'computed_at_request_time')),
            'warnings' => [],
            'confidence' => ['level' => 'high', 'basis' => 'source_report_parity'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summary(Collection $rows, string $metric): array
    {
        if ($metric === 'academic_defer_count') {
            return [
                'student_count' => $rows->count(),
                'defer_count' => $rows->count(),
            ];
        }

        return ['student_count' => $rows->count()];
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
                'status' => 'status_at_selected_semester',
                'program' => 'program_name',
                'intake_semester' => 'intake_semester',
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
                    'metrics' => ['student_count' => $group->count()],
                ])
                ->sortBy('value')
                ->values()
                ->all();

            array_push($groups, ...$items);
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isDeferred(array $row): bool
    {
        return ($row['status_at_selected_semester'] ?? null) === 'deferred'
            || ($row['current_status'] ?? null) === 'deferred'
            || ($row['latest_action_type'] ?? null) === 'ACADEMIC_DEFER';
    }
}
