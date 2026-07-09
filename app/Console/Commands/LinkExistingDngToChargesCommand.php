<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Link existing DNG payment requests to their source finance_charges via the
 * dng_payment_request_charges pivot table.
 *
 * Use this command once after deploying the Unified DNG Worklist feature to
 * backfill pivot links for legacy DNG records that were created without them.
 *
 * Algorithm:
 *   For each DngPaymentRequest WHERE chargeLinks = 0 AND finance_charge_id IS NULL
 *   AND status IN (pending, pushed_to_dng, paid_uninvoiced, paid_invoiced, reconciled):
 *
 *   1. Map fee_type → charge_types via DngFeeTypeOptions
 *   2. Find FinanceCharge WHERE student_id matches AND charge_type in mapped types
 *      AND status = active AND created within ±24h of DNG created_at
 *      AND semester_id = dng.semester_id (if set)
 *   3. If exactly 1 charge found → create pivot row with full amount
 *   4. If multiple charges found → create pivot rows distributing amount proportionally
 *   5. If none found → log warning, skip (requires manual review)
 *
 * Usage:
 *   ./scripts/dev.sh artisan dng:link-charges --dry-run
 *   ./scripts/dev.sh artisan dng:link-charges
 */
class LinkExistingDngToChargesCommand extends Command
{
    protected $signature = 'dng:link-charges
                            {--dry-run : Preview changes without writing to the database}
                            {--status=* : Limit to specific DNG statuses (default: pending,pushed_to_dng,paid_uninvoiced,paid_invoiced,reconciled)}
                            {--limit= : Maximum number of DNG records to process}';

    protected $description = 'Link existing DNG payment requests to finance_charges via the pivot table';

    private const DEFAULT_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    private int $linked = 0;

    private int $skipped = 0;

    private int $notFound = 0;

    private int $multi = 0;

    /** @var array<int, array{id: int, student_code: string, fee_type: string, amount: float, status: string, created_at: string}> */
    private array $notFoundRecords = [];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $statuses = (array) $this->option('status') ?: self::DEFAULT_STATUSES;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($isDryRun) {
            $this->warn('[DRY RUN] No data will be written.');
        }

        $this->info('Processing DNG records with status: '.implode(', ', $statuses));

        // Fetch unlinked DNG records
        $query = DngPaymentRequest::query()
            ->whereIn('status', $statuses)
            ->whereNull('finance_charge_id')
            ->whereDoesntHave('chargeLinks')
            ->with('student')
            ->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} unlinked DNG records to process.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function (Collection $records) use ($isDryRun, $bar) {
            foreach ($records as $dng) {
                $this->processRecord($dng, $isDryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Linked (1 charge found)',         $this->linked],
                ['Linked (multiple charges, proportional)', $this->multi],
                ['Skipped (already linked)',         $this->skipped],
                ['Not found (manual review needed)', $this->notFound],
            ]
        );

        if (! empty($this->notFoundRecords)) {
            $this->newLine();
            $this->warn('DNG records with no matching charge (manual review required):');
            $this->table(
                ['ID', 'Student', 'Fee Type', 'Amount', 'Status', 'Created'],
                array_map(fn ($r) => [
                    $r['id'], $r['student_code'], $r['fee_type'],
                    number_format($r['amount'], 0, ',', '.'), $r['status'], $r['created_at'],
                ], $this->notFoundRecords)
            );
        }

        if ($isDryRun) {
            $this->info('[DRY RUN] No data was written. Remove --dry-run to apply.');
        } else {
            $this->info('Done.');
        }

        return self::SUCCESS;
    }

    private function processRecord(DngPaymentRequest $dng, bool $isDryRun): void
    {
        // Double-check: skip if already linked via other path
        if ($dng->chargeLinks()->exists()) {
            $this->skipped++;

            return;
        }

        try {
            $chargeTypes = ListDngWorklistQuery::mapFeeTypeToChargeTypes($dng->fee_type);
        } catch (\InvalidArgumentException) {
            // Unmapped fee types (BHYT, PRE, THHB, GC, F1) — cannot link to charges
            $this->notFound++;
            $this->notFoundRecords[] = $this->formatNotFoundRecord($dng);

            return;
        }

        // Find candidate charges within ±24h temporal proximity
        $candidates = FinanceCharge::query()
            ->where('student_id', $dng->student_id)
            ->whereIn('charge_type', $chargeTypes)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->when($dng->semester_id, fn ($q) => $q->where('semester_id', $dng->semester_id))
            ->whereBetween('created_at', [
                $dng->created_at->subDay(),
                $dng->created_at->addDay(),
            ])
            ->get();

        if ($candidates->isEmpty()) {
            // Try broader search without temporal constraint (for very old records)
            $candidates = FinanceCharge::query()
                ->where('student_id', $dng->student_id)
                ->whereIn('charge_type', $chargeTypes)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->when($dng->semester_id, fn ($q) => $q->where('semester_id', $dng->semester_id))
                ->get();
        }

        if ($candidates->isEmpty()) {
            $this->notFound++;
            $this->notFoundRecords[] = $this->formatNotFoundRecord($dng);

            return;
        }

        if (! $isDryRun) {
            DB::transaction(function () use ($dng, $candidates) {
                $totalBalance = (float) $candidates->sum('amount');

                foreach ($candidates as $charge) {
                    $chargeAmount = $totalBalance > 0
                        ? round((float) $dng->amount * ((float) $charge->amount / $totalBalance), 2)
                        : (float) $charge->amount;

                    DngPaymentRequestCharge::create([
                        'dng_payment_request_id' => $dng->id,
                        'finance_charge_id' => $charge->id,
                        'amount' => $chargeAmount,
                    ]);
                }
            });
        }

        if ($candidates->count() === 1) {
            $this->linked++;
        } else {
            $this->multi++;
        }
    }

    /** @return array{id: int, student_code: string, fee_type: string, amount: float, status: string, created_at: string} */
    private function formatNotFoundRecord(DngPaymentRequest $dng): array
    {
        return [
            'id' => $dng->id,
            'student_code' => $dng->student_code,
            'fee_type' => $dng->fee_type,
            'amount' => (float) $dng->amount,
            'status' => $dng->status,
            'created_at' => $dng->created_at?->toDateTimeString() ?? '',
        ];
    }
}
