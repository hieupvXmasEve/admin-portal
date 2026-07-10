<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillLegacyEgcRetakeDiscountEntitlementsAction;
use Illuminate\Console\Command;

class BackfillLegacyEgcRetakeDiscountEntitlementsCommand extends Command
{
    protected $signature = 'finance:backfill-legacy-egc-retake-discount-entitlements
        {--dry-run : Report conversions without writing entitlements}
        {--report-mismatches : Show skipped / mismatch rows in a table}';

    protected $description = 'Link legacy egc_retake invoice discounts to FinanceDiscountEntitlement carriers';

    public function handle(BackfillLegacyEgcRetakeDiscountEntitlementsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $action->run($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['checked'] === 0) {
            $this->info('No legacy egc_retake discounts matched the conversion scope.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy egc_retake discount(s): converted %d, already converted %d, skipped %d, mismatch(es) %d.',
            $prefix,
            $result['checked'],
            $result['converted'],
            $result['already_converted'],
            $result['skipped'],
            $result['mismatches'],
        ));

        if ($result['mismatches'] > 0 || $result['skipped'] > 0) {
            $this->warn(sprintf(
                'Conversion completed with %d mismatch(es) and %d skipped discount(s).',
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
                $detail['invoice_discount_id'] ?? '—',
                $detail['invoice_id'] ?? '—',
                $detail['reason'] ?? '—',
                $detail['source_kind'] ?? '—',
                $detail['source_ref'] ?? '—',
                $detail['discount_amount'] ?? '—',
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->table(
            ['Discount', 'Invoice', 'Reason', 'Source kind', 'Source ref', 'Amount'],
            $rows,
        );
    }
}
