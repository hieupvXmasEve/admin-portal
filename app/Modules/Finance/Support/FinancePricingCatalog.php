<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\PricingStrategy;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\Pricing\EgcLevelFeePricingResolver;
use App\Modules\Finance\Support\Pricing\TuitionTermPricingResolver;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use RuntimeException;

class FinancePricingCatalog
{
    public function __construct(
        private readonly TuitionTermPricingResolver $tuitionTermPricingResolver,
        private readonly EgcLevelFeePricingResolver $egcLevelFeePricingResolver,
    ) {}

    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    public function price(FinanceIntakeData $intake): array
    {
        $definition = ObligationTypeRegistry::get($intake->obligation_type);

        return match ($definition->pricingStrategy) {
            PricingStrategy::CatalogFixed, PricingStrategy::CatalogFacts => $this->priceFromCatalogOrFail($intake),
            PricingStrategy::StaffSupplied => $this->priceFromStaffSupplied($intake),
            PricingStrategy::GeneratorAmount => $this->priceFromGeneratorAmount($intake),
            PricingStrategy::PolicyComputed => $this->priceFromPolicyComputed($intake),
            PricingStrategy::NotApplicable => throw new RuntimeException(
                "Pricing strategy [{$definition->pricingStrategy->value}] is not supported for intake type [{$intake->obligation_type}] yet."
            ),
        };
    }

    /**
     * Whether intake facts may carry amount/currency for this obligation type.
     * Catalog-priced types forbid them (ADR-0026); staff/generator/policy strategies allow amount.
     * tuition_term and egc_level_fee are GeneratorAmount transitional wiring but price from
     * Finance-owned resolvers/catalog facts only — callers must not supply final amounts.
     */
    public function allowsAmountInFacts(string $obligationType): bool
    {
        if (in_array($obligationType, [
            FinanceCharge::TYPE_TUITION_TERM,
            FinanceCharge::TYPE_EGC_LEVEL_FEE,
        ], true)) {
            return false;
        }

        $strategy = ObligationTypeRegistry::get($obligationType)->pricingStrategy;

        return in_array($strategy, [
            PricingStrategy::StaffSupplied,
            PricingStrategy::GeneratorAmount,
            PricingStrategy::PolicyComputed,
        ], true);
    }

    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    private function priceFromCatalogOrFail(FinanceIntakeData $intake): array
    {
        $catalogPriced = $this->priceFromCatalogItems($intake);

        if ($catalogPriced !== null) {
            return $catalogPriced;
        }

        throw new RuntimeException("No active Finance pricing catalog item for {$intake->obligation_type}.");
    }

    /**
     * Generator/batch amount, with tuition plan as Finance-owned fallback for tuition_term.
     *
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    private function priceFromGeneratorAmount(FinanceIntakeData $intake): array
    {
        if (array_key_exists('amount', $intake->facts)) {
            return $this->priceFromSuppliedAmount($intake, PricingStrategy::GeneratorAmount, 'generator');
        }

        $catalogPriced = $this->priceFromCatalogItems($intake);
        if ($catalogPriced !== null) {
            return $catalogPriced;
        }

        // Tuition term: Finance-owned TuitionPlan is the operational pricing catalog
        // until every plan/term is mirrored into finance_pricing_catalog_items.
        if ($intake->obligation_type === FinanceCharge::TYPE_TUITION_TERM) {
            return $this->tuitionTermPricingResolver->price($intake);
        }

        // EGC level fee: Finance-owned Unit.base_fee (+ flat fallback) via resolver.
        if ($intake->obligation_type === FinanceCharge::TYPE_EGC_LEVEL_FEE) {
            return $this->egcLevelFeePricingResolver->price($intake);
        }

        throw new RuntimeException(
            "No pricing for {$intake->obligation_type}: generator amount fact missing and no catalog rule."
        );
    }

    /**
     * Staff-entered amount (manual_fee, adjustment, admission_fee).
     *
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    private function priceFromStaffSupplied(FinanceIntakeData $intake): array
    {
        return $this->priceFromSuppliedAmount($intake, PricingStrategy::StaffSupplied, 'staff_supplied');
    }

    /**
     * Policy-computed amount supplied by Finance after policy evaluation
     * (defer_credit preserve amount, scholarship grant, etc.).
     *
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    private function priceFromPolicyComputed(FinanceIntakeData $intake): array
    {
        return $this->priceFromSuppliedAmount($intake, PricingStrategy::PolicyComputed, 'policy_computed');
    }

    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    private function priceFromSuppliedAmount(
        FinanceIntakeData $intake,
        PricingStrategy $strategy,
        string $ruleSuffix,
    ): array {
        $amount = $this->requiredNumericAmount($intake);
        $currency = $this->optionalCurrency($intake);
        $ruleVersion = "{$intake->obligation_type}:{$ruleSuffix}";

        return [
            'amount' => $amount,
            'currency' => $currency,
            'rule_version' => $ruleVersion,
            'snapshot' => [
                'pricing_strategy' => $strategy->value,
                'obligation_type' => $intake->obligation_type,
                'amount' => $amount,
                'currency' => $currency,
                'facts' => $intake->facts,
            ],
        ];
    }

    /**
     * Prefer the most specific active catalog rule (most facts_match keys), then newest effective_from.
     *
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}|null
     */
    private function priceFromCatalogItems(FinanceIntakeData $intake): ?array
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

        if ($candidates->isEmpty()) {
            return null;
        }

        $matching = $candidates
            ->filter(fn (FinancePricingCatalogItem $item): bool => $this->matchesFacts($item, $intake->facts))
            ->sortByDesc(fn (FinancePricingCatalogItem $item): int => count($item->facts_match ?? []))
            ->values();

        $catalogItem = $matching->first();

        if (! $catalogItem instanceof FinancePricingCatalogItem) {
            return null;
        }

        return [
            'amount' => (float) $catalogItem->amount,
            'currency' => $catalogItem->currency,
            'rule_version' => $catalogItem->rule_version,
            'snapshot' => [
                'pricing_source' => 'pricing_catalog',
                'catalog_item_id' => $catalogItem->id,
                'catalog_rule_version' => $catalogItem->rule_version,
                'obligation_type' => $catalogItem->obligation_type,
                'amount' => (float) $catalogItem->amount,
                'currency' => $catalogItem->currency,
                'description' => $catalogItem->description,
                'facts_match' => $catalogItem->facts_match,
                'facts' => $intake->facts,
            ],
        ];
    }

    private function requiredNumericAmount(FinanceIntakeData $intake): float
    {
        if (! array_key_exists('amount', $intake->facts)) {
            throw InvalidFinanceIntakePayload::missingFact('amount');
        }

        $raw = $intake->facts['amount'];

        if (! is_numeric($raw)) {
            throw InvalidFinanceIntakePayload::invalidFact('amount', 'must be numeric');
        }

        return (float) $raw;
    }

    private function optionalCurrency(FinanceIntakeData $intake): string
    {
        $currency = $intake->facts['currency'] ?? 'VND';

        if (! is_string($currency) || $currency === '') {
            return 'VND';
        }

        return strtoupper($currency);
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
            if (! array_key_exists($key, $facts)) {
                return false;
            }

            if (! $this->factValuesEqual($facts[$key], $expected)) {
                return false;
            }
        }

        return true;
    }

    private function factValuesEqual(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (string) $actual === (string) $expected;
        }

        return $actual === $expected;
    }
}
