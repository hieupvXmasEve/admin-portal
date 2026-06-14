<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Unit;

/**
 * Single source of truth for the EGC level fee (FIN-06).
 *
 * Canonical source is the EGC Unit's base_fee for that level. When no EGC unit
 * exists for the level, or its base_fee is unset/zero, fall back to the flat
 * FALLBACK_FEE so every generation path (dedicated EGC flow, batch flow) and the
 * previews resolve the same amount. Previously the dedicated flow hardcoded the
 * flat fee while the batch flow read base_fee (and skipped the charge entirely
 * when no unit existed) — the two diverged.
 */
class EgcLevelFeeResolver
{
    /**
     * Flat fallback fee (VND) used when an EGC unit has no base_fee configured.
     */
    public const FALLBACK_FEE = 15_000_000;

    /**
     * Per-instance memo so resolving the same level repeatedly inside a batch
     * loop does not re-query units.
     *
     * @var array<int, float>
     */
    private array $feeByLevel = [];

    public function resolve(int $level): float
    {
        if (! array_key_exists($level, $this->feeByLevel)) {
            $unit = Unit::query()
                ->where('unit_type', 'egc')
                ->where('level', $level)
                ->first();

            $baseFee = $unit !== null ? (float) $unit->base_fee : 0.0;

            $this->feeByLevel[$level] = $baseFee > 0
                ? $baseFee
                : (float) self::FALLBACK_FEE;
        }

        return $this->feeByLevel[$level];
    }
}
