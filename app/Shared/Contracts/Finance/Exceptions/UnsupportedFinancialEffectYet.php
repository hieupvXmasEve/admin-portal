<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\Exceptions;

use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use RuntimeException;

class UnsupportedFinancialEffectYet extends RuntimeException
{
    public static function forEffect(FinancialEffect $effect): self
    {
        return new self("Finance intake does not support {$effect->value} effects yet.");
    }
}
