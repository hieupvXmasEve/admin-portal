<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

final readonly class SettlementPositionAmounts
{
    public function __construct(
        public Money $gross,
        public Money $discount,
        public Money $cash,
        public Money $credit,
        public Money $remaining,
    ) {}
}
