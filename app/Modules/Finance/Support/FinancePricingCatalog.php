<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use RuntimeException;

class FinancePricingCatalog
{
    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    public function price(FinanceIntakeData $intake): array
    {
        $candidates = FinancePricingCatalogItem::query()
            ->where('obligation_type', $intake->obligation_type)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', now());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $catalogItem = $candidates->first(fn (FinancePricingCatalogItem $item): bool => $this->hasFactMatch($item)
            && $this->matchesFacts($item, $intake->facts))
            ?? $candidates->first(fn (FinancePricingCatalogItem $item): bool => $this->matchesFacts($item, $intake->facts));

        if (! $catalogItem instanceof FinancePricingCatalogItem) {
            throw new RuntimeException("No active Finance pricing catalog item for {$intake->obligation_type}.");
        }

        return [
            'amount' => (float) $catalogItem->amount,
            'currency' => $catalogItem->currency,
            'rule_version' => $catalogItem->rule_version,
            'snapshot' => [
                'catalog_item_id' => $catalogItem->id,
                'catalog_rule_version' => $catalogItem->rule_version,
                'obligation_type' => $catalogItem->obligation_type,
                'amount' => (float) $catalogItem->amount,
                'currency' => $catalogItem->currency,
                'description' => $catalogItem->description,
                'facts' => $intake->facts,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function matchesFacts(FinancePricingCatalogItem $item, array $facts): bool
    {
        $matchFacts = $item->facts_match ?? [];

        if ($matchFacts === []) {
            return true;
        }

        foreach ($matchFacts as $key => $expected) {
            if (! array_key_exists($key, $facts) || $facts[$key] !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function hasFactMatch(FinancePricingCatalogItem $item): bool
    {
        return ($item->facts_match ?? []) !== [];
    }
}
