<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use InvalidArgumentException;

final readonly class Money
{
    public const VND = 'VND';

    public const VND_SCALE = 2;

    public function __construct(
        public string $amount,
        public string $currency,
        public int $scale,
        public int $minor_amount,
    ) {}

    public static function vnd(string|int $amount): self
    {
        return self::fromDecimal($amount, self::VND, self::VND_SCALE);
    }

    public static function zero(): self
    {
        return self::vnd(0);
    }

    public static function fromDecimal(string|int $amount, string $currency, int $scale): self
    {
        if ($currency !== self::VND) {
            throw new InvalidArgumentException('Settlement Position supports VND only.');
        }

        if ($scale !== self::VND_SCALE) {
            throw new InvalidArgumentException('Settlement Position VND values must use scale 2.');
        }

        $value = trim((string) $amount);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Money amount must be a decimal string or integer.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction = ''] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException('Money amount exceeds its supported scale.');
        }

        $minorAmount = (int) ltrim($whole.str_pad($fraction, $scale, '0'), '0');
        if ($negative && $minorAmount !== 0) {
            $minorAmount *= -1;
        }

        return new self(
            amount: self::formatMinorAmount($minorAmount, $scale),
            currency: $currency,
            scale: $scale,
            minor_amount: $minorAmount,
        );
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            amount: self::formatMinorAmount($this->minor_amount + $other->minor_amount, $this->scale),
            currency: $this->currency,
            scale: $this->scale,
            minor_amount: $this->minor_amount + $other->minor_amount,
        );
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            amount: self::formatMinorAmount($this->minor_amount - $other->minor_amount, $this->scale),
            currency: $this->currency,
            scale: $this->scale,
            minor_amount: $this->minor_amount - $other->minor_amount,
        );
    }

    public function isNegative(): bool
    {
        return $this->minor_amount < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor_amount > 0;
    }

    public function isZero(): bool
    {
        return $this->minor_amount === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor_amount > $other->minor_amount;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency || $this->scale !== $other->scale) {
            throw new InvalidArgumentException('Money values must use the same currency and scale.');
        }
    }

    private static function formatMinorAmount(int $minorAmount, int $scale): string
    {
        $negative = $minorAmount < 0;
        $digits = (string) abs($minorAmount);
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }
}
