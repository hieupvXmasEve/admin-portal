<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

class QueryPlanValidationResult
{
    /**
     * @param  array<string, mixed>  $normalizedFilters
     * @param  list<string>  $groupBy
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $hiddenSections
     * @param  list<string>  $messages
     */
    public function __construct(
        private readonly bool $allowed,
        private readonly string $catalogVersion,
        private readonly string $toolSchemaVersion,
        private readonly ?string $metric,
        private readonly array $normalizedFilters,
        private readonly array $groupBy,
        private readonly string $permissionResult,
        private readonly array $campusScopeSnapshot,
        private readonly array $hiddenSections,
        private readonly int $maxRecordLimit,
        private readonly int $estimatedRecordCount,
        private readonly ?string $sourceReport,
        private readonly ?string $sourceReferencePolicy,
        private readonly ?string $safeErrorCode,
        private readonly array $messages = [],
    ) {}

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function safeErrorCode(): ?string
    {
        return $this->safeErrorCode;
    }

    public function permissionResult(): string
    {
        return $this->permissionResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedFilters(): array
    {
        return $this->normalizedFilters;
    }

    /**
     * @return list<string>
     */
    public function hiddenSections(): array
    {
        return $this->hiddenSections;
    }

    public function maxRecordLimit(): int
    {
        return $this->maxRecordLimit;
    }

    public function estimatedRecordCount(): int
    {
        return $this->estimatedRecordCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'catalog_version' => $this->catalogVersion,
            'tool_schema_version' => $this->toolSchemaVersion,
            'metric' => $this->metric,
            'normalized_filters' => $this->normalizedFilters,
            'group_by' => $this->groupBy,
            'permission_result' => $this->permissionResult,
            'campus_scope_snapshot' => $this->campusScopeSnapshot,
            'hidden_sections' => $this->hiddenSections,
            'max_record_limit' => $this->maxRecordLimit,
            'estimated_record_count' => $this->estimatedRecordCount,
            'source_report' => $this->sourceReport,
            'source_reference_policy' => $this->sourceReferencePolicy,
            'safe_error_code' => $this->safeErrorCode,
            'messages' => $this->messages,
        ];
    }
}
