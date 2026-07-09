<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Pricing;

use App\Modules\Finance\Models\FinanceCharge;

/**
 * Baseline pricing catalog rows for local/prod parity.
 *
 * Only amounts/currency/rule versions — never registry behaviour facts (ADR-0027).
 * Used by finance:seed-pricing-catalog; staff may create newer versions via Pricing Ops.
 *
 * @phpstan-type BaselineRow array{
 *     obligation_type: string,
 *     amount: float,
 *     currency: string,
 *     rule_version: string,
 *     description: string,
 *     facts_match: array<string, mixed>|null,
 * }
 */
final class BaselinePricingCatalog
{
    /**
     * @return list<BaselineRow>
     */
    public static function rows(): array
    {
        return [
            [
                'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
                'amount' => 1_500_000.0,
                'currency' => 'VND',
                'rule_version' => 'retake_fee:v1',
                'description' => 'Fixed retake fee (baseline)',
                'facts_match' => null,
            ],
            [
                'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
                'amount' => 750_000.0,
                'currency' => 'VND',
                'rule_version' => 'exam_resit_fee:v1',
                'description' => 'Fixed exam resit fee (baseline)',
                'facts_match' => null,
            ],
        ];
    }
}
