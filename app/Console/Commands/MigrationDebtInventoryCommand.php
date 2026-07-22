<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\MigrationDebt\MigrationDebtGuard;
use App\Support\MigrationDebt\MigrationDebtInventory;
use Illuminate\Console\Command;

final class MigrationDebtInventoryCommand extends Command
{
    protected $signature = 'migration-debt:inventory
        {--check : Fail when approved debt baselines or metadata are violated}
        {--format=table : Output format: table or json}';

    protected $description = 'Report the read-only migration-debt inventory and optionally enforce its regression guard';

    public function __construct(
        private readonly MigrationDebtInventory $inventory,
        private readonly MigrationDebtGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error("Unsupported format '{$format}'. Use table or json.");

            return self::FAILURE;
        }

        $report = $this->inventory->scan();
        $guard = $this->guard->evaluate($report);

        if ($format === 'json') {
            $this->line(json_encode([
                'inventory' => $report,
                'guard' => $guard,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->renderTable($report, $guard);
        }

        if (! $this->option('check')) {
            return self::SUCCESS;
        }

        return $guard['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{
     *     coverage: array{runtime_surfaces: array<string, int>},
     *     debt: array{counts: array<string, int>}
     * }  $report
     * @param  array{passed: bool, errors: list<string>}  $guard
     */
    private function renderTable(array $report, array $guard): void
    {
        $rows = [];
        foreach (config('migration_debt.allowlists', []) as $rule => $entry) {
            $current = (int) ($report['debt']['counts'][$rule] ?? 0);
            $baseline = (int) $entry['baseline'];
            $passing = $this->guard->rulePasses($rule, $current);
            $rows[] = [$rule, $current, $baseline, $passing ? 'pass' : 'fail'];
        }

        $this->table(['Rule', 'Current', 'Baseline', 'Status'], $rows);
        $this->line('');
        $this->table(
            ['Runtime surface', 'Files'],
            collect($report['coverage']['runtime_surfaces'])
                ->map(fn (int $count, string $surface): array => [$surface, $count])
                ->values()
                ->all(),
        );

        if ($this->option('check')) {
            if ($guard['passed']) {
                $this->info('Migration-debt guard passed.');
            } else {
                $this->error('Migration-debt guard failed:');
                foreach ($guard['errors'] as $error) {
                    $this->line(' - '.$error);
                }
            }
        }
    }
}
