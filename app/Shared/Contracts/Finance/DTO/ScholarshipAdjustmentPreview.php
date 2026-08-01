<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

/**
 * What a proposed scholarship adjustment would cost the student, in money.
 *
 * Read-only projection for UI: it writes nothing and reuses the same resolver
 * the ledger uses, so what staff and students see cannot drift from what is
 * actually charged.
 *
 * `has_invoice = false` means the target semester has no tuition invoice yet.
 * The amounts are then all zero and callers MUST present the preview as
 * unavailable — never as "the student pays nothing".
 */
final readonly class ScholarshipAdjustmentPreview
{
    public function __construct(
        public bool $has_invoice,
        /** Total of active tuition_term lines — the base every discount is computed from. */
        public float $tuition_base,
        /** percentage|fixed_amount — how `original_amount` and the proposed value are read. */
        public ?string $original_type,
        public ?float $original_amount,
        /** Discount the student gets today, before this adjustment. */
        public float $current_discount,
        /** Discount after the proposed adjustment; null when no value was proposed. */
        public ?float $adjusted_discount,
        /** Tuition payable today (base - current_discount). */
        public float $payable_before,
        /** Tuition payable if the proposal is applied; null when no value was proposed. */
        public ?float $payable_after,
    ) {}

    /**
     * Extra tuition the student would owe. Zero means the proposal changes
     * nothing — which happens when the clamp to the tuition base absorbs the
     * reduction, and is worth surfacing rather than hiding.
     */
    public function delta(): ?float
    {
        if ($this->payable_after === null) {
            return null;
        }

        return round($this->payable_after - $this->payable_before, 2);
    }
}
