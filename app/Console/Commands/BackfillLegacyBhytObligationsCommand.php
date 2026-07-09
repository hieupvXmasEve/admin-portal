<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyBhytObligationsAction;
use Illuminate\Console\Command;

class BackfillLegacyBhytObligationsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-bhyt-obligations
        {--dry-run : Report changes without writing FinanceObligation rows or charge links}
        {--report-mismatches : Show skipped / mismatch rows in a table}';

    protected $description = 'Backfill legacy bhyt charges into FinanceObligation rows without creating charges';

    /**
     * Execute the console command.
     */
    public function handle(BackfillLegacyBhytObligationsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->run($dryRun);

        if ($result['checked'] === 0) {
            $this->info('No legacy bhyt debit charges matched the backfill scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy bhyt debit charge(s): created %d, already linked %d, linked existing %d, skipped %d, mismatch(es) %d.',
            $prefix,
            $result['checked'],
            $result['created'],
            $result['already_linked'],
            $result['linked_existing'],
            $result['skipped'],
            $result['mismatches'],
        ));

        if ($result['mismatches'] > 0 || $result['skipped'] > 0) {
            $this->warn(sprintf(
                'Backfill completed with %d mismatch(es) and %d skipped charge(s).',
                $result['mismatches'],
                $result['skipped'],
            ));

            if ((bool) $this->option('report-mismatches')) {
                $this->reportDetails($result['details']);
            } else {
                $this->line('Run with --report-mismatches to inspect them.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function reportDetails(array $details): void
    {
        $rows = [];

        foreach ($details as $detail) {
            if (! in_array($detail['status'] ?? null, ['mismatch', 'skipped'], true)) {
                continue;
            }

            $rows[] = [
                $detail['charge_id'] ?? '—',
                $detail['charge_type'] ?? '—',
                $detail['reason'] ?? '—',
                $detail['source_kind'] ?? '—',
                $detail['source_ref'] ?? '—',
                $detail['charge_amount'] ?? '—',
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->table(
            ['Charge', 'Type', 'Reason', 'Source kind', 'Source ref', 'Charge amount'],
            $rows,
        );
    }
}
