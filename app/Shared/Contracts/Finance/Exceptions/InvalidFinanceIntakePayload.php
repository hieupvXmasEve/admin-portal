<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\Exceptions;

use InvalidArgumentException;

class InvalidFinanceIntakePayload extends InvalidArgumentException
{
    public static function missingFact(string $fact): self
    {
        return new self("Finance intake is missing required fact: {$fact}.");
    }

    public static function forbiddenPricingFact(string $fact): self
    {
        return new self("Finance intake fact must not supply pricing value: {$fact}.");
    }

    public static function forbiddenPayerFact(string $fact): self
    {
        return new self("Finance intake fact must not supply payer identity: {$fact}. Finance resolves the billing account.");
    }

    public static function invalidFact(string $fact, string $reason): self
    {
        return new self("Finance intake fact [{$fact}] is invalid: {$reason}.");
    }
}
