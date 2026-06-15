<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services\Batch;

use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Preview-token safety contract for Batch Studio.
 *
 * issue()   -> after step ② preview, store per-line hashes (one-time, 30-min TTL).
 * verify()  -> at step ④ commit, recompute-compare the *selected subset* (no mutation).
 * consume() -> verify then forget the token (one-time use; blocks stale-tab replay).
 */
final class BatchPreviewTokenService
{
    private const TTL_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $scope  semester/fee-type/scope params (audit only)
     * @param  list<BatchPreviewLine>  $lines
     */
    public function issue(int $userId, BatchJobType $jobType, array $scope, array $lines): string
    {
        $token = (string) Str::uuid();

        Cache::put(
            $this->cacheKey($userId, $jobType, $token),
            [
                'user_id' => $userId,
                'job_type' => $jobType->value,
                'scope' => $scope,
                'lines' => BatchPreviewLine::toTokenPayload($lines),
            ],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    /**
     * Recompute-compare the submitted subset against the issued per-line hashes.
     *
     * @param  array<string, array<string, mixed>>  $selected  key => re-resolved hash payload
     * @return array{ok: bool, missing: bool, changed: list<string>}
     */
    public function verify(int $userId, string $token, BatchJobType $jobType, array $selected): array
    {
        $stored = Cache::get($this->cacheKey($userId, $jobType, $token));

        if (! is_array($stored)
            || ($stored['user_id'] ?? null) !== $userId
            || ($stored['job_type'] ?? null) !== $jobType->value) {
            return ['ok' => false, 'missing' => true, 'changed' => []];
        }

        $issued = $stored['lines'] ?? [];
        $changed = [];

        foreach ($selected as $key => $payload) {
            $expected = $issued[$key] ?? null;
            $actual = BatchPreviewLineHasher::hashLine($payload);
            if ($expected === null || ! hash_equals($expected, $actual)) {
                $changed[] = (string) $key;
            }
        }

        return ['ok' => $changed === [], 'missing' => false, 'changed' => $changed];
    }

    /**
     * @param  array<string, array<string, mixed>>  $selected
     * @return array{ok: bool, missing: bool, changed: list<string>}
     */
    public function consume(int $userId, string $token, BatchJobType $jobType, array $selected): array
    {
        $result = $this->verify($userId, $token, $jobType, $selected);

        if ($result['ok']) {
            Cache::forget($this->cacheKey($userId, $jobType, $token));
        }

        return $result;
    }

    /**
     * The scope params stored at issue time — the commit controller re-resolves the
     * selected lines from current DB state using these, so drift is computed server-side
     * (never trust a client-sent payload).
     *
     * @return array<string, mixed>|null  null if missing/expired/foreign
     */
    public function scope(int $userId, string $token, BatchJobType $jobType): ?array
    {
        $stored = Cache::get($this->cacheKey($userId, $jobType, $token));

        if (! is_array($stored)
            || ($stored['user_id'] ?? null) !== $userId
            || ($stored['job_type'] ?? null) !== $jobType->value) {
            return null;
        }

        return $stored['scope'] ?? [];
    }

    private function cacheKey(int $userId, BatchJobType $jobType, string $token): string
    {
        return sprintf('%s:%d:%s', $jobType->cacheNamespace(), $userId, $token);
    }
}