<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\EntityReferenceResolver;
use App\Shared\Contracts\Academic\AiAcademicEntitySearchReader;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SearchEntitiesTool
{
    public const NAME = 'search_entities';

    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

    public function __construct(
        private readonly EntityCatalog $catalog,
        private readonly AiAcademicEntitySearchReader $reader,
        private readonly AiAuditRecorder $auditRecorder,
        private readonly EntityReferenceResolver $entityReferenceResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments, QueryMetricsExecutionContext $context): EntitySearchResult
    {
        $started = microtime(true);
        $normalizedQuery = $this->normalizedQuery($arguments['query'] ?? '');
        $options = is_array($arguments['options'] ?? null) ? $arguments['options'] : [];
        $limit = $this->limit($options['limit'] ?? null);
        $entityTypes = $this->entityTypes($arguments['entity_types'] ?? null);
        $campusScope = $this->campusScope($context, $entityTypes);

        $schemaError = $this->schemaError($arguments, $normalizedQuery, $entityTypes, $limit);

        if ($schemaError !== null) {
            $result = EntitySearchResult::failed(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $normalizedQuery,
                $entityTypes,
                min(max($limit, 1), self::MAX_LIMIT),
                $campusScope,
                $schemaError,
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        $hiddenSections = $this->hiddenSectionsForDeniedTypes($context, $entityTypes);

        if ($hiddenSections !== []) {
            $result = EntitySearchResult::denied(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $normalizedQuery,
                $entityTypes,
                $limit,
                $campusScope,
                $hiddenSections,
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        try {
            $result = $this->search($arguments, $context, $normalizedQuery, $entityTypes, $limit, $campusScope);
        } catch (Throwable) {
            $result = EntitySearchResult::failed(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $normalizedQuery,
                $entityTypes,
                $limit,
                $campusScope,
                'source_query_failed',
            );
        }

        $this->recordAudit($context, $arguments, $result, $started);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $entityTypes
     * @param  array<string, mixed>  $campusScope
     */
    private function search(
        array $arguments,
        QueryMetricsExecutionContext $context,
        string $normalizedQuery,
        array $entityTypes,
        int $limit,
        array $campusScope,
    ): EntitySearchResult {
        $filters = $this->normalizedFilters($arguments['filters'] ?? []);
        $results = [];
        $sourceReferences = [];
        $warnings = [];

        foreach ($entityTypes as $entityType) {
            $definition = $this->catalog->entity($entityType);

            if (! $definition) {
                continue;
            }

            $entityResults = $this->readEntity($entityType, (string) $arguments['query'], $context, $filters, $limit + 1);

            if (count($entityResults) > $limit) {
                $entityResults = array_slice($entityResults, 0, $limit);
                $warnings[] = 'source_result_truncated';
            }

            $sourceReference = $this->sourceReference($definition);
            $sourceReferences[] = $sourceReference;

            foreach ($entityResults as $entityResult) {
                $results[] = $this->resultPayload($entityResult, $definition, $sourceReference, $context);
            }
        }

        return EntitySearchResult::completed(
            self::NAME,
            $this->catalog->toolSchemaVersion(),
            $this->catalog->version(),
            $normalizedQuery,
            $entityTypes,
            $limit,
            array_slice($results, 0, $limit),
            $sourceReferences,
            $campusScope,
            array_values(array_unique($warnings)),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function readEntity(
        string $entityType,
        string $query,
        QueryMetricsExecutionContext $context,
        array $filters,
        int $limit,
    ): array {
        return match ($entityType) {
            'student' => $this->reader->searchStudents($query, $context->campus?->id, $filters, $limit),
            'program' => $this->reader->searchPrograms($query, $filters, $limit),
            'semester' => $this->reader->searchSemesters($query, $filters, $limit),
            'course_offering' => $this->reader->searchCourseOfferings($query, $context->campus?->id, $filters, $limit),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $entityResult
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $sourceReference
     * @return array<string, mixed>
     */
    private function resultPayload(
        array $entityResult,
        array $definition,
        array $sourceReference,
        QueryMetricsExecutionContext $context,
    ): array {
        return [
            'entity_type' => (string) $entityResult['entity_type'],
            'entity_ref' => $this->entityRef($entityResult, $definition, $context),
            'label' => (string) $entityResult['label'],
            'safe_identifiers' => $entityResult['safe_identifiers'] ?? [],
            'match_reason' => (string) $entityResult['match_reason'],
            'source_reference' => $sourceReference,
        ];
    }

    /**
     * @param  array<string, mixed>  $entityResult
     * @param  array<string, mixed>  $definition
     */
    private function entityRef(array $entityResult, array $definition, QueryMetricsExecutionContext $context): string
    {
        return $this->entityReferenceResolver->encodeReference($entityResult, $definition, $context->campus);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function sourceReference(array $definition): array
    {
        return [
            'source_report' => (string) $definition['source_report'],
            'source_reference_policy' => (string) $definition['source_reference_policy'],
            'catalog_version' => $this->catalog->version(),
            'tool_schema_version' => $this->catalog->toolSchemaVersion(),
            'entity_type' => (string) $definition['key'],
            'fields' => $definition['search_fields'],
            'scope' => $definition['campus_scope_rule'],
            'freshness' => (string) ($definition['freshness_rule'] ?? 'computed_at_request_time'),
            'permission_scope' => (string) ($definition['required_permission'] ?? ''),
            'source_reader' => (string) ($definition['source_reader'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function entityTypes(mixed $entityTypes): array
    {
        if (! is_array($entityTypes) || $entityTypes === []) {
            return [];
        }

        return $this->catalog->normalizeEntityTypes(array_values(array_map('strval', $entityTypes)));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $entityTypes
     */
    private function schemaError(array $arguments, string $normalizedQuery, array $entityTypes, int $limit): ?string
    {
        if ($normalizedQuery === '' || ! is_string($arguments['query'] ?? null) || mb_strlen((string) $arguments['query']) > 100) {
            return 'invalid_entity_search_schema';
        }

        if (mb_strlen($normalizedQuery) < 3) {
            return 'query_too_short';
        }

        if ($entityTypes === []) {
            return 'unsupported_entity_type';
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            return 'result_limit_exceeded';
        }

        $filters = $arguments['filters'] ?? [];

        if (! is_array($filters)) {
            return 'invalid_entity_search_schema';
        }

        if (array_key_exists('campus_id', $filters)) {
            return 'unsupported_entity_filter';
        }

        $allowedFilters = $this->allowedFilters($entityTypes);

        foreach (array_keys($filters) as $filter) {
            if (! in_array($filter, $allowedFilters, true)) {
                return 'unsupported_entity_filter';
            }
        }

        if (array_key_exists('semester', $filters)) {
            $semester = $filters['semester'];

            if (! is_scalar($semester) || (! $this->isCurrentKeyword($semester) && filter_var($semester, FILTER_VALIDATE_INT) === false)) {
                return 'invalid_entity_filter_value';
            }
        }

        if (array_key_exists('program_id', $filters)) {
            $programId = $filters['program_id'];

            if (! is_scalar($programId) || filter_var($programId, FILTER_VALIDATE_INT) === false) {
                return 'invalid_entity_filter_value';
            }
        }

        $options = $arguments['options'] ?? [];

        if ($options !== [] && ! is_array($options)) {
            return 'invalid_entity_search_schema';
        }

        foreach (['include_profiles', 'include_rows', 'include_hidden_fields'] as $forbiddenOption) {
            if (is_array($options) && array_key_exists($forbiddenOption, $options)) {
                return 'invalid_entity_search_schema';
            }
        }

        foreach (['sql', 'table', 'columns', 'relationships', 'include', 'include_profiles', 'include_rows', 'include_hidden_fields'] as $forbiddenArgument) {
            if (array_key_exists($forbiddenArgument, $arguments)) {
                return 'invalid_entity_search_schema';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $entityTypes
     * @return list<string>
     */
    private function allowedFilters(array $entityTypes): array
    {
        $filters = [];

        foreach ($entityTypes as $entityType) {
            $definition = $this->catalog->entity($entityType);

            foreach (($definition['allowed_filters'] ?? []) as $filter) {
                if (! in_array($filter, $filters, true)) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $normalized = [];

        if (array_key_exists('semester', $filters)) {
            $normalized['semester'] = $this->isCurrentKeyword($filters['semester'])
                ? 'current'
                : (int) $filters['semester'];
        }

        if (isset($filters['program_id'])) {
            $normalized['program_id'] = (int) $filters['program_id'];
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $entityTypes
     * @return array<string, mixed>
     */
    private function campusScope(QueryMetricsExecutionContext $context, array $entityTypes): array
    {
        $scopeRules = collect($entityTypes)
            ->map(fn (string $entityType): ?string => $this->catalog->entity($entityType)['campus_scope_rule'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'campus_ids' => $context->campus?->id ? [$context->campus->id] : [],
            'scope_rule' => count($scopeRules) === 1 ? $scopeRules[0] : 'mixed',
        ];
    }

    /**
     * @param  list<string>  $entityTypes
     * @return list<string>
     */
    private function hiddenSectionsForDeniedTypes(QueryMetricsExecutionContext $context, array $entityTypes): array
    {
        $hidden = [];

        foreach ($entityTypes as $entityType) {
            $definition = $this->catalog->entity($entityType);
            $permission = $definition['required_permission'] ?? null;

            if (! is_string($permission) || Gate::forUser($context->actor)->allows($permission)) {
                continue;
            }

            $hidden[] = $entityType;
        }

        return $hidden;
    }

    private function normalizedQuery(mixed $query): string
    {
        if (! is_string($query)) {
            return '';
        }

        return str($query)->lower()->squish()->toString();
    }

    private function limit(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return self::DEFAULT_LIMIT;
        }

        return (int) $limit;
    }

    private function isCurrentKeyword(mixed $value): bool
    {
        return is_string($value) && str($value)->lower()->squish()->toString() === 'current';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function recordAudit(
        QueryMetricsExecutionContext $context,
        array $arguments,
        EntitySearchResult $result,
        float $started,
    ): void {
        if ($context->trace === null) {
            return;
        }

        $this->auditRecorder->recordToolCall($context->trace, [
            'tool_name' => self::NAME,
            'tool_schema_version' => $this->catalog->toolSchemaVersion(),
            'arguments' => $arguments,
            'permission_result' => $result->permissionResult(),
            'campus_scope_snapshot' => $result->toArray()['campus_scope_snapshot'] ?? [],
            'hidden_sections' => $result->toArray()['hidden_sections'] ?? [],
            'record_count' => $result->recordCount(),
            'source_references' => $result->sourceReferences(),
            'result_summary' => $result->auditSummary(),
            'status' => $result->status(),
            'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)),
            'safe_error_code' => $result->safeErrorCode(),
        ]);
    }
}
