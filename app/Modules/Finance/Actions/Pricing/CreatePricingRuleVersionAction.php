<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Pricing;

use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use InvalidArgumentException;

/**
 * Create an immutable pricing rule version. Historical rows are never edited in place;
 * price changes always produce a new rule_version row.
 */
class CreatePricingRuleVersionAction
{
    /**
     * @param  array{
     *     obligation_type: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     rule_version: string,
     *     description?: string|null,
     *     facts_match?: array<string, mixed>|null,
     *     is_active?: bool,
     *     effective_from?: string|\DateTimeInterface|null,
     *     effective_until?: string|\DateTimeInterface|null,
     * }  $data
     */
    public function handle(array $data): FinancePricingCatalogItem
    {
        $obligationType = (string) $data['obligation_type'];

        if (! ObligationTypeRegistry::has($obligationType)) {
            throw new InvalidArgumentException("Unknown obligation type [{$obligationType}].");
        }

        $definition = ObligationTypeRegistry::get($obligationType);

        if (! $definition->requiresPricingCatalog()) {
            throw new InvalidArgumentException(
                "Obligation type [{$obligationType}] does not use the pricing catalog (strategy: {$definition->pricingStrategy->value})."
            );
        }

        return FinancePricingCatalogItem::query()->create([
            'obligation_type' => $obligationType,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'VND',
            'rule_version' => $data['rule_version'],
            'description' => $data['description'] ?? null,
            'facts_match' => $data['facts_match'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ]);
    }
}
