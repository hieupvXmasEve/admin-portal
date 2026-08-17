<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

class MetricCatalog
{
    public const VERSION = 'metric-catalog:v1';

    public const TOOL_SCHEMA_VERSION = 'query_metrics:v1';

    /**
     * @return array<int, string>
     */
    public function metricKeys(): array
    {
        return array_keys($this->metrics());
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function toolSchemaVersion(): string
    {
        return self::TOOL_SCHEMA_VERSION;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metric(string $key): ?array
    {
        return $this->metrics()[$key] ?? null;
    }

    public function hasMetric(string $key): bool
    {
        return $this->metric($key) !== null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function metrics(): array
    {
        return [
            'academic_student_status_count' => [
                'key' => 'academic_student_status_count',
                'catalog_version' => self::VERSION,
                'domain' => 'academic',
                'label' => 'Student status count',
                'description' => 'Aggregate count of academic student statuses for a selected semester.',
                'source_query' => 'App\\Modules\\Academic\\Progression\\Queries\\Reporting\\GetStudentStatusBySemesterQuery',
                'source_reader' => 'App\\Shared\\Contracts\\Academic\\AiAcademicMetricReader',
                'source_report' => 'academic.reporting.student-status-by-semester',
                'source_reference_policy' => 'report_summary_with_filters',
                'output_shape' => 'aggregate_summary',
                'aggregate_fields' => ['student_count'],
                'allowed_filters' => [
                    'semester' => ['type' => 'semester', 'required' => true],
                    'program_id' => ['type' => 'integer'],
                    'intake_semester_id' => ['type' => 'integer'],
                    'student_status' => ['type' => 'string'],
                    'campus_id' => ['type' => 'campus_scope'],
                ],
                'allowed_group_by' => ['status', 'program', 'intake_semester'],
                'required_permission' => 'view_ai_metrics',
                'required_domain_permission' => 'view_academic_report',
                'campus_scope_rule' => 'current_campus_only',
                'max_record_limit' => 500,
                'freshness_rule' => 'computed_at_request_time',
                'hidden_sections' => [],
            ],
            'academic_defer_count' => [
                'key' => 'academic_defer_count',
                'catalog_version' => self::VERSION,
                'domain' => 'academic',
                'label' => 'Academic defer count',
                'description' => 'Aggregate count of deferred students for a selected semester.',
                'source_query' => 'App\\Modules\\Academic\\Progression\\Queries\\Reporting\\GetStudentStatusBySemesterQuery',
                'source_reader' => 'App\\Shared\\Contracts\\Academic\\AiAcademicMetricReader',
                'source_report' => 'academic.reporting.student-status-by-semester',
                'source_reference_policy' => 'report_summary_with_filters',
                'output_shape' => 'aggregate_summary',
                'aggregate_fields' => ['defer_count'],
                'allowed_filters' => [
                    'semester' => ['type' => 'semester', 'required' => true],
                    'program_id' => ['type' => 'integer'],
                    'intake_semester_id' => ['type' => 'integer'],
                    'campus_id' => ['type' => 'campus_scope'],
                ],
                'allowed_group_by' => ['program', 'intake_semester'],
                'required_permission' => 'view_ai_metrics',
                'required_domain_permission' => 'view_academic_report',
                'campus_scope_rule' => 'current_campus_only',
                'max_record_limit' => 500,
                'freshness_rule' => 'computed_at_request_time',
                'hidden_sections' => [],
            ],
            'finance_collection_summary' => [
                'key' => 'finance_collection_summary',
                'catalog_version' => self::VERSION,
                'domain' => 'finance',
                'label' => 'Finance collection summary',
                'description' => 'Aggregate billed, paid, outstanding, overdue, overpaid, and unapplied amounts.',
                'source_query' => 'App\\Modules\\Finance\\Queries\\Reporting\\ListCollectionProgressQuery',
                'source_reader' => 'App\\Shared\\Contracts\\Finance\\AiFinanceMetricReader',
                'source_report' => 'finance.reporting.collection-progress',
                'source_reference_policy' => 'report_summary_with_filters',
                'output_shape' => 'aggregate_summary',
                'aggregate_fields' => [
                    'student_count',
                    'billed_total',
                    'paid_total',
                    'outstanding_total',
                    'overdue_total',
                    'overpaid_total',
                    'unapplied_total',
                    'collection_rate',
                ],
                'allowed_filters' => [
                    'semester' => ['type' => 'semester', 'required' => true],
                    'program_id' => ['type' => 'integer'],
                    'intake_semester_id' => ['type' => 'integer'],
                    'fee_type' => ['type' => 'string'],
                    'balance_state' => ['type' => 'string'],
                    'aging_bucket' => ['type' => 'string'],
                    'student_status' => ['type' => 'string'],
                    'campus_id' => ['type' => 'campus_scope'],
                ],
                'allowed_group_by' => ['program', 'fee_type', 'balance_state', 'aging_bucket'],
                'required_permission' => 'view_ai_metrics',
                'required_domain_permission' => 'view_finance_reporting',
                'campus_scope_rule' => 'current_campus_only',
                'max_record_limit' => 500,
                'freshness_rule' => 'computed_at_request_time',
                'hidden_sections' => [],
            ],
            'finance_fee_monitor_summary' => [
                'key' => 'finance_fee_monitor_summary',
                'catalog_version' => self::VERSION,
                'domain' => 'finance',
                'label' => 'Finance fee monitor summary',
                'description' => 'Aggregate expected, generated, missing, blocked, and paid fee completeness.',
                'source_query' => 'App\\Modules\\Finance\\Queries\\Reporting\\ListFeeMonitorQuery',
                'source_reader' => 'App\\Shared\\Contracts\\Finance\\AiFinanceMetricReader',
                'source_report' => 'finance.reporting.fee-monitor',
                'source_reference_policy' => 'report_summary_with_filters',
                'output_shape' => 'aggregate_summary',
                'aggregate_fields' => ['missing_count', 'generated_count', 'blocked_count', 'paid_count'],
                'allowed_filters' => [
                    'semester' => ['type' => 'semester', 'required' => true],
                    'program_id' => ['type' => 'integer'],
                    'intake_semester_id' => ['type' => 'integer'],
                    'expected_fee_type' => ['type' => 'string'],
                    'generation_state' => ['type' => 'string'],
                    'payment_state' => ['type' => 'string'],
                    'student_status' => ['type' => 'string'],
                    'campus_id' => ['type' => 'campus_scope'],
                ],
                'allowed_group_by' => ['expected_fee_type', 'generation_state', 'payment_state', 'program'],
                'required_permission' => 'view_ai_metrics',
                'required_domain_permission' => 'view_finance_reporting',
                'campus_scope_rule' => 'current_campus_only',
                'max_record_limit' => 500,
                'freshness_rule' => 'computed_at_request_time',
                'hidden_sections' => [],
            ],
            'finance_dng_lifecycle_attention' => [
                'key' => 'finance_dng_lifecycle_attention',
                'catalog_version' => self::VERSION,
                'domain' => 'finance',
                'label' => 'DNG payment lifecycle attention',
                'description' => 'Aggregate DNG request, webhook, invoice, allocation, and flow attention states.',
                'source_query' => 'App\\Modules\\Finance\\Queries\\Reporting\\ListDngLifecycleQuery',
                'source_reader' => 'App\\Shared\\Contracts\\Finance\\AiFinanceMetricReader',
                'source_report' => 'finance.reporting.dng-lifecycle',
                'source_reference_policy' => 'report_summary_with_filters',
                'output_shape' => 'aggregate_summary',
                'aggregate_fields' => ['attention_count', 'request_count'],
                'allowed_filters' => [
                    'attention_bucket' => ['type' => 'string'],
                    'flow_state' => ['type' => 'string'],
                    'fee_type' => ['type' => 'string'],
                    'campus_id' => ['type' => 'campus_scope'],
                ],
                'allowed_group_by' => ['attention_bucket', 'flow_state', 'fee_type'],
                'required_permission' => 'view_ai_metrics',
                'required_domain_permission' => 'view_finance_reporting',
                'campus_scope_rule' => 'current_campus_only',
                'max_record_limit' => 500,
                'freshness_rule' => 'computed_at_request_time',
                'hidden_sections' => [],
            ],
        ];
    }
}
