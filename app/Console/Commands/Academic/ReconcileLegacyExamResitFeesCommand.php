<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Actions\CreateLegacyExamResitChargeFromPaidPtlAction;
use App\Modules\Academic\Actions\ReconcileLegacyExamResitFeesAction;
use Illuminate\Console\Command;

/**
 * ACAD-RET-001 Slice 6 — reconcile legacy `exam_resit_fee` charges into Academic
 * exam-resit sources, and report the charges that cannot be matched safely.
 *
 * Thin wrapper over {@see ReconcileLegacyExamResitFeesAction}; all matching and
 * write logic lives there so it is unit-testable without the console.
 *
 * Run AFTER `academic:backfill-failure-reason`, because matching requires the
 * failed records to be classified into the grade-fail lane.
 */
class ReconcileLegacyExamResitFeesCommand extends Command
{
    protected $signature = 'academic:reconcile-legacy-exam-resit-fees
        {--dry-run : Report what would be reconciled without writing}
        {--report-exceptions : List the unmatched legacy charges in a table}';

    protected $description = 'Reconcile legacy exam_resit_fee charges into Academic exam-resit sources, reporting unmatched charges (ACAD-RET-001 slice 6)';

    public function handle(
        CreateLegacyExamResitChargeFromPaidPtlAction $chargeFromPtl,
        ReconcileLegacyExamResitFeesAction $action,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $chargeResult = $chargeFromPtl->run($dryRun);
        if ($chargeResult['checked'] > 0) {
            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->info(sprintf(
                '%sPaid PTL without charge: %d checked, %d charge(s) created, %d skipped.',
                $prefix,
                $chargeResult['checked'],
                $chargeResult['created'],
                $chargeResult['skipped'],
            ));
        }

        $result = $action->run($dryRun);

        if ($result['checked'] === 0) {
            $this->info('No legacy exam_resit_fee charges need reconciliation.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy charge(s): %d reconciled, %d exception(s).',
            $prefix,
            $result['checked'],
            $result['reconciled'],
            $result['exceptions'],
        ));

        if ($result['exceptions'] > 0) {
            $this->warn("{$result['exceptions']} legacy charge(s) could not be matched to a student/unit/failed record safely.");

            if ((bool) $this->option('report-exceptions')) {
                $this->reportExceptions($result['details']);
            } else {
                $this->line('Run with --report-exceptions to inspect them.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private function reportExceptions(array $details): void
    {
        $rows = [];
        foreach ($details as $detail) {
            if (($detail['status'] ?? null) !== 'exception') {
                continue;
            }

            $rows[] = [
                $detail['charge_id'] ?? '—',
                $detail['student_id'] ?? '—',
                number_format((float) ($detail['amount'] ?? 0)),
                $detail['reason'] ?? '—',
                $detail['description'] ?? '—',
            ];
        }

        $this->table(['Charge', 'Student', 'Amount', 'Reason', 'Description'], $rows);
    }
}
