<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyScholarshipEntitlementsAction;
use Illuminate\Console\Command;

class BackfillLegacyScholarshipEntitlementsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-scholarship-entitlements
        {--dry-run : Report classifications/conversions without writing entitlements or voiding charges}
        {--report-mismatches : Show skipped / mismatch rows in a table}';

    protected $description = 'Classify and convert legacy scholarship_credit charges into discount or credit entitlements (ADR-0030)';

    public function handle(BackfillLegacyScholarshipEntitlementsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $action->run($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['checked'] === 0) {
            $this->info('No active legacy scholarship_credit charges matched the conversion scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy scholarship_credit charge(s): converted %d (discount %d, credit %d), already converted %d, skipped %d, mismatch(es) %d.',
            $prefix,
            $result['checked'],
            $result['converted'],
            $result['converted_discount'],
            $result['converted_credit'],
            $result['already_converted'],
            $result['skipped'],
            $result['mismatches'],
        ));

        if ($result['mismatches'] > 0 || $result['skipped'] > 0) {
            $this->warn(sprintf(
                'Conversion completed with %d mismatch(es) and %d skipped charge(s).',
                $result['mismatches'],
                $result['skipped'],
            ));

            if ((bool) $this->option('report-mismatches')) {
                $this->reportDetails($result['details']);
            } else {
                $this->line('Run with --report-mismatches to inspect them.');
            }
        }

        return $result['mismatches'] > 0 ? self::FAILURE : self::SUCCESS;
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

            $carrier = $detail['classification']['carrier']
                ?? $detail['carrier']
                ?? '—';

            $rows[] = [
                $detail['charge_id'] ?? '—',
                $detail['charge_type'] ?? '—',
                $carrier,
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
            ['Charge', 'Type', 'Carrier', 'Reason', 'Source kind', 'Source ref', 'Charge amount'],
            $rows,
        );
    }
}
