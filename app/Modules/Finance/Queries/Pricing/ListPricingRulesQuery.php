<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Pricing;

use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPricingRulesQuery
{
    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     obligation_types: list<array{value: string, label: string, pricing_strategy: string, requires_catalog: bool}>,
     * }
     */
    public function handle(?string $obligationType = null, int $perPage = 50): array
    {
        $query = FinancePricingCatalogItem::query()
            ->orderBy('obligation_type')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($obligationType !== null && $obligationType !== '' && $obligationType !== 'all') {
            $query->where('obligation_type', $obligationType);
        }

        return [
            'items' => $query->paginate(max(1, min($perPage, 100)))->withQueryString(),
            'obligation_types' => $this->obligationTypeOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, pricing_strategy: string, requires_catalog: bool}>
     */
    private function obligationTypeOptions(): array
    {
        $options = [];

        foreach (ObligationTypeRegistry::all() as $definition) {
            // Retired types stay in the registry for historical enum/read-model
            // parity but are not offered for new pricing rules.
            if ($definition->isRetired()) {
                continue;
            }

            $options[] = [
                'value' => $definition->type,
                'label' => $definition->label,
                'pricing_strategy' => $definition->pricingStrategy->value,
                'requires_catalog' => $definition->requiresPricingCatalog(),
            ];
        }

        return $options;
    }
}
