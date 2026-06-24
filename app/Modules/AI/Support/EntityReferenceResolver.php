<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class EntityReferenceResolver
{
    private const MAX_REFERENCE_AGE_HOURS = 24;

    public function __construct(private readonly EntityCatalog $catalog) {}

    /**
     * @param  array<string, mixed>  $entityResult
     * @param  array<string, mixed>  $definition
     */
    public function encodeReference(array $entityResult, array $definition, ?Campus $campus): string
    {
        return Crypt::encryptString(json_encode([
            'entity_type' => $entityResult['entity_type'],
            'source_id' => $entityResult['source_id'],
            'campus_id' => $campus?->id,
            'catalog_version' => $this->catalog->version(),
            'scope_rule' => $definition['campus_scope_rule'],
            'issued_at' => now()->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{ok: true, reference: array{entity_type: string, source_id: int, campus_id: int|null, catalog_version: string, scope_rule: string, issued_at: string}}|array{ok: false, safe_error_code: string}
     */
    public function resolve(string $entityRef, ?Campus $campus, ?string $expectedEntityType = null): array
    {
        try {
            $decoded = Crypt::decryptString($entityRef);
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return ['ok' => false, 'safe_error_code' => 'invalid_entity_reference'];
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'safe_error_code' => 'invalid_entity_reference'];
        }

        $entityType = $this->stringValue($payload['entity_type'] ?? null);
        $sourceId = $payload['source_id'] ?? null;
        $catalogVersion = $this->stringValue($payload['catalog_version'] ?? null);
        $scopeRule = $this->stringValue($payload['scope_rule'] ?? null);
        $issuedAt = $this->stringValue($payload['issued_at'] ?? null);

        if (
            $entityType === null
            || ($expectedEntityType !== null && $entityType !== $expectedEntityType)
            || filter_var($sourceId, FILTER_VALIDATE_INT) === false
            || $catalogVersion !== $this->catalog->version()
            || $scopeRule === null
            || $issuedAt === null
        ) {
            return ['ok' => false, 'safe_error_code' => 'invalid_entity_reference'];
        }

        try {
            $issued = CarbonImmutable::parse($issuedAt);
        } catch (\Throwable) {
            return ['ok' => false, 'safe_error_code' => 'invalid_entity_reference'];
        }

        if ($issued->lt(now()->subHours(self::MAX_REFERENCE_AGE_HOURS))) {
            return ['ok' => false, 'safe_error_code' => 'expired_entity_reference'];
        }

        $referenceCampusId = $payload['campus_id'] ?? null;
        $referenceCampusId = $referenceCampusId === null ? null : (int) $referenceCampusId;

        if ($scopeRule === 'current_campus_only' && ($campus === null || $referenceCampusId !== (int) $campus->id)) {
            return ['ok' => false, 'safe_error_code' => 'forbidden_by_campus_scope'];
        }

        return [
            'ok' => true,
            'reference' => [
                'entity_type' => $entityType,
                'source_id' => (int) $sourceId,
                'campus_id' => $referenceCampusId,
                'catalog_version' => $catalogVersion,
                'scope_rule' => $scopeRule,
                'issued_at' => $issuedAt,
            ],
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
