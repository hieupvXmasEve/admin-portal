<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;

/**
 * Immutable money accumulator for one revenue bucket. Folds Settlement
 * Positions one at a time; an invalid line only ever increments
 * invalid_count/invalid_gross and never enters a money total (plan.md "Bẫy
 * phải tránh").
 */
final readonly class RevenuePeriodBucket
{
    public function __construct(
        public Money $gross,
        public Money $discount,
        public Money $cash,
        public Money $credit,
        public Money $remaining,
        public int $invalidCount,
        public Money $invalidGross,
    ) {}

    public static function empty(): self
    {
        $zero = Money::zero();

        return new self($zero, $zero, $zero, $zero, $zero, 0, $zero);
    }

    public function withPosition(SettlementPosition $position): self
    {
        if (! $position->isValid() || $position->amounts === null) {
            return new self(
                $this->gross,
                $this->discount,
                $this->cash,
                $this->credit,
                $this->remaining,
                $this->invalidCount + 1,
                $this->invalidGross->add($position->raw_evidence->gross),
            );
        }

        $amounts = $position->amounts;

        return new self(
            $this->gross->add($amounts->gross),
            $this->discount->add($amounts->discount),
            $this->cash->add($amounts->cash),
            $this->credit->add($amounts->credit),
            $this->remaining->add($amounts->remaining),
            $this->invalidCount,
            $this->invalidGross,
        );
    }

    public function merge(self $other): self
    {
        return new self(
            $this->gross->add($other->gross),
            $this->discount->add($other->discount),
            $this->cash->add($other->cash),
            $this->credit->add($other->credit),
            $this->remaining->add($other->remaining),
            $this->invalidCount + $other->invalidCount,
            $this->invalidGross->add($other->invalidGross),
        );
    }

    public function netBilled(): Money
    {
        return $this->gross->subtract($this->discount);
    }

    public function collectionRate(): ?float
    {
        $netBilled = $this->netBilled();
        if (! $netBilled->isPositive()) {
            return null;
        }

        return round(((float) $this->cash->amount) / ((float) $netBilled->amount), 4);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'gross' => (float) $this->gross->amount,
            'discount' => (float) $this->discount->amount,
            'net_billed' => (float) $this->netBilled()->amount,
            'cash' => (float) $this->cash->amount,
            'credit' => (float) $this->credit->amount,
            'outstanding' => (float) $this->remaining->amount,
            'collection_rate' => $this->collectionRate(),
            'invalid_count' => $this->invalidCount,
            'invalid_gross' => (float) $this->invalidGross->amount,
        ];
    }
}
