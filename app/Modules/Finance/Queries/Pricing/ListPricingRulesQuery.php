<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Pricing;

use App\Models\Unit;
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

        $paginator = $query->paginate(max(1, min($perPage, 100)))->withQueryString();

        return [
            'items' => $this->withUnitLabels($paginator),
            'obligation_types' => $this->obligationTypeOptions(),
        ];
    }

    /**
     * facts_match commonly scopes a rule to a single subject via {"unit_id": N}.
     * Resolve those ids to subject names in one batched query so the UI can show
     * "MATH101 - Calculus I" instead of raw JSON.
     */
    private function withUnitLabels(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $unitIds = collect($paginator->items())
            ->map(fn (FinancePricingCatalogItem $item) => data_get($item->facts_match, 'unit_id'))
            ->filter()
            ->unique()
            ->values();

        $unitLabels = $unitIds->isEmpty()
            ? collect()
            : Unit::query()->whereIn('id', $unitIds)->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Unit $unit) => [$unit->id => "{$unit->code} - {$unit->name}"]);

        return $paginator->through(function (FinancePricingCatalogItem $item) use ($unitLabels) {
            $unitId = data_get($item->facts_match, 'unit_id');

            return [
                ...$item->toArray(),
                'facts_match_label' => $unitId !== null ? ($unitLabels[$unitId] ?? "Unit #{$unitId}") : null,
            ];
        });
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
