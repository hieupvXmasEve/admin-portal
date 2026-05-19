<?php

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

class ScholarshipFixedAmountCalculator
{
    private const ROUNDING_INCREMENT = 1000;

    public static function calculate(float|int|string $totalAmount, int|string $totalTerms): int
    {
        $amount = (float) $totalAmount;
        $terms = (int) $totalTerms;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Total amount must be greater than zero.');
        }

        if ($terms < 1) {
            throw new InvalidArgumentException('Total terms must be at least one.');
        }

        return (int) (ceil(($amount / $terms) / self::ROUNDING_INCREMENT) * self::ROUNDING_INCREMENT);
    }
}
