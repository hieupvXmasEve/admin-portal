<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use Illuminate\Support\Collection;

final class EgcRetakeTargetResolver
{
    /**
     * Resolve the one block that may receive the retake relevel/discount.
     *
     * Block 1 failures target block 2 in the same semester. Block 2 failures
     * target block 1 in the immediately following semester. No later fallback.
     *
     * @return Collection<int, AcademicEgcBlockData>
     */
    public static function targetBlocksFor(object|int $sourceBlock): Collection
    {
        $source = self::sourceBlockData($sourceBlock);
        if (! $source instanceof AcademicEgcBlockData) {
            return collect();
        }

        return self::chargeable(collect(self::academicSources()->egcRetakeTargetsForBlock($source->id)));
    }

    private static function sourceBlockData(object|int $sourceBlock): ?AcademicEgcBlockData
    {
        if ($sourceBlock instanceof AcademicEgcBlockData) {
            return $sourceBlock;
        }

        $blockId = is_int($sourceBlock) ? $sourceBlock : (int) ($sourceBlock->id ?? 0);

        return $blockId > 0 ? self::academicSources()->egcBlockById($blockId) : null;
    }

    /** @param Collection<int, AcademicEgcBlockData> $blocks @return Collection<int, AcademicEgcBlockData> */
    private static function chargeable(Collection $blocks): Collection
    {
        $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($blocks);

        return $blocks
            ->filter(fn (AcademicEgcBlockData $block): bool => $chargesByBlock->has($block->id))
            ->values();
    }

    private static function academicSources(): AcademicFinanceChargeSourceGateway
    {
        return app(AcademicFinanceChargeSourceGateway::class);
    }
}
