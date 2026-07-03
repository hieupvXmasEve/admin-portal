<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\EgcBlock;
use Illuminate\Support\Collection;

final class EgcBlockGenerationState
{
    public const NormalGeneration = 'normal_generation';

    public const AlreadyGenerated = 'already_generated';

    public const ReissueCandidate = 'reissue_candidate';

    public const Blocked = 'blocked';

    /**
     * @param  Collection<int, EgcBlock>  $blocks
     * @param  Collection<int, EgcBlock>  $reissueBlocks
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason,
        public readonly Collection $blocks,
        public readonly Collection $reissueBlocks,
        public readonly int $collectibleBlockCount,
    ) {}

    public function allowsNormalGeneration(): bool
    {
        return $this->status === self::NormalGeneration;
    }

    public function isAlreadyGenerated(): bool
    {
        return $this->status === self::AlreadyGenerated;
    }

    public function shouldReissue(): bool
    {
        return $this->status === self::ReissueCandidate && $this->reissueBlocks->isNotEmpty();
    }

    public function isBlocked(): bool
    {
        return $this->status === self::Blocked;
    }
}
