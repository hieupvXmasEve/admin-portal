<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Pricing;

use App\Modules\Finance\Models\FinancePricingCatalogItem;

/**
 * Activate a pricing rule version. Only is_active may change; other fields stay immutable.
 */
class ActivatePricingRuleVersionAction
{
    public function handle(FinancePricingCatalogItem $item): FinancePricingCatalogItem
    {
        if (! $item->is_active) {
            $item->is_active = true;
            $item->save();
        }

        return $item->refresh();
    }
}
