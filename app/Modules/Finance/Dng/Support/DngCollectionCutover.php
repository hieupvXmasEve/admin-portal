<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

use App\Modules\Finance\Dng\Exceptions\DngCollectionCutoverBlocked;

final class DngCollectionCutover
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_CUTOVER = 'cutover';

    public const MODE_OFF = 'off';

    public function mode(): string
    {
        return (string) config('finance.dng.collection_mode', self::MODE_LEGACY);
    }

    public function allowsNewCollection(): bool
    {
        return $this->mode() !== self::MODE_OFF;
    }

    public function isCutover(): bool
    {
        return $this->mode() === self::MODE_CUTOVER;
    }

    public function assertCollectionAllowed(): void
    {
        if (! $this->allowsNewCollection()) {
            throw new DngCollectionCutoverBlocked;
        }
    }

    public function assertLegacyCollectionAllowed(): void
    {
        if ($this->isCutover() || ! $this->allowsNewCollection()) {
            throw new DngCollectionCutoverBlocked;
        }
    }
}
