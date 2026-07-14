<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\EgcBlock;
use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Resolves EGC block finance evidence through the canonical obligation source
 * triple. EGC source rows never persist a Finance charge or obligation id.
 */
final class EgcBlockFinanceResolver
{
    public function sourceRef(EgcBlock|int $block): string
    {
        $id = $block instanceof EgcBlock ? (int) $block->id : $block;

        return 'egc-block:'.$id;
    }

    public function chargeFor(EgcBlock|int $block): ?FinanceCharge
    {
        return $this->chargesFor(collect([$block instanceof EgcBlock ? $block : $block]))
            ->get($block instanceof EgcBlock ? (int) $block->id : $block);
    }

    public function bindExistingCharge(EgcBlock $block, FinanceCharge $charge): void
    {
        $obligation = $charge->financeObligation;
        if (! $obligation instanceof FinanceObligation) {
            throw new RuntimeException("EGC block {$block->id} charge {$charge->id} has no canonical Finance obligation.");
        }

        $sourceRef = $this->sourceRef($block);
        $conflict = FinanceObligation::query()
            ->where('source_system', SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM)
            ->where('source_kind', SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK)
            ->where('source_ref', $sourceRef)
            ->whereKeyNot($obligation->id)
            ->exists();
        if ($conflict) {
            throw new RuntimeException("EGC block {$block->id} already resolves to a different Finance obligation.");
        }

        $obligation->update([
            'source_system' => SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM,
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK,
            'source_ref' => $sourceRef,
        ]);
    }

    /**
     * @param  Collection<int, EgcBlock|int>  $blocks
     * @return Collection<int, FinanceCharge> keyed by EGC block id
     */
    public function chargesFor(Collection $blocks): Collection
    {
        $blockIds = $blocks
            ->map(static fn (EgcBlock|int $block): int => $block instanceof EgcBlock ? (int) $block->id : $block)
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
}
