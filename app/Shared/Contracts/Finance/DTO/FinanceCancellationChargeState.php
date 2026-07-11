<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

/**
 * Finance-owned cancellation preconditions for a source-linked charge.
 * Academic (and other sources) must use this instead of reading Finance/DNG models.
 */
final readonly class FinanceCancellationChargeState
{
    public function __construct(
        public ?int $financeChargeId,
        public bool $hasActiveCharge,
        public bool $isPaid,
        public bool $hasUnpaidActiveCharge,
        public bool $requiresNoRefundAcknowledgement,
        public bool $requiresUnpaidVoidConfirmation,
    ) {}
}
