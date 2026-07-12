<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Console\Command;
use Throwable;

/**
 * NT4 / DB-14 / DB-15: rebuild the student_invoices.cached_* snapshot from the
 * canonical Settlement Position (cash and credit remain separate).
 *
 * The cache columns are never the source of truth — this command makes them
 * disposable and rebuildable on demand, and clears any stale cached_paid_at on
 * invoices that are no longer paid. Safe to run repeatedly.
 */
class RebuildInvoiceSnapshots extends Command
{
    protected $signature = 'finance:rebuild-invoice-snapshots
        {--semester= : Limit to a single semester_id}
        {--campus= : Limit to a single campus_id}
        {--chunk=200 : Rows processed per chunk}
        {--dry-run : Inspect canonical projections without writing cache columns}';

    protected $description = 'Rebuild student_invoices cached_* snapshot columns from the ledger (NT4)';

    public function handle(SettlementService $settlementService): int
    {
        $semesterId = $this->option('semester') !== null ? (int) $this->option('semester') : null;
        $campusId = $this->option('campus') !== null ? (int) $this->option('campus') : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $query = StudentInvoice::query()
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations.invoiceDiscount'])
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($campusId, fn ($q) => $q->forCampus($campusId))
            ->orderBy('id');

        $total = (clone $query)->toBase()->count();

        if ($total === 0) {
            $this->info('No invoices matched the given filters. Nothing to rebuild.');

            return self::SUCCESS;
        }

        $mode = $dryRun ? 'Checking' : 'Rebuilding';
        $this->info("{$mode} cached_* snapshot for {$total} invoice(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $scanned = 0;
        $drifted = 0;
        $invalid = 0;
        $rebuilt = 0;
        $failed = 0;

        $query->chunkById($chunkSize, function ($invoices) use (
            $settlementService,
            $dryRun,
            &$scanned,
            &$drifted,
            &$invalid,
            &$rebuilt,
            &$failed,
            $bar,
        ) {
            foreach ($invoices as $invoice) {
                $scanned++;

                try {
                    $snapshot = $settlementService->deriveInvoiceCacheSnapshot($invoice);
                    $isDrifted = $settlementService->snapshotDriftsFromCache($invoice, $snapshot);

                    if ($isDrifted) {
                        $drifted++;
                        $this->newLine();
                        $this->line(sprintf(
                            'Invoice #%d drift: cached total=%s, cash=%s; canonical total=%s, cash=%s',
                            $invoice->id,
                            $invoice->cached_total_amount,
                            $invoice->cached_paid_amount,
                            number_format($snapshot['net'], 2, '.', ''),
                            number_format($snapshot['paid'], 2, '.', ''),
                        ));
                    }

                    if (! $dryRun) {
                        $settlementService->recalculateInvoiceSnapshot($invoice);
                        $rebuilt++;
                    }
                } catch (\RuntimeException $e) {
                    $invalid++;
                    $this->newLine();
                    $this->warn("Invoice #{$invoice->id} invalid: {$e->getMessage()}");
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Invoice #{$invoice->id} failed: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Scanned: {$scanned}. Drifted: {$drifted}. Rebuilt: {$rebuilt}. Invalid: {$invalid}. Failed: {$failed}.");

        return $invalid === 0 && $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
