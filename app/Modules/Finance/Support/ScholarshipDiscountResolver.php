<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\ScholarshipDefinition;

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
}
