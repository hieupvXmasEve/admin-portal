<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AiEvaluationCase;

class StaffMetricQuestionDataset
{
    public const DATASET_VERSION = 'metric-catalog-v1';

    public function __construct(
        private readonly AiEvaluationRunner $evaluationRunner,
        private readonly MetricCatalog $catalog,
    ) {}

    /**
     * @return list<AiEvaluationCase>
     */
    public function seedBaselineCases(): array
    {
        return collect($this->cases())
            ->map(function (array $case): AiEvaluationCase {
                $existing = AiEvaluationCase::query()
                    ->where('dataset_version', self::DATASET_VERSION)
                    ->where('key', $case['key'])
                    ->first();

                if ($existing) {
                    $existing->fill($case)->save();

                    return $existing->refresh();
                }

                return $this->evaluationRunner->createCase($case);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cases(): array
    {
        return [
            $this->case(
                key: 'academic_defer_count_by_program',
                question: 'Kỳ hiện tại có bao nhiêu sinh viên defer theo từng program?',
                metric: 'academic_defer_count',
                filters: ['semester' => 'current'],
                groupBy: ['program'],
            ),
            $this->case(
                key: 'academic_status_count_selected_semester',
                question: 'Selected semester có bao nhiêu sinh viên theo từng status?',
                metric: 'academic_student_status_count',
                filters: ['semester' => 'current'],
                groupBy: ['status'],
            ),
            $this->case(
                key: 'finance_collection_summary_by_program',
                question: 'Current semester outstanding tuition by program là bao nhiêu?',
                metric: 'finance_collection_summary',
                filters: ['semester' => 'current'],
                groupBy: ['program'],
            ),
            $this->case(
                key: 'finance_fee_monitor_missing_by_expected_fee_type',
                question: 'Fee monitor đang thiếu hoặc blocked những expected fee type nào?',
                metric: 'finance_fee_monitor_summary',
                filters: ['semester' => 'current'],
                groupBy: ['expected_fee_type'],
            ),
            $this->case(
                key: 'finance_dng_lifecycle_attention_by_bucket',
                question: 'DNG/payment lifecycle có bucket nào cần staff chú ý?',
                metric: 'finance_dng_lifecycle_attention',
                filters: [],
                groupBy: ['attention_bucket'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $groupBy
     * @return array<string, mixed>
     */
    private function case(string $key, string $question, string $metric, array $filters, array $groupBy): array
    {
        $definition = $this->catalog->metric($metric);

        return [
            'key' => $key,
            'dataset_version' => self::DATASET_VERSION,
            'question' => $question,
            'actor_fixture' => [
                'role' => 'staff',
                'campus' => 'current',
            ],
            'permission_context' => [
                'permissions' => [(string) ($definition['required_permission'] ?? 'view_ai_metrics')],
                'campus_scope' => 'current_campus_only',
            ],
            'expected_tool_calls' => [
                [
                    'tool' => 'query_metrics',
                    'metric' => $metric,
                    'filters' => $filters,
                    'group_by' => $groupBy,
                ],
            ],
            'expected_source_references' => [
                [
                    'source_report' => (string) ($definition['source_report'] ?? ''),
                    'source_reference_policy' => (string) ($definition['source_reference_policy'] ?? ''),
                    'catalog_version' => $this->catalog->version(),
                    'tool_schema_version' => $this->catalog->toolSchemaVersion(),
                ],
            ],
            'expected_answer_properties' => [
                'must_cite_sources' => true,
                'must_include_filters' => true,
                'must_not_fabricate_numbers' => true,
            ],
            'status' => 'active',
        ];
    }
}
