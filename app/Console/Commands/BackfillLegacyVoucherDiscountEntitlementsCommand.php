<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyVoucherDiscountEntitlementsAction;
use Illuminate\Console\Command;

class BackfillLegacyVoucherDiscountEntitlementsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-voucher-discount-entitlements
        {--dry-run : Report conversions without writing entitlements or voiding charges}
        {--report-mismatches : Show skipped / mismatch rows in a table}';

    protected $description = 'Convert legacy voucher_credit negative charges with discount carriers into FinanceDiscountEntitlement';

    public function handle(BackfillLegacyVoucherDiscountEntitlementsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $action->run($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['checked'] === 0) {
            $this->info('No active legacy voucher_credit charges matched the conversion scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy voucher_credit charge(s): converted %d, already converted %d, skipped %d, mismatch(es) %d.',
            $prefix,
            $result['checked'],
            $result['converted'],
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
