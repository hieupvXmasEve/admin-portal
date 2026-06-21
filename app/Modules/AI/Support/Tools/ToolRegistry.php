<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Modules\AI\Support\MetricCatalog;

class ToolRegistry
{
    public function __construct(
        private readonly QueryMetricsTool $queryMetricsTool,
        private readonly MetricCatalog $catalog,
    ) {}

    /**
     * @return list<string>
     */
    public function toolNames(): array
    {
        return ['query_metrics'];
    }

    public function has(string $toolName): bool
    {
        return $toolName === 'query_metrics';
    }

    public function definition(string $toolName): ?AiToolDefinition
    {
        if (! $this->has($toolName)) {
            return null;
        }

        return new AiToolDefinition(
            name: 'query_metrics',
            schemaVersion: $this->catalog->toolSchemaVersion(),
            permission: 'view_ai_metrics',
            description: 'Execute an allowlisted aggregate Academic or Finance metric.',
            safeErrorCodes: [
                'invalid_query_plan_schema',
                'unsupported_metric',
                'unsupported_filter',
                'invalid_filter_value',
                'missing_required_filter',
                'unsupported_group_by',
                'unsupported_query_plan_option',
                'forbidden_by_permission',
                'forbidden_by_campus_scope',
                'max_record_limit_exceeded',
                'metric_resolver_missing',
                'source_query_failed',
                'source_result_truncated',
            ],
        );
    }

    public function tool(string $toolName): ?QueryMetricsTool
    {
        return $this->has($toolName) ? $this->queryMetricsTool : null;
    }
}
