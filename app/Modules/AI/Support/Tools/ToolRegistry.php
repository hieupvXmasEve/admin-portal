<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;

class ToolRegistry
{
    public function __construct(
        private readonly QueryMetricsTool $queryMetricsTool,
        private readonly SearchEntitiesTool $searchEntitiesTool,
        private readonly GetEntityProfileTool $getEntityProfileTool,
        private readonly MetricCatalog $catalog,
        private readonly EntityCatalog $entityCatalog,
        private readonly StudentProfileSectionCatalog $studentProfileSectionCatalog,
    ) {}

    /**
     * @return list<string>
     */
    public function toolNames(): array
    {
        return ['query_metrics', 'search_entities', 'get_entity_profile'];
    }

    public function has(string $toolName): bool
    {
        return in_array($toolName, $this->toolNames(), true);
    }

    public function definition(string $toolName): ?AiToolDefinition
    {
        return match ($toolName) {
            'query_metrics' => new AiToolDefinition(
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
                    'forbidden_by_domain_permission',
                    'forbidden_by_campus_scope',
                    'max_record_limit_exceeded',
                    'metric_resolver_missing',
                    'source_query_failed',
                    'source_result_truncated',
                ],
            ),
            'search_entities' => new AiToolDefinition(
                name: 'search_entities',
                schemaVersion: $this->entityCatalog->toolSchemaVersion(),
                permission: 'view_ai_metrics',
                description: 'Search allowlisted academic entity candidates without exposing raw source records.',
                safeErrorCodes: $this->entityCatalog->safeErrorCodes(),
            ),
            'get_entity_profile' => new AiToolDefinition(
                name: 'get_entity_profile',
                schemaVersion: $this->studentProfileSectionCatalog->toolSchemaVersion(),
                permission: 'view_ai_metrics',
                description: 'Return allowlisted student profile sections from an opaque entity_ref.',
                safeErrorCodes: $this->studentProfileSectionCatalog->safeErrorCodes(),
            ),
            default => null,
        };
    }

    public function tool(string $toolName): QueryMetricsTool|SearchEntitiesTool|GetEntityProfileTool|null
    {
        return match ($toolName) {
            'query_metrics' => $this->queryMetricsTool,
            'search_entities' => $this->searchEntitiesTool,
            'get_entity_profile' => $this->getEntityProfileTool,
            default => null,
        };
    }
}
