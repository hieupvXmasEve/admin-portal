<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Pricing;

use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Support\Pricing\BaselinePricingCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Upsert baseline pricing rows for local/prod parity without touching registry behaviour.
 * Existing rows with the same (obligation_type, rule_version) are left unchanged (idempotent).
 */
class SeedBaselinePricingCatalogAction
{
    /**
     * @return array{created: int, skipped: int, rows: list<array{obligation_type: string, rule_version: string, status: string}>}
     */
    public function handle(bool $dryRun = false): array
    {
        $created = 0;
        $skipped = 0;
        $details = [];

        $apply = function () use (&$created, &$skipped, &$details, $dryRun): void {
            foreach (BaselinePricingCatalog::rows() as $row) {
                $exists = FinancePricingCatalogItem::query()
                    ->where('obligation_type', $row['obligation_type'])
                    ->where('rule_version', $row['rule_version'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $details[] = [
                        'obligation_type' => $row['obligation_type'],
                        'rule_version' => $row['rule_version'],
                        'status' => 'skipped',
                    ];

                    continue;
                }

                if (! $dryRun) {
                    FinancePricingCatalogItem::query()->create([
                        'obligation_type' => $row['obligation_type'],
                        'amount' => $row['amount'],
                        'currency' => $row['currency'],
                        'rule_version' => $row['rule_version'],
                        'description' => $row['description'],
                        'facts_match' => $row['facts_match'],
                        'is_active' => true,
                        'effective_from' => now()->subDay(),
                        'effective_until' => null,
                    ]);
                }

                $created++;
                $details[] = [
                    'obligation_type' => $row['obligation_type'],
                    'rule_version' => $row['rule_version'],
                    'status' => $dryRun ? 'would_create' : 'created',
                ];
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'rows' => $details,
        ];
    }
}
