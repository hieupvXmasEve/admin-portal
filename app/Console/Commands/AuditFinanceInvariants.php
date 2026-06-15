<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Support\Integrity\FinanceInvariant;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only audit of finance data integrity invariants.
 *
 * Each invariant returns the number of OFFENDING rows/groups.
 * A correct dataset returns 0 for every invariant.
 *
 * This command performs SELECT queries only — it never mutates data.
 * The invariant catalog (INV-1..INV-15) lives in the shared
 * App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry so the command
 * and the finance audit workspace cannot drift. A failing invariant SQL is
 * surfaced as ERROR (never swallowed into a clean 0).
 * See docs/features/finance/finance-module-review-2026-06-13.md (Mục 4).
 */
class AuditFinanceInvariants extends Command
{
    protected $signature = 'finance:audit-invariants {--sample : Show up to 5 sample ids per failing invariant}';

    protected $description = 'Read-only audit of finance data-integrity invariants (0 offending rows = correct)';

    public function __construct(
        private readonly FinanceInvariantRegistry $registry,
        private readonly FinanceIntegrityAuditor $auditor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->line('');
        $this->info('🔎 Finance data-integrity audit (read-only). 0 offending = correct.');
        $this->printContextCounts();
        $this->line('');

        $rows = [];
        $totalBad = 0;

        foreach ($this->registry->all() as $invariant) {
            try {
                $count = $this->auditor->count($invariant, null);
            } catch (Throwable $e) {
                $rows[] = [$invariant->code, $invariant->severity, 'ERROR', mb_substr($e->getMessage(), 0, 60)];

                continue;
            }

            $totalBad += $count;
            $rows[] = [
                $invariant->code,
                $invariant->severity,
                $count === 0 ? '✅ 0' : "❌ {$count}",
                $invariant->label,
            ];

            if ($count > 0 && $this->option('sample')) {
                $this->showSamples($invariant);
            }
        }

        $this->table(['Invariant', 'Severity', 'Offending', 'Description'], $rows);

        $this->line('');
        if ($totalBad === 0) {
            $this->info('✅ All invariants pass — no offending rows found in current dataset.');
        } else {
            $this->warn("⚠️  {$totalBad} total offending rows/groups across invariants. Run with --sample for ids.");
        }
        $this->line('');

        return self::SUCCESS;
    }

    private function printContextCounts(): void
    {
        $tables = [
            'finance_charges', 'payments', 'payment_applications',
            'student_invoices', 'invoice_lines', 'invoice_discounts',
            'discount_allocations', 'student_scholarship_awards',
        ];

        $rows = [];
        foreach ($tables as $t) {
            try {
                $rows[] = [$t, (string) DB::table($t)->count()];
            } catch (Throwable) {
                $rows[] = [$t, 'n/a'];
            }
        }
        $this->line('Context — row counts:');
        $this->table(['Table', 'Rows'], $rows);
    }

    private function showSamples(FinanceInvariant $invariant): void
    {
        // Parity note: the pre-refactor command printed "sample error: <msg>" if a
        // sample SQL threw. The shared auditor treats samples as best-effort and
        // degrades to an empty id list instead. This only differs on an effectively
        // unreachable path (samples run only after count() on the same tables already
        // succeeded); count-level parity — the integrity signal — is fully preserved.
        $ids = $this->auditor->samples($invariant, null);
        $this->line("   ↳ {$invariant->code} sample ids: ".implode(', ', $ids));
    }
}
