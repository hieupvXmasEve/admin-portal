<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Resolves EGC block finance evidence through the canonical obligation source
 * triple. EGC source rows never persist a Finance charge or obligation id.
 */
final class EgcBlockFinanceResolver
{
    public function sourceRef(object|int $block): string
    {
        $id = $this->blockId($block);

        return AcademicFinanceSourceKeys::egcBlockRef($id);
    }

    public function chargeFor(object|int $block): ?FinanceCharge
    {
        $blockId = $this->blockId($block);

        return $this->chargesFor(collect([$blockId]))->get($blockId);
    }

    public function bindExistingCharge(object|int $block, FinanceCharge $charge): void
    {
        $blockId = $this->blockId($block);
        $obligation = $charge->financeObligation;
        if (! $obligation instanceof FinanceObligation) {
            throw new RuntimeException("EGC block {$blockId} charge {$charge->id} has no canonical Finance obligation.");
        }

        $sourceRef = $this->sourceRef($blockId);
        $conflict = FinanceObligation::query()
            ->where('source_system', SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM)
            ->where('source_kind', SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK)
            ->where('source_ref', $sourceRef)
            ->whereKeyNot($obligation->id)
            ->exists();
        if ($conflict) {
            throw new RuntimeException("EGC block {$blockId} already resolves to a different Finance obligation.");
        }

        $obligation->update([
            'source_system' => SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM,
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK,
            'source_ref' => $sourceRef,
        ]);
    }

    /**
     * @param  Collection<int, object|int>  $blocks
     * @return Collection<int, FinanceCharge> keyed by EGC block id
     */
    public function chargesFor(Collection $blocks): Collection
    {
        $blockIds = $blocks
            ->map(fn (object|int $block): int => $this->blockId($block))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($blockIds->isEmpty()) {
            return collect();
        }

        $blockIdByObligation = FinanceObligation::query()
            ->where('source_system', SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM)
            ->where('source_kind', SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK)
            ->whereIn('source_ref', $blockIds->map(fn (int $id): string => $this->sourceRef($id)))
            ->pluck('source_ref', 'id')
            ->map(static fn (string $sourceRef): int => (int) substr($sourceRef, strlen('egc-block:')));

        $charges = FinanceCharge::query()
            ->whereIn('finance_obligation_id', $blockIdByObligation->keys())
            ->orderByDesc('id')
            ->get()
            ->keyBy('finance_obligation_id');

        return $blockIdByObligation
            ->mapWithKeys(fn (int $blockId, int $obligationId): array => [$blockId => $charges->get($obligationId)])
            ->filter();
    }

    private function blockId(object|int $block): int
    {
        if (is_int($block)) {
            return $block;
        }

        if (isset($block->id) && is_numeric($block->id)) {
            return (int) $block->id;
        }

        throw new RuntimeException('EGC block finance resolution requires a block id.');
    }
}
