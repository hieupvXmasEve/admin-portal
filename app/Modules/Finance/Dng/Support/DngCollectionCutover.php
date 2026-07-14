<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

use App\Modules\Finance\Dng\Exceptions\DngCollectionCutoverBlocked;

final class DngCollectionCutover
{
    public function allowsNewCollection(): bool
    {
        return (bool) config('finance.dng.enabled', true);
    }

    public function assertCollectionAllowed(): void
    {
        if (! $this->allowsNewCollection()) {
            throw new DngCollectionCutoverBlocked;
        }
    }
}
