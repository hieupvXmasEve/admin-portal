<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use Illuminate\Support\Collection;

/**
 * Classify a legacy scholarship_credit negative charge by its settlement carrier
 * (ADR-0030 / wave 4).
 *
 * Fee-specific scholarships already live on invoice_discounts/discount_allocations
 * (discount_type=scholarship). Grant-like scholarships reduce outstanding only via
 * the negative charge line (no discount carrier). Evidence is returned for audit
 * so backfill and dry-runs can explain every decision.
 */
final class ScholarshipCarrierClassifier
{
    private const RECONCILE_TOLERANCE = 0.01;

    public function classify(FinanceCharge $charge): ScholarshipCarrierClassification
    {
        if ($charge->charge_type !== FinanceCharge::TYPE_SCHOLARSHIP_CREDIT) {
            return new ScholarshipCarrierClassification(
                carrier: ScholarshipCarrierClassification::CARRIER_SKIPPED,
                reason: ScholarshipCarrierClassification::REASON_WRONG_CHARGE_TYPE,
                amount: abs((float) $charge->amount),
                evidence: [
                    'charge_id' => $charge->id,
                    'charge_type' => $charge->charge_type,
                ],
            );
        }

        if ($charge->student_id === null) {
            return new ScholarshipCarrierClassification(
                carrier: ScholarshipCarrierClassification::CARRIER_SKIPPED,
                reason: ScholarshipCarrierClassification::REASON_MISSING_STUDENT_ID,
                amount: abs((float) $charge->amount),
                evidence: ['charge_id' => $charge->id],
            );
        }

        $amount = abs((float) $charge->amount);
        if ($amount <= 0) {
            return new ScholarshipCarrierClassification(
                carrier: ScholarshipCarrierClassification::CARRIER_SKIPPED,
                reason: ScholarshipCarrierClassification::REASON_NON_POSITIVE_AMOUNT,
                amount: $amount,
                evidence: [
                    'charge_id' => $charge->id,
                    'raw_amount' => (float) $charge->amount,
                ],
            );
        }

        $negativeLines = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->get();

        $invoiceIds = $negativeLines
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $match = $this->findDiscountCarriers($charge, $invoiceIds, $amount);

        if ($match['discounts']->isNotEmpty()) {
            $discountIds = $match['discounts']->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            return new ScholarshipCarrierClassification(
                carrier: ScholarshipCarrierClassification::CARRIER_DISCOUNT,
                reason: ScholarshipCarrierClassification::REASON_DISCOUNT_ALLOCATION_CARRIER,
                amount: $amount,
                invoiceIds: $invoiceIds,
                carrierDiscountIds: $discountIds,
                evidence: [
                    'charge_id' => $charge->id,
                    'student_id' => $charge->student_id,
                    'semester_id' => $charge->semester_id,
                    'legacy_amount' => (float) $charge->amount,
                    'invoice_ids' => $invoiceIds,
                    'negative_line_ids' => $negativeLines->pluck('id')->values()->all(),
                    'carrier_invoice_discount_ids' => $discountIds,
                    'match_method' => $match['method'],
                    'discount_type' => 'scholarship',
                    'source_type' => $charge->source_type,
                    'source_id' => $charge->source_id,
                    'target_entitlement' => 'FinanceDiscountEntitlement',
                ],
            );
        }

        return new ScholarshipCarrierClassification(
            carrier: ScholarshipCarrierClassification::CARRIER_CREDIT,
            reason: ScholarshipCarrierClassification::REASON_NO_DISCOUNT_ALLOCATION_CARRIER,
            amount: $amount,
            invoiceIds: $invoiceIds,
            carrierDiscountIds: [],
            evidence: [
                'charge_id' => $charge->id,
                'student_id' => $charge->student_id,
                'semester_id' => $charge->semester_id,
                'legacy_amount' => (float) $charge->amount,
                'invoice_ids' => $invoiceIds,
                'negative_line_ids' => $negativeLines->pluck('id')->values()->all(),
                'carrier_invoice_discount_ids' => [],
                'match_method' => null,
                'source_type' => $charge->source_type,
                'source_id' => $charge->source_id,
                'target_entitlement' => 'FinanceCreditEntitlement',
            ],
        );
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array{discounts:Collection<int, InvoiceDiscount>, method:?string}
     */
    private function findDiscountCarriers(FinanceCharge $charge, array $invoiceIds, float $amount): array
    {
        if ($invoiceIds === []) {
            return ['discounts' => collect(), 'method' => null];
        }

        $query = InvoiceDiscount::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->where('discount_type', 'scholarship')
            ->where(function ($statusQuery): void {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            // Already owned by another entitlement must not be rebound.
            ->whereNull('finance_discount_entitlement_id');

        // Prefer award-linked discount when the charge points at a scholarship award.
        // Require amount alignment so an under-sized award discount cannot claim a
        // larger scholarship_credit reduction as fee-specific.
        if (
            $charge->source_type === StudentScholarshipAward::class
            && $charge->source_id !== null
        ) {
            $byAward = (clone $query)
                ->where('reference_id', (int) $charge->source_id)
                ->where(function ($sourceQuery): void {
                    $sourceQuery
                        ->where('discount_source', StudentScholarshipAward::class)
                        ->orWhereNull('discount_source')
                        ->orWhere('discount_source', 'scholarship');
                })
                ->get();

            $matched = $this->withPositiveNetAllocations($byAward)
                ->filter(fn (InvoiceDiscount $discount): bool => abs((float) $discount->amount - $amount) <= self::RECONCILE_TOLERANCE)
                ->values();

            if ($matched->isNotEmpty()) {
                // One reduction → one carrier header (never rebind siblings).
                return ['discounts' => $matched->take(1)->values(), 'method' => 'award_reference'];
            }
        }

        // Exact amount match on the same invoice(s). Never fall back to
        // "all scholarship discounts on invoice" — that can rebind unrelated carriers.
        $byAmount = (clone $query)
            ->where('amount', $amount)
            ->get();

        $matched = $this->withPositiveNetAllocations($byAmount);
        if ($matched->isNotEmpty()) {
            // One charge maps to at most one discount header of the same amount.
            return ['discounts' => $matched->take(1)->values(), 'method' => 'amount_match'];
        }

        return ['discounts' => collect(), 'method' => null];
    }

    /**
     * @param  Collection<int, InvoiceDiscount>  $discounts
     * @return Collection<int, InvoiceDiscount>
     */
    private function withPositiveNetAllocations(Collection $discounts): Collection
    {
        return $discounts
            ->filter(function (InvoiceDiscount $discount): bool {
                $net = (float) DiscountAllocation::query()
                    ->where('invoice_discount_id', $discount->id)
                    ->sum('amount');

                return $net > self::RECONCILE_TOLERANCE;
            })
            ->values();
    }
}
