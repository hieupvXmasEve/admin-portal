<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support\Grading;

use App\Modules\Academic\Delivery\Actions\ApplyGradingSchemePackAction;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Loads the canonical, repo-tracked Metropolia grading scheme pack and exposes
 * each entry in the executable shape consumed by {@see MetropoliaV1Calculator}.
 *
 * The pack lives at docs/features/academic/metropolia-grading-schemes.json so it
 * is versioned alongside the prose reference. Schools share this repo but not a
 * database, so the catalog is read-only here; per-database assignment happens
 * through {@see ApplyGradingSchemePackAction}.
 */
final class MetropoliaSchemeCatalog
{
    private const PACK_PATH = 'docs/features/academic/metropolia-grading-schemes.json';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cachedSchemes = null;

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->schemes());
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        $scheme = $this->schemes()[$key] ?? null;

        if (! is_array($scheme)) {
            throw new RuntimeException("Unknown Metropolia grading scheme key [{$key}].");
        }

        return $scheme;
    }

    /** @return array<string, array<string, mixed>> */
    public function schemes(): array
    {
        if ($this->cachedSchemes !== null) {
            return $this->cachedSchemes;
        }

        $path = base_path(self::PACK_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Metropolia grading scheme pack not found at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $schemes = Arr::get($decoded, 'schemes');

        if (! is_array($schemes)) {
            throw new RuntimeException('Metropolia grading scheme pack must contain a schemes object.');
        }

        return $this->cachedSchemes = $schemes;
    }
}
