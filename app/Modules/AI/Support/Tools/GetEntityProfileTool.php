<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Models\User;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\EntityReferenceResolver;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Shared\Contracts\Academic\AiAcademicStudentProfileReader;
use App\Shared\Contracts\Finance\AiFinanceStudentProfileReader;
use Illuminate\Support\Facades\Gate;
use Throwable;

class GetEntityProfileTool
{
    public const NAME = 'get_entity_profile';

    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

    public function __construct(
        private readonly StudentProfileSectionCatalog $catalog,
        private readonly EntityCatalog $entityCatalog,
        private readonly EntityReferenceResolver $entityReferenceResolver,
        private readonly AiAcademicStudentProfileReader $academicReader,
        private readonly AiFinanceStudentProfileReader $financeReader,
        private readonly AiAuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments, QueryMetricsExecutionContext $context): EntityProfileResult
    {
        $started = microtime(true);
        $entityType = $this->entityType($arguments['entity_type'] ?? null);
        $requestedSections = $this->requestedSections($arguments['sections'] ?? null);
        $limit = $this->limit($arguments['options']['limit'] ?? null);
        $campusScope = $this->campusScope($context);

        $schemaError = $this->schemaError($arguments, $entityType, $requestedSections, $limit);

        if ($schemaError !== null) {
            $result = EntityProfileResult::failed(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                $entityType,
                $requestedSections,
                $campusScope,
                $schemaError,
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        if (! Gate::forUser($context->actor)->allows('view_ai_metrics')) {
            $result = EntityProfileResult::denied(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                $entityType,
                $requestedSections,
                $campusScope,
                ['get_entity_profile'],
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        $resolution = $this->entityReferenceResolver->resolve((string) $arguments['entity_ref'], $context->campus, 'student');

        if ($resolution['ok'] === false) {
            $safeErrorCode = (string) $resolution['safe_error_code'];
            $result = $safeErrorCode === 'forbidden_by_campus_scope'
                ? EntityProfileResult::denied(
                    self::NAME,
                    $this->catalog->toolSchemaVersion(),
                    $this->catalog->version(),
                    $this->entityCatalog->version(),
                    $entityType,
                    $requestedSections,
                    $campusScope,
                    ['student.profile'],
                    $safeErrorCode,
                )
                : EntityProfileResult::failed(
                    self::NAME,
                    $this->catalog->toolSchemaVersion(),
                    $this->catalog->version(),
                    $this->entityCatalog->version(),
                    $entityType,
                    $requestedSections,
                    $campusScope,
                    $safeErrorCode,
                );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        $sourceId = (int) $resolution['reference']['source_id'];

        if (! Gate::forUser($context->actor)->allows('view_student')) {
            $result = EntityProfileResult::denied(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                $entityType,
                $requestedSections,
                $campusScope,
                $this->hiddenSectionsFor($requestedSections),
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        $identity = $this->academicReader->identity($sourceId);

        if ($identity === null) {
            $result = EntityProfileResult::failed(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                $entityType,
                $requestedSections,
                $campusScope,
                'entity_not_found',
            );
            $this->recordAudit($context, $arguments, $result, $started);

            return $result;
        }

        try {
            $result = $this->profileResult($sourceId, $identity, $requestedSections, $limit, $campusScope, $context->actor);
        } catch (Throwable) {
            $result = EntityProfileResult::failed(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                $entityType,
                $requestedSections,
                $campusScope,
                'source_query_failed',
            );
        }

        $this->recordAudit($context, $arguments, $result, $started);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  list<string>  $requestedSections
     * @param  array<string, mixed>  $campusScope
     */
    private function profileResult(
        int $studentId,
        array $identity,
        array $requestedSections,
        int $limit,
        array $campusScope,
        User $actor,
    ): EntityProfileResult {
        $sections = [];
        $hiddenSections = [];
        $sourceReferences = [];

        foreach ($requestedSections as $section) {
            $definition = $this->catalog->section($section);
            $permission = $definition['required_permission'] ?? null;

            if (! is_string($permission) || ! Gate::forUser($actor)->allows($permission)) {
                $hiddenSections[] = (string) ($definition['hidden_section'] ?? "student.{$section}");

                continue;
            }

            $sections[$section] = $this->sectionPayload($studentId, $section, $identity, $limit);
            $sourceReferences[] = $this->sourceReference($definition);
        }

        if ($sections === []) {
            return EntityProfileResult::denied(
                self::NAME,
                $this->catalog->toolSchemaVersion(),
                $this->catalog->version(),
                $this->entityCatalog->version(),
                'student',
                $requestedSections,
                $campusScope,
                array_values(array_unique($hiddenSections)),
            );
        }

        return EntityProfileResult::completed(
            self::NAME,
            $this->catalog->toolSchemaVersion(),
            $this->catalog->version(),
            $this->entityCatalog->version(),
            'student',
            $requestedSections,
            $sections,
            $sourceReferences,
            $campusScope,
            array_values(array_unique($hiddenSections)),
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function sectionPayload(int $studentId, string $section, array $identity, int $limit): array
    {
        return match ($section) {
            'identity' => $identity,
            'academic_summary' => $this->academicReader->academicSummary($studentId),
            'enrollments' => $this->academicReader->enrollments($studentId, $limit),
            'attendance_summary' => $this->academicReader->attendanceSummary($studentId, $limit),
            'finance_summary' => $this->financeReader->financeSummary($studentId),
            'lifecycle_actions' => $this->academicReader->lifecycleActions($studentId, $limit),
            default => [],
        };
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
            'entity_type' => 'student',
            'section' => (string) $definition['key'],
            'fields' => $definition['fields'] ?? [],
            'freshness' => (string) ($definition['freshness_rule'] ?? 'computed_at_request_time'),
            'permission_scope' => (string) ($definition['required_permission'] ?? ''),
            'source_reader' => (string) ($definition['source_reader'] ?? ''),
        ];
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function hiddenSectionsFor(array $sections): array
    {
        return collect($sections)
            ->map(fn (string $section): string => (string) ($this->catalog->section($section)['hidden_section'] ?? "student.{$section}"))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $requestedSections
     */
    private function schemaError(array $arguments, ?string $entityType, array $requestedSections, int $limit): ?string
    {
        if ($entityType !== 'student') {
            return 'unsupported_entity_type';
        }

        if (! is_string($arguments['entity_ref'] ?? null) || trim((string) $arguments['entity_ref']) === '' || mb_strlen((string) $arguments['entity_ref']) > 4096) {
            return 'invalid_profile_request_schema';
        }

        if (! is_array($arguments['sections'] ?? null) || $arguments['sections'] === []) {
            return 'invalid_profile_request_schema';
        }

        if ($requestedSections === [] || $this->hasUnsupportedSections($arguments['sections'])) {
            return 'unsupported_profile_section';
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            return 'invalid_profile_request_schema';
        }

        $options = $arguments['options'] ?? [];

        if ($options !== [] && ! is_array($options)) {
            return 'invalid_profile_request_schema';
        }

        foreach (['include_profiles', 'include_rows', 'include_hidden_fields', 'include_source_rows'] as $forbiddenOption) {
            if (is_array($options) && array_key_exists($forbiddenOption, $options)) {
                return 'invalid_profile_request_schema';
            }
        }

        foreach (['student_id', 'source_id', 'id', 'sql', 'table', 'columns', 'relationships', 'include', 'include_rows', 'include_hidden_fields', 'include_source_rows', 'raw', 'ledger', 'attachments', 'payload'] as $forbiddenArgument) {
            if (array_key_exists($forbiddenArgument, $arguments)) {
                return 'invalid_profile_request_schema';
            }
        }

        return null;
    }

    private function entityType(mixed $entityType): ?string
    {
        if (! is_string($entityType)) {
            return null;
        }

        return $this->entityCatalog->normalizeEntityType($entityType);
    }

    /**
     * @return list<string>
     */
    private function requestedSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        return $this->catalog->normalizeSections(array_values(array_map('strval', $sections)));
    }

    private function hasUnsupportedSections(mixed $sections): bool
    {
        if (! is_array($sections)) {
            return false;
        }

        foreach ($sections as $section) {
            if (! is_scalar($section) || $this->catalog->normalizeSection((string) $section) === null) {
                return true;
            }
        }

        return false;
    }

    private function limit(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return self::DEFAULT_LIMIT;
        }

        return (int) $limit;
    }

    /**
     * @return array<string, mixed>
     */
    private function campusScope(QueryMetricsExecutionContext $context): array
    {
        return [
            'campus_ids' => $context->campus?->id ? [$context->campus->id] : [],
            'scope_rule' => 'current_campus_only',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function recordAudit(
        QueryMetricsExecutionContext $context,
        array $arguments,
        EntityProfileResult $result,
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
