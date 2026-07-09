<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Pricing;

use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;

/**
 * Types that need runtime catalog pricing but have no currently usable active rule.
 * Surfaces the retake_fee prod-500 class of failure before intake.
 *
 * "Usable" = is_active and within effective_from / effective_until window (same as FinancePricingCatalog).
 */
class ListPricingCoverageWarningsQuery
{
    /**
     * @return list<array{obligation_type: string, label: string, pricing_strategy: string}>
     */
    public function handle(): array
    {
        $requiredTypes = ObligationTypeRegistry::typesRequiringPricingCatalog();

        if ($requiredTypes === []) {
            return [];
        }

        $now = now();

        $typesWithUsableRule = FinancePricingCatalogItem::query()
            ->whereIn('obligation_type', $requiredTypes)
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $now);
            })
            ->distinct()
            ->pluck('obligation_type')
            ->all();

        $covered = array_fill_keys($typesWithUsableRule, true);
        $warnings = [];

        foreach (ObligationTypeRegistry::requiringPricingCatalog() as $definition) {
            if (isset($covered[$definition->type])) {
                continue;
            }

            $warnings[] = [
                'obligation_type' => $definition->type,
                'label' => $definition->label,
                'pricing_strategy' => $definition->pricingStrategy->value,
            ];
        }

        return $warnings;
    }
}
