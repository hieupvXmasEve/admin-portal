<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class StudentLifecyclePreservePreview
{
    public function __construct(
        public string $source,
        public float $preserveAmount,
        public string $feePolicy,
    ) {}

    /** @return array{source: string, preserve_amount: float, fee_policy: string} */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'preserve_amount' => $this->preserveAmount,
            'fee_policy' => $this->feePolicy,
        ];
    }
}
