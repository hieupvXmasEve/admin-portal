<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

final readonly class SettlementPositionRawEvidence
{
    public function __construct(
        public Money $gross,
        public Money $discount,
        public Money $cash,
        public Money $credit,
        public Money $remaining,
        public Money $reversed_discount_residue,
        public Money $inactive_credit_residue,
    ) {}

    public static function empty(): self
    {
        return new self(
            gross: Money::zero(),
            discount: Money::zero(),
            cash: Money::zero(),
            credit: Money::zero(),
            remaining: Money::zero(),
            reversed_discount_residue: Money::zero(),
            inactive_credit_residue: Money::zero(),
        );
    }
}
