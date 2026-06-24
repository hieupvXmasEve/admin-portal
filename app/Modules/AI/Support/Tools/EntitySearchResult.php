<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

class EntitySearchResult
{
    /**
     * @param  list<string>  $entityTypes
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $sourceReferences
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $hiddenSections
     * @param  list<string>  $warnings
     * @param  array{level: string, basis: string}  $confidence
     */
    public function __construct(
        private readonly bool $allowed,
        private readonly string $tool,
        private readonly string $toolSchemaVersion,
        private readonly string $catalogVersion,
        private readonly string $normalizedQuery,
        private readonly array $entityTypes,
        private readonly int $resultLimit,
        private readonly int $resultCount,
        private readonly array $results,
        private readonly array $sourceReferences,
        private readonly array $campusScopeSnapshot,
        private readonly array $hiddenSections,
        private readonly array $warnings,
        private readonly array $confidence,
        private readonly ?string $safeErrorCode,
        private readonly string $permissionResult,
        private readonly string $status,
    ) {}

    /**
     * @param  list<string>  $entityTypes
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $sourceReferences
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $warnings
     */
    public static function completed(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $normalizedQuery,
        array $entityTypes,
        int $resultLimit,
        array $results,
        array $sourceReferences,
        array $campusScopeSnapshot,
        array $warnings = [],
    ): self {
        $status = in_array('source_result_truncated', $warnings, true) ? 'partial' : 'completed';

        return new self(
            allowed: true,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            normalizedQuery: $normalizedQuery,
            entityTypes: $entityTypes,
            resultLimit: $resultLimit,
            resultCount: count($results),
            results: $results,
            sourceReferences: $sourceReferences,
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: [],
            warnings: $warnings,
            confidence: ['level' => $results === [] ? 'none' : 'high', 'basis' => $results === [] ? 'no_current_scope_match' : 'exact_identifier_match'],
            safeErrorCode: in_array('source_result_truncated', $warnings, true) ? 'source_result_truncated' : null,
            permissionResult: 'allowed',
            status: $status,
        );
    }

    /**
     * @param  list<string>  $entityTypes
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $hiddenSections
     */
    public static function denied(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $normalizedQuery,
        array $entityTypes,
        int $resultLimit,
        array $campusScopeSnapshot,
        array $hiddenSections,
        string $safeErrorCode = 'forbidden_by_permission',
    ): self {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            normalizedQuery: $normalizedQuery,
            entityTypes: $entityTypes,
            resultLimit: $resultLimit,
            resultCount: 0,
            results: [],
            sourceReferences: [],
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: $hiddenSections,
            warnings: [],
            confidence: ['level' => 'none', 'basis' => 'not_executed'],
            safeErrorCode: $safeErrorCode,
            permissionResult: 'denied',
            status: 'denied',
        );
    }

    /**
     * @param  list<string>  $entityTypes
     * @param  array<string, mixed>  $campusScopeSnapshot
     */
    public static function failed(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $normalizedQuery,
        array $entityTypes,
        int $resultLimit,
        array $campusScopeSnapshot,
        string $safeErrorCode,
    ): self {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            normalizedQuery: $normalizedQuery,
            entityTypes: $entityTypes,
            resultLimit: $resultLimit,
            resultCount: 0,
            results: [],
            sourceReferences: [],
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: [],
            warnings: [],
            confidence: ['level' => 'none', 'basis' => 'not_executed'],
            safeErrorCode: $safeErrorCode,
            permissionResult: 'not_evaluated',
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
        return $this->resultCount;
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
            'normalized_query' => $this->normalizedQuery,
            'entity_types' => $this->entityTypes,
            'result_limit' => $this->resultLimit,
            'result_count' => $this->resultCount,
            'results' => $this->results,
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
            'normalized_query' => $this->normalizedQuery,
            'entity_types' => $this->entityTypes,
            'result_limit' => $this->resultLimit,
            'result_count' => $this->resultCount,
            'results' => $this->results,
            'source_references' => $this->sourceReferences,
            'campus_scope_snapshot' => $this->campusScopeSnapshot,
            'hidden_sections' => $this->hiddenSections,
            'warnings' => $this->warnings,
            'confidence' => $this->confidence,
            'safe_error_code' => $this->safeErrorCode,
            'status' => $this->status,
        ];
    }
}
