<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyRetakeResitObligationsAction;
use Illuminate\Console\Command;

class BackfillLegacyRetakeResitObligationsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-retake-resit-obligations
        {--dry-run : Report changes without writing FinanceObligation rows or charge links}
        {--report-mismatches : Show amount/source/link mismatches in a table}';

    protected $description = 'Backfill legacy retake_fee/exam_resit_fee charges into FinanceObligation rows without creating charges';

    /**
     * Execute the console command.
     */
    public function handle(BackfillLegacyRetakeResitObligationsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->run($dryRun);

        if ($result['checked'] === 0) {
            $this->info('No legacy retake/resit debit charges matched the backfill scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy retake/resit debit charge(s): created %d, already linked %d, linked existing %d, skipped %d, mismatch(es) %d.',
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
                $this->reportMismatches($result['details']);
            } else {
                $this->line('Run with --report-mismatches to inspect them.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function reportMismatches(array $details): void
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
                $detail['legacy_source_amount'] ?? '—',
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->table(
            ['Charge', 'Type', 'Reason', 'Source kind', 'Source ref', 'Charge amount', 'Source amount'],
            $rows,
        );
    }
}
