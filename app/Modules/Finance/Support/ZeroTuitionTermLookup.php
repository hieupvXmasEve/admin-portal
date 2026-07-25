<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\TuitionPlanTerm;

final class ZeroTuitionTermLookup
{
    private ?bool $exists = null;

    public function exists(): bool
    {
        return $this->exists ??= TuitionPlanTerm::query()
            ->where('amount', 0)
            ->exists();
    }
}
