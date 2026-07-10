<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Pricing;

use App\Modules\Finance\Support\EgcLevelFeeResolver;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;

/**
 * Finance-owned EGC level fee pricing (FIN-06 / wave 5).
 *
 * Sources send pricing facts only (level_number + generation semantics).
 * Final amount is resolved from Unit.base_fee via {@see EgcLevelFeeResolver},
 * never accepted as a caller-supplied final price.
 */
final class EgcLevelFeePricingResolver
{
    public function __construct(
        private readonly EgcLevelFeeResolver $egcLevelFeeResolver,
    ) {}

    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    public function price(FinanceIntakeData $intake): array
    {
        $levelNumber = $this->requiredIntFact($intake, 'level_number');
        $amount = $this->egcLevelFeeResolver->resolve($levelNumber);
        $currency = $this->optionalCurrency($intake);
        $ruleVersion = sprintf('egc_level_fee:level:%d', $levelNumber);

        return [
            'amount' => $amount,
            'currency' => $currency,
            'rule_version' => $ruleVersion,
            'snapshot' => [
                'pricing_source' => 'egc_level_fee_resolver',
                'level_number' => $levelNumber,
                'amount' => $amount,
                'currency' => $currency,
                'obligation_type' => $intake->obligation_type,
                'facts' => $intake->facts,
            ],
        ];
    }

    private function requiredIntFact(FinanceIntakeData $intake, string $key): int
    {
        if (! array_key_exists($key, $intake->facts)) {
            throw InvalidFinanceIntakePayload::missingFact($key);
        }

        return (int) $intake->facts[$key];
    }

    private function optionalCurrency(FinanceIntakeData $intake): string
    {
        $currency = $intake->facts['currency'] ?? 'VND';

        if (! is_string($currency) || $currency === '') {
            return 'VND';
        }

        return strtoupper($currency);
    }
}
