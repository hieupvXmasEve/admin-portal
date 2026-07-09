<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Finance\Actions\Legacy\LinkLegacyRetakeDngToChargeAction;
use App\Modules\Finance\Actions\Legacy\ReconcileLegacyRetakeFeesAction;
use Illuminate\Console\Command;

/**
 * ACAD-RET-001 — link paid HL DNG requests and reconcile legacy retake_fee charges.
 */
class ReconcileLegacyRetakeFeesCommand extends Command
{
    protected $signature = 'academic:reconcile-legacy-retake-fees
        {--dry-run : Report what would be reconciled without writing}
        {--report-exceptions : List unmatched legacy charges in a table}';

    protected $description = 'Link paid HL DNG requests and reconcile legacy retake_fee charges into CourseRetakeRegistration sources (ACAD-RET-001)';

    public function handle(
        LinkLegacyRetakeDngToChargeAction $linkDng,
        ReconcileLegacyRetakeFeesAction $reconcile,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $linkResult = $linkDng->run($dryRun);
        if ($linkResult['checked'] > 0) {
            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->info(sprintf(
                '%sPaid HL without charge link: %d checked, %d linked, %d skipped, %d paid-synced.',
                $prefix,
                $linkResult['checked'],
                $linkResult['linked'],
                $linkResult['skipped'],
                $linkResult['paid_synced'],
            ));
        }

        $result = $reconcile->run($dryRun);

        if ($result['checked'] === 0) {
            $this->info('No legacy retake_fee charges need reconciliation.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy retake charge(s): %d reconciled, %d exception(s).',
            $prefix,
            $result['checked'],
            $result['reconciled'],
            $result['exceptions'],
        ));

        if ($result['exceptions'] > 0) {
            $this->warn("{$result['exceptions']} legacy retake charge(s) could not be matched safely.");

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
