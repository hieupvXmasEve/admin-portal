<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariant;
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
 * The invariant catalog (INV-1..INV-18) lives in the shared
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

        $evidence = match ($invariant->code) {
            'INV-13' => $this->voidChargeInstallmentEvidence($ids),
            'INV-18' => $this->voidChargeInvoiceLineEvidence($ids),
            default => [],
        };

        if ($evidence !== []) {
            $this->line("   ↳ {$invariant->code} evidence reason code: {$this->evidenceReasonCode($invariant->code)}");
            $this->table(
                ['Student', 'Campus', 'Semester', 'Entity', 'Lifecycle state', 'Raw ledger components', 'Reason code'],
                $evidence,
            );
        }
    }

    /**
     * @param  list<int>  $chargeIds
     * @return list<list<string>>
     */
    private function voidChargeInstallmentEvidence(array $chargeIds): array
    {
        if ($chargeIds === []) {
            return [];
        }

        return DB::table('finance_charges as fc')
            ->join('students as s', 's.id', '=', 'fc.student_id')
            ->leftJoin('campuses as c', 'c.id', '=', 's.campus_id')
            ->leftJoin('semesters as sem', 'sem.id', '=', 'fc.semester_id')
            ->join('finance_charge_installments as fci', 'fci.finance_charge_id', '=', 'fc.id')
            ->whereIn('fc.id', $chargeIds)
            ->whereIn('fci.status', ['pending', 'awaiting_payment'])
            ->select([
                'fc.id as charge_id',
                'fc.status as charge_status',
                'fc.amount as gross',
                's.student_id as student_code',
                'c.code as campus_code',
                'sem.code as semester_code',
                'fci.id as installment_id',
                'fci.installment_no',
                'fci.amount as installment_amount',
                'fci.status as installment_status',
            ])
            ->orderBy('fc.id')
            ->orderBy('fci.installment_no')
            ->get()
            ->map(fn (object $row): array => [
                (string) $row->student_code,
                (string) ($row->campus_code ?? 'n/a'),
                (string) ($row->semester_code ?? 'n/a'),
                "charge #{$row->charge_id}",
                "charge={$row->charge_status}; installment #{$row->installment_id} ({$row->installment_status}, {$row->installment_amount})",
                $this->chargeMoneyEvidence((int) $row->charge_id, $row->gross),
                'finance_invariant.INV-13.live_installment_on_void_charge',
            ])
            ->all();
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return list<list<string>>
     */
    private function voidChargeInvoiceLineEvidence(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return DB::table('student_invoices as si')
            ->join('students as s', 's.id', '=', 'si.student_id')
            ->leftJoin('campuses as c', 'c.id', '=', 's.campus_id')
            ->leftJoin('semesters as sem', 'sem.id', '=', 'si.semester_id')
            ->join('invoice_lines as il', 'il.invoice_id', '=', 'si.id')
            ->join('finance_charges as fc', 'fc.id', '=', 'il.charge_id')
            ->whereIn('si.id', $invoiceIds)
            ->where('il.status', 'active')
            ->where('fc.status', 'void')
            ->select([
                'si.id as invoice_id',
                'si.status as invoice_status',
                's.student_id as student_code',
                'c.code as campus_code',
                'sem.code as semester_code',
                'il.id as invoice_line_id',
                'il.amount_snapshot',
                'fc.id as charge_id',
            ])
            ->orderBy('si.id')
            ->orderBy('il.id')
            ->get()
            ->map(fn (object $row): array => [
                (string) $row->student_code,
                (string) ($row->campus_code ?? 'n/a'),
                (string) ($row->semester_code ?? 'n/a'),
                "invoice #{$row->invoice_id}",
                "invoice={$row->invoice_status}; line #{$row->invoice_line_id} (active, {$row->amount_snapshot}); charge #{$row->charge_id} (void)",
                $this->invoiceMoneyEvidence((int) $row->invoice_id),
                'finance_invariant.INV-18.active_invoice_line_on_void_charge',
            ])
            ->all();
    }

    private function chargeMoneyEvidence(int $chargeId, mixed $gross): string
    {
        $lineIds = DB::table('invoice_lines')
            ->where('charge_id', $chargeId)
            ->where('status', 'active')
            ->pluck('id');

        return $this->formatMoneyEvidence(
            $gross,
            $this->discountTotalForLines($lineIds->all()),
            $this->cashTotalForLines($lineIds->all()),
            $this->creditTotalForLines($lineIds->all()),
        );
    }

    private function evidenceReasonCode(string $invariantCode): string
    {
        return match ($invariantCode) {
            'INV-13' => 'finance_invariant.INV-13.live_installment_on_void_charge',
            'INV-18' => 'finance_invariant.INV-18.active_invoice_line_on_void_charge',
            default => throw new \LogicException("No operational evidence reason code is defined for {$invariantCode}."),
        };
    }

    private function invoiceMoneyEvidence(int $invoiceId): string
    {
        $lines = DB::table('invoice_lines')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'active')
            ->get(['id', 'amount_snapshot']);

        return $this->formatMoneyEvidence(
            $lines->sum('amount_snapshot'),
            $this->discountTotalForLines($lines->pluck('id')->all()),
            $this->cashTotalForLines($lines->pluck('id')->all()),
            $this->creditTotalForLines($lines->pluck('id')->all()),
        );
    }

    /** @param list<int> $lineIds */
    private function discountTotalForLines(array $lineIds): mixed
    {
        return $lineIds === [] ? 0 : DB::table('discount_allocations')
            ->whereIn('invoice_line_id', $lineIds)
            ->sum('amount');
    }

    /** @param list<int> $lineIds */
    private function cashTotalForLines(array $lineIds): mixed
    {
        return $lineIds === [] ? 0 : DB::table('payment_applications')
            ->join('payments', 'payments.id', '=', 'payment_applications.payment_id')
            ->whereIn('payment_applications.invoice_line_id', $lineIds)
            ->where('payments.status', 'completed')
            ->sum('payment_applications.amount');
    }

    /** @param list<int> $lineIds */
    private function creditTotalForLines(array $lineIds): mixed
    {
        return $lineIds === [] ? 0 : DB::table('credit_applications')
            ->whereIn('invoice_line_id', $lineIds)
            ->sum('amount');
    }

    private function formatMoneyEvidence(mixed $gross, mixed $discount, mixed $cash, mixed $credit): string
    {
        return sprintf(
            'gross=%s; discount=%s; cash=%s; credit=%s; remaining_collectible=unavailable (invalid position)',
            (string) $gross,
            (string) $discount,
            (string) $cash,
            (string) $credit,
        );
    }
}
