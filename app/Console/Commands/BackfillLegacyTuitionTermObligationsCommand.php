<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyTuitionTermObligationsAction;
use Illuminate\Console\Command;

class BackfillLegacyTuitionTermObligationsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-tuition-term-obligations
        {--dry-run : Report changes without writing FinanceObligation rows or charge links}
        {--report-mismatches : Show skipped / mismatch rows in a table}';

    protected $description = 'Backfill legacy tuition_term charges into FinanceObligation rows and hard-gate settlement reconciliation';

    /**
     * Execute the console command.
     *
     * Exit FAILURE when any settlement hard-gate mismatches remain (wave-3 closure).
     */
    public function handle(BackfillLegacyTuitionTermObligationsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->run($dryRun);

        if ($result['checked'] === 0) {
            $this->info('No legacy tuition_term charges matched the backfill scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy tuition_term charge(s): created %d, already linked %d, linked existing %d, skipped %d, mismatch(es) %d.',
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

        if ($result['mismatches'] > 0) {
            $this->error(
                'Reconciliation hard-gate failed: settlement outstanding does not match old computed balance for one or more rows. Fix or accept exceptions before closing wave 3.'
            );

            return self::FAILURE;
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
                $detail['old_balance'] ?? '—',
                $detail['derived_outstanding'] ?? '—',
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->table(
            ['Charge', 'Type', 'Reason', 'Source kind', 'Source ref', 'Charge amount', 'Old balance', 'Derived outstanding'],
            $rows,
        );
    }
}
