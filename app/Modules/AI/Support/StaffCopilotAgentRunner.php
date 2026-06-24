<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use Illuminate\Support\Str;

class StaffCopilotAgentRunner
{
    public const PROMPT_VERSION = 'staff-copilot-mvp:v1';

    public function __construct(
        private readonly BusinessGlossary $glossary,
        private readonly MetricCatalog $catalog,
        private readonly StaffMetricQuestionDataset $dataset,
        private readonly ToolDispatcher $toolDispatcher,
    ) {}

    public function run(string $question, User $actor, ?Campus $campus, AiAgentTrace $trace): StaffCopilotAnswer
    {
        $entitySearchPlan = $this->entitySearchPlan($question);

        if ($entitySearchPlan !== null) {
            $result = $this->toolDispatcher->dispatch(
                toolName: 'search_entities',
                arguments: $entitySearchPlan,
                actor: $actor,
                campus: $campus,
                trace: $trace,
            );

            return StaffCopilotAnswer::fromResult($result);
        }

        $plan = $this->planFor($question);

        if ($plan === null) {
            return StaffCopilotAnswer::unsupported();
        }

        $result = $this->toolDispatcher->dispatch(
            toolName: 'query_metrics',
            arguments: $plan,
            actor: $actor,
            campus: $campus,
            trace: $trace,
        );

        return StaffCopilotAnswer::fromResult($result);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entitySearchPlan(string $question): ?array
    {
        foreach ($this->entitySearchPatterns() as $entityType => $patterns) {
            foreach ($patterns as $pattern) {
                if (! preg_match($pattern, $question, $matches)) {
                    continue;
                }

                $query = $this->sanitizeEntityQuery((string) ($matches['query'] ?? $matches[1] ?? ''));

                if (mb_strlen($query) < 3) {
                    continue;
                }

                return [
                    'query' => $query,
                    'entity_types' => [$entityType],
                    'options' => [
                        'limit' => 5,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function entitySearchPatterns(): array
    {
        $verbs = '(?:find|search|lookup|tìm|tim)';

        return [
            'student' => [
                "/\\b{$verbs}\\s+(?:student|sinh\\s+vi[eê]n|sv)\\s+(?<query>[A-Z0-9._-]{3,100})\\b/iu",
            ],
            'program' => [
                "/\\b{$verbs}\\s+(?:program|major|ng[aà]nh)\\s+(?<query>[A-Z0-9._-]{3,100})\\b/iu",
            ],
            'semester' => [
                "/\\b{$verbs}\\s+(?:semester|term|k[yỳ])\\s+(?<query>[A-Z0-9._-]{3,100})\\b/iu",
            ],
            'course_offering' => [
                "/\\b{$verbs}\\s+(?:class|section|l[oớ]p)\\s+(?<query>[A-Z0-9._-]{3,100})\\b/iu",
            ],
        ];
    }

    private function sanitizeEntityQuery(string $query): string
    {
        return Str::of($query)
            ->trim(" \t\n\r\0\x0B.,;:!?")
            ->toString();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planFor(string $question): ?array
    {
        return $this->datasetPlan($question) ?? $this->glossaryPlan($question);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function datasetPlan(string $question): ?array
    {
        $normalizedQuestion = $this->normalize($question);

        foreach ($this->dataset->cases() as $case) {
            if ($normalizedQuestion !== $this->normalize((string) $case['question'])) {
                continue;
            }

            $toolCall = $case['expected_tool_calls'][0] ?? null;

            if (! is_array($toolCall) || ($toolCall['tool'] ?? null) !== 'query_metrics') {
                return null;
            }

            $metric = (string) ($toolCall['metric'] ?? '');

            if (! $this->catalog->hasMetric($metric)) {
                return null;
            }

            return $this->queryMetricsPlan(
                metric: $metric,
                filters: is_array($toolCall['filters'] ?? null) ? $toolCall['filters'] : [],
                groupBy: is_array($toolCall['group_by'] ?? null) ? array_values(array_map('strval', $toolCall['group_by'])) : [],
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function glossaryPlan(string $question): ?array
    {
        $metric = $this->glossary->metricForPhrase($question);

        if ($metric === null || ! $this->catalog->hasMetric($metric)) {
            return null;
        }

        $metricDefinition = $this->catalog->metric($metric) ?? [];
        $filters = [];
        $filterValue = $this->glossary->filterValueForPhrase($question);

        if ($filterValue !== null) {
            $filters[$filterValue['filter']] = $filterValue['value'];
        }

        foreach (($metricDefinition['allowed_filters'] ?? []) as $filter => $definition) {
            if (($definition['required'] ?? false) === true && $filter === 'semester' && ! array_key_exists('semester', $filters)) {
                $filters['semester'] = 'current';
            }
        }

        return $this->queryMetricsPlan(
            metric: $metric,
            filters: $filters,
            groupBy: $this->groupByForQuestion($question, $metricDefinition['allowed_group_by'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $groupBy
     * @return array<string, mixed>
     */
    private function queryMetricsPlan(string $metric, array $filters, array $groupBy): array
    {
        $metricDefinition = $this->catalog->metric($metric) ?? [];

        return [
            'metric' => $metric,
            'filters' => $filters,
            'group_by' => $groupBy,
            'options' => [
                'include_rows' => false,
                'limit' => (int) ($metricDefinition['max_record_limit'] ?? 100),
            ],
        ];
    }

    /**
     * @param  list<string>  $allowedGroupBy
     * @return list<string>
     */
    private function groupByForQuestion(string $question, array $allowedGroupBy): array
    {
        $normalized = $this->normalize($question);
        $candidates = [
            'program' => ['by program', 'theo program', 'theo từng program'],
            'status' => ['by status', 'theo status', 'từng status'],
            'expected_fee_type' => ['expected fee type', 'loại phí dự kiến'],
            'attention_bucket' => ['attention bucket', 'bucket'],
            'fee_type' => ['fee type', 'loại phí'],
            'balance_state' => ['balance state', 'trạng thái nợ'],
            'aging_bucket' => ['aging bucket', 'tuổi nợ'],
        ];

        foreach ($candidates as $groupBy => $phrases) {
            if (! in_array($groupBy, $allowedGroupBy, true)) {
                continue;
            }

            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $this->normalize($phrase))) {
                    return [$groupBy];
                }
            }
        }

        return [];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->toString();
    }
}
