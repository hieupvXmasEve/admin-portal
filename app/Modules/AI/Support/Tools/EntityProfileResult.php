<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

class EntityProfileResult
{
    /**
     * @param  list<string>  $requestedSections
     * @param  list<string>  $returnedSections
     * @param  array<string, mixed>  $sections
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
        private readonly string $entityCatalogVersion,
        private readonly ?string $entityType,
        private readonly array $requestedSections,
        private readonly array $returnedSections,
        private readonly array $sections,
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
     * @param  list<string>  $requestedSections
     * @param  array<string, mixed>  $sections
     * @param  list<array<string, mixed>>  $sourceReferences
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $hiddenSections
     * @param  list<string>  $warnings
     */
    public static function completed(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $entityCatalogVersion,
        string $entityType,
        array $requestedSections,
        array $sections,
        array $sourceReferences,
        array $campusScopeSnapshot,
        array $hiddenSections = [],
        array $warnings = [],
    ): self {
        $returnedSections = array_values(array_keys($sections));
        $status = $hiddenSections !== [] || in_array('source_result_truncated', $warnings, true) ? 'partial' : 'completed';

        return new self(
            allowed: true,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            entityCatalogVersion: $entityCatalogVersion,
            entityType: $entityType,
            requestedSections: $requestedSections,
            returnedSections: $returnedSections,
            sections: $sections,
            sourceReferences: $sourceReferences,
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: $hiddenSections,
            warnings: $warnings,
            confidence: ['level' => 'high', 'basis' => 'profile_section_readers'],
            safeErrorCode: in_array('source_result_truncated', $warnings, true) ? 'source_result_truncated' : null,
            permissionResult: $hiddenSections !== [] ? 'partial' : 'allowed',
            status: $status,
        );
    }

    /**
     * @param  list<string>  $requestedSections
     * @param  array<string, mixed>  $campusScopeSnapshot
     * @param  list<string>  $hiddenSections
     */
    public static function denied(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $entityCatalogVersion,
        ?string $entityType,
        array $requestedSections,
        array $campusScopeSnapshot,
        array $hiddenSections,
        string $safeErrorCode = 'forbidden_by_permission',
    ): self {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            entityCatalogVersion: $entityCatalogVersion,
            entityType: $entityType,
            requestedSections: $requestedSections,
            returnedSections: [],
            sections: [],
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
     * @param  list<string>  $requestedSections
     * @param  array<string, mixed>  $campusScopeSnapshot
     */
    public static function failed(
        string $tool,
        string $toolSchemaVersion,
        string $catalogVersion,
        string $entityCatalogVersion,
        ?string $entityType,
        array $requestedSections,
        array $campusScopeSnapshot,
        string $safeErrorCode,
    ): self {
        return new self(
            allowed: false,
            tool: $tool,
            toolSchemaVersion: $toolSchemaVersion,
            catalogVersion: $catalogVersion,
            entityCatalogVersion: $entityCatalogVersion,
            entityType: $entityType,
            requestedSections: $requestedSections,
            returnedSections: [],
            sections: [],
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
        return count($this->returnedSections);
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
            'entity_type' => $this->entityType,
            'profile_catalog_version' => $this->catalogVersion,
            'requested_sections' => $this->requestedSections,
            'returned_sections' => $this->returnedSections,
            'sections' => $this->sections,
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
            'entity_catalog_version' => $this->entityCatalogVersion,
            'entity_type' => $this->entityType,
            'requested_sections' => $this->requestedSections,
            'returned_sections' => $this->returnedSections,
            'sections' => $this->sections,
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
