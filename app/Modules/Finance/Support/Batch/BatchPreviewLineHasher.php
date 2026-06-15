<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

/**
 * Pure, deterministic per-line hashing for the Batch Studio preview-token contract.
 *
 * The hash binds the *resolved* payload of a single preview line so that a
 * recompute at commit time can detect any drift (changed scholarship, fee config,
 * registration, etc.) even when the resulting amount happens to be unchanged.
 */
final class BatchPreviewLineHasher
{
    /**
     * SHA-256 over the canonical (recursively key-sorted, value-sorted-arrays) payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hashLine(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    /**
     * Fee-config fingerprint. TuitionPlan / TuitionPlanTerm have no `version` column,
     * so identity = primary key + updated_at timestamp.
     */
    public static function feeConfigFingerprint(
        ?int $planId,
        ?string $planUpdatedAt,
        ?int $termId,
        ?string $termUpdatedAt,
    ): string {
        if ($planId === null && $termId === null) {
            return 'none';
        }

        return sprintf(
            'plan:%s@%s|term:%s@%s',
            $planId ?? '-',
            $planUpdatedAt ?? '-',
            $termId ?? '-',
            $termUpdatedAt ?? '-',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function canonicalJson(array $payload): string
    {
        $normalized = self::normalize($payload);

        return (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Recursively sort associative arrays by key and re-index + sort list arrays,
     * so display ordering never changes the hash.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private static function normalize($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $normalized = array_map(static fn ($v) => self::normalize($v), $value);

        if ($isList) {
            sort($normalized);

            return $normalized;
        }

        ksort($normalized);

        return $normalized;
    }
}