<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

/**
 * Outcome of a scholarship adjustment intake. `accepted` is true whenever the
 * adjustment row was persisted — including the finance_review_required outcome
 * (a ledger refusal must never destroy the approval). Academic writes its
 * dossier status from `status`, not from exceptions.
 */
final readonly class ScholarshipAdjustmentResult
{
    public function __construct(
        public bool $accepted,
        /** pending_apply|applied|finance_review_required when accepted; rejection code otherwise. */
        public string $status,
        public ?int $adjustment_id,
        public ?string $message = null,
    ) {}

    public static function rejected(string $code, string $message): self
    {
        return new self(false, $code, null, $message);
    }
}
