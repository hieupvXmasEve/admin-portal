<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

final readonly class StudentLifecycleFinanceCharge
{
    public function __construct(
        public int $id,
        public ?int $semesterId,
        public float $amount,
        public ?string $description,
        public ?string $effectiveAt,
        public float $paidAmount,
        public bool $isFullyPaid,
    ) {}

    /**
     * @return array{id: int, semester_id: int|null, amount: float, description: string|null, effective_at: string|null, paid_amount: float, is_fully_paid: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'semester_id' => $this->semesterId,
            'amount' => $this->amount,
            'description' => $this->description,
            'effective_at' => $this->effectiveAt,
            'paid_amount' => $this->paidAmount,
            'is_fully_paid' => $this->isFullyPaid,
        ];
    }
}
