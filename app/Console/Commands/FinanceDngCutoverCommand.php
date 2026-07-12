<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillActiveDngMigrationAction;
use App\Modules\Finance\Dng\Support\DngActiveMigrationReport;
use App\Modules\Finance\Queries\Dng\DngActiveMigrationInventoryQuery;
use Illuminate\Console\Command;

final class FinanceDngCutoverCommand extends Command
{
    protected $signature = 'finance:dng-cutover
                            {--backfill : Apply only exact, safe reservation metadata and target links}
                            {--dry-run : Report changes without writing (default when --backfill is omitted)}
                            {--limit= : Limit the number of DNG requests inspected}
                            {--json : Print machine-readable output}';

    protected $description = 'Inventory and safely backfill active DNG requests before settlement cutover';

    public function handle(
        DngActiveMigrationInventoryQuery $inventory,
        BackfillActiveDngMigrationAction $backfill,
    ): int {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $report = $this->option('backfill') && ! $this->option('dry-run')
            ? $backfill->handle($limit)
            : $inventory->handle($limit);

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => $report->total,
                'counts' => $report->counts,
                'backfilled' => $report->backfilled,
                'safe_to_cutover' => $report->isSafeToCutover(),
                'inventory_complete' => $report->complete,
                'records' => $report->records,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->render($report);
        }

        return $report->isSafeToCutover() ? self::SUCCESS : self::FAILURE;
    }

    private function render(DngActiveMigrationReport $report): void
    {
        $this->info($report->backfilled > 0
            ? "Backfilled {$report->backfilled} exact DNG request(s); no provider call was made."
            : 'DNG cutover inventory is read-only.');
        $this->table(
            ['Metric', 'Count'],
            collect($report->counts)->sortKeys()->map(fn (int $count, string $key): array => [$key, $count])->values()->all(),
        );
        $this->line('Active requests inspected: '.$report->total);
        $this->line('Safe to cut over: '.($report->isSafeToCutover() ? 'yes' : 'no'));
    }
}
