<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Pricing\SeedBaselinePricingCatalogAction;
use Illuminate\Console\Command;

class SeedFinancePricingCatalogCommand extends Command
{
    protected $signature = 'finance:seed-pricing-catalog
        {--dry-run : Report rows that would be created without writing}';

    protected $description = 'Seed baseline Finance pricing catalog rows for local/prod parity (idempotent; never edits existing versions)';

    public function handle(SeedBaselinePricingCatalogAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->handle($dryRun);

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sBaseline pricing catalog: created %d, skipped %d.',
            $prefix,
            $result['created'],
            $result['skipped'],
        ));

        if ($result['rows'] !== []) {
            $this->table(
                ['obligation_type', 'rule_version', 'status'],
                array_map(
                    static fn (array $row): array => [
                        $row['obligation_type'],
                        $row['rule_version'],
                        $row['status'],
                    ],
                    $result['rows'],
                ),
            );
        }

        return self::SUCCESS;
    }
}
