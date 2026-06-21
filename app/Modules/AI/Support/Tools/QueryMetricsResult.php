<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

class QueryMetricsResult
{
    /**
     * @param  array<string, mixed>  $normalizedFilters
     * @param  list<string>  $groupBy
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  array<string, mixed>|null  $summary
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $sourceReferences
     * @param  array<string, mixed>  $freshness
     * @param  list<string>  $hiddenSections
     * @param  list<string>  $warnings
     * @param  array{level: string, basis: string}  $confidence
     */
    public function __construct(
        private readonly bool $allowed,
        private readonly string $tool,
        private readonly string $toolSchemaVersion,
        private readonly string $catalogVersion,
        private readonly ?string $metric,
        private readonly array $normalizedFilters,
        private readonly array $groupBy,
        private readonly array $campusScopeSnapshot,
        private readonly ?array $summary,
        private readonly array $groups,
        private readonly array $sourceReferences,
        private readonly int $recordCount,
        private readonly array $freshness,
        private readonly array $hiddenSections,
        private readonly array $warnings,
        private readonly array $confidence,
        private readonly ?string $safeErrorCode,
        private readonly string $permissionResult,
        private readonly string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validation
     */
    public static function denied(string $tool, array $validation, ?string $safeErrorCode = null): self
    {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: (string) ($validation['tool_schema_version'] ?? 'query_metrics:v1'),
            catalogVersion: (string) ($validation['catalog_version'] ?? 'metric-catalog:v1'),
            metric: isset($validation['metric']) ? (string) $validation['metric'] : null,
            normalizedFilters: [],
            groupBy: $validation['group_by'] ?? [],
            campusScopeSnapshot: $validation['campus_scope_snapshot'] ?? [],
            summary: null,
            groups: [],
            sourceReferences: [],
            recordCount: 0,
            freshness: [],
            hiddenSections: $validation['hidden_sections'] ?? [],
            warnings: [],
            confidence: ['level' => 'none', 'basis' => 'not_executed'],
            safeErrorCode: $safeErrorCode ?? (isset($validation['safe_error_code']) ? (string) $validation['safe_error_code'] : null),
            permissionResult: (string) ($validation['permission_result'] ?? 'not_evaluated'),
            status: 'denied',
        );
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $payload
     */
    public static function completed(string $tool, array $validation, array $payload): self
    {
        $warnings = $payload['warnings'] ?? [];

        return new self(
            allowed: true,
            tool: $tool,
            toolSchemaVersion: (string) $validation['tool_schema_version'],
            catalogVersion: (string) $validation['catalog_version'],
            metric: (string) $validation['metric'],
            normalizedFilters: $validation['normalized_filters'] ?? [],
            groupBy: $validation['group_by'] ?? [],
            campusScopeSnapshot: $validation['campus_scope_snapshot'] ?? [],
            summary: $payload['summary'] ?? [],
            groups: $payload['groups'] ?? [],
            sourceReferences: $payload['source_references'] ?? [],
            recordCount: (int) ($payload['record_count'] ?? 0),
            freshness: $payload['freshness'] ?? [],
            hiddenSections: $validation['hidden_sections'] ?? [],
            warnings: $warnings,
            confidence: $payload['confidence'] ?? ['level' => 'high', 'basis' => 'source_report_parity'],
            safeErrorCode: $warnings !== [] && in_array('source_result_truncated', $warnings, true) ? 'source_result_truncated' : null,
            permissionResult: (string) ($validation['permission_result'] ?? 'allowed'),
            status: $warnings !== [] && in_array('source_result_truncated', $warnings, true) ? 'partial' : 'completed',
        );
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    public static function failed(string $tool, array $validation, string $safeErrorCode): self
    {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: (string) ($validation['tool_schema_version'] ?? 'query_metrics:v1'),
            catalogVersion: (string) ($validation['catalog_version'] ?? 'metric-catalog:v1'),
            metric: isset($validation['metric']) ? (string) $validation['metric'] : null,
            normalizedFilters: $validation['normalized_filters'] ?? [],
            groupBy: $validation['group_by'] ?? [],
            campusScopeSnapshot: $validation['campus_scope_snapshot'] ?? [],
            summary: null,
            groups: [],
            sourceReferences: [],
            recordCount: 0,
            freshness: [],
            hiddenSections: $validation['hidden_sections'] ?? [],
            warnings: [],
            confidence: ['level' => 'none', 'basis' => 'not_executed'],
            safeErrorCode: $safeErrorCode,
            permissionResult: (string) ($validation['permission_result'] ?? 'not_evaluated'),
            status: 'failed',
        );
    }

    public function status(): string
    {
        return $this->status;
    }

    public function permissionResult(): string
    {
        return $this->permissionResult;
    }

    public function recordCount(): int
    {
        return $this->recordCount;
    }

    public function safeErrorCode(): ?string
    {
        return $this->safeErrorCode;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceReferences(): array
    {
        return $this->sourceReferences;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSummary(): array
    {
        return [
            'allowed' => $this->allowed,
            'metric' => $this->metric,
            'summary' => $this->summary,
            'group_count' => count($this->groups),
            'warnings' => $this->warnings,
            'confidence' => $this->confidence,
            'safe_error_code' => $this->safeErrorCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'tool' => $this->tool,
            'tool_schema_version' => $this->toolSchemaVersion,
            'catalog_version' => $this->catalogVersion,
            'metric' => $this->metric,
            'normalized_filters' => $this->normalizedFilters,
            'group_by' => $this->groupBy,
            'campus_scope_snapshot' => $this->campusScopeSnapshot,
            'summary' => $this->summary,
            'groups' => $this->groups,
            'source_references' => $this->sourceReferences,
            'record_count' => $this->recordCount,
            'freshness' => $this->freshness,
            'hidden_sections' => $this->hiddenSections,
            'warnings' => $this->warnings,
            'confidence' => $this->confidence,
            'safe_error_code' => $this->safeErrorCode,
            'status' => $this->status,
        ];
    }
}
