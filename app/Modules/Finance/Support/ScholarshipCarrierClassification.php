<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

/**
 * Auditable result of {@see ScholarshipCarrierClassifier} for one
 * legacy scholarship_credit charge (ADR-0030 / wave 4).
 *
 * Carrier is mutually exclusive:
 *  - discount: fee-specific scholarship whose settlement already lives on
 *    invoice_discounts / discount_allocations → FinanceDiscountEntitlement
 *  - credit: grant-like scholarship with no discount-allocation carrier →
 *    FinanceCreditEntitlement + credit applications
 *  - skipped: not convertible (missing student, non-positive amount, wrong type)
 */
final class ScholarshipCarrierClassification
{
    public const CARRIER_DISCOUNT = 'discount';

    public const CARRIER_CREDIT = 'credit';

    public const CARRIER_SKIPPED = 'skipped';

    public const REASON_DISCOUNT_ALLOCATION_CARRIER = 'discount_allocation_carrier';

    public const REASON_NO_DISCOUNT_ALLOCATION_CARRIER = 'no_discount_allocation_carrier';

    public const REASON_MISSING_STUDENT_ID = 'missing_student_id';

    public const REASON_NON_POSITIVE_AMOUNT = 'non_positive_amount';

    public const REASON_WRONG_CHARGE_TYPE = 'wrong_charge_type';

    /**
     * @param  list<int>  $invoiceIds
     * @param  list<int>  $carrierDiscountIds
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly string $carrier,
        public readonly string $reason,
        public readonly float $amount,
        public readonly array $invoiceIds = [],
        public readonly array $carrierDiscountIds = [],
        public readonly array $evidence = [],
    ) {}

    public function isDiscount(): bool
    {
        return $this->carrier === self::CARRIER_DISCOUNT;
    }

    public function isCredit(): bool
    {
        return $this->carrier === self::CARRIER_CREDIT;
    }

    public function isSkipped(): bool
    {
        return $this->carrier === self::CARRIER_SKIPPED;
    }

    /**
     * @return array{
     *     carrier:string,
     *     reason:string,
     *     amount:float,
     *     invoice_ids:list<int>,
     *     carrier_discount_ids:list<int>,
     *     evidence:array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'reason' => $this->reason,
            'amount' => $this->amount,
            'invoice_ids' => $this->invoiceIds,
            'carrier_discount_ids' => $this->carrierDiscountIds,
            'evidence' => $this->evidence,
        ];
    }
}
