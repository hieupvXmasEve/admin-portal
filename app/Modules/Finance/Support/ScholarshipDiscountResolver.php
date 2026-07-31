<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\ScholarshipDefinition;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Models\StudentInvoice;

/**
 * Single source of truth for turning a scholarship definition into a money
 * discount for one charge (FIN-04 / FIN-07).
 *
 * Every charge-generation path (CreateFinanceChargeAction, batch, major) and
 * every preview must run scholarship math through here so preview and execute
 * always agree, and so the discount can never exceed the charge it applies to
 * (an uncapped fixed_amount or >100% definition would otherwise create a
 * negative balance — see invariant INV-8).
 */
class ScholarshipDiscountResolver
{
    /**
     * Resolve the capped scholarship discount for a single charge.
     *
     * - percentage: baseAmount * definition.amount / 100
     * - fixed:      definition.amount (flat value)
     *
     * The result is always clamped to [0, baseAmount].
     */
    public function resolve(ScholarshipDefinition $definition, float $baseAmount): float
    {
        if ($baseAmount <= 0) {
            return 0.0;
        }

        $rawValue = (float) $definition->amount;

        $discount = $definition->type === 'percentage'
            ? ($baseAmount * $rawValue) / 100
            : $rawValue;

        return max(0.0, min($baseAmount, $discount));
    }

    /**
     * Resolve the discount for a charge honoring a per-semester adjustment.
     *
     * With no adjustment this is exactly resolve(). With one, the adjusted
     * amount is computed using the SNAPSHOT type semantics from the adjustment
     * (the award may have mutated since approval — snapshots win), clamped to
     * [0, baseAmount] and never above the unadjusted resolution.
     *
     * May legitimately return 0.0 (full suspension). Callers MUST NOT treat 0
     * as "no scholarship" — an existing ledger discount has to be zeroed.
     */
    public function resolveAdjusted(
        ScholarshipDefinition $definition,
        float $baseAmount,
        ?ScholarshipSemesterAdjustment $adjustment,
    ): float {
        if ($adjustment === null) {
            return $this->resolve($definition, $baseAmount);
        }

        if ($baseAmount <= 0) {
            return 0.0;
        }

        $adjustedValue = (float) $adjustment->adjusted_amount;

        $discount = $adjustment->original_type === ScholarshipSemesterAdjustment::TYPE_PERCENTAGE
            ? ($baseAmount * $adjustedValue) / 100
            : $adjustedValue;

        $discount = max(0.0, min($baseAmount, $discount));

        // An adjustment only ever reduces — never exceeds what the definition
        // would grant unadjusted.
        return min($discount, $this->resolve($definition, $baseAmount));
    }

    /**
     * The scholarship discount base for an invoice: TOTAL of its active
     * tuition_term lines. Every surface that writes the (single, upserted)
     * scholarship InvoiceDiscount row MUST use this base — per-charge bases
     * overwrite each other on multi-charge invoices and keep only the last
     * charge's discount.
     */
    public function invoiceTuitionBase(StudentInvoice $invoice): float
    {
        return (float) $invoice->invoiceLines()
            ->where('status', 'active')
            ->whereHas('charge', fn ($query) => $query
                ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                ->where('status', FinanceCharge::STATUS_ACTIVE))
            ->sum('amount_snapshot');
    }
}
