<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
 * See docs/features/finance/finance-module-review-2026-06-13.md (Mục 4).
 */
class AuditFinanceInvariants extends Command
{
    protected $signature = 'finance:audit-invariants {--sample : Show up to 5 sample ids per failing invariant}';

    protected $description = 'Read-only audit of finance data-integrity invariants (0 offending rows = correct)';

    /**
     * @var array<int, array{code:string, severity:string, label:string, count_sql:string, sample_sql:?string}>
     */
    private array $invariants = [];

    public function handle(): int
    {
        $this->defineInvariants();

        $this->line('');
        $this->info('🔎 Finance data-integrity audit (read-only). 0 offending = correct.');
        $this->printContextCounts();
        $this->line('');

        $rows = [];
        $totalBad = 0;

        foreach ($this->invariants as $inv) {
            try {
                $count = (int) (DB::selectOne($inv['count_sql'])->c ?? 0);
            } catch (Throwable $e) {
                $rows[] = [$inv['code'], $inv['severity'], 'ERROR', mb_substr($e->getMessage(), 0, 60)];

                continue;
            }

            $totalBad += $count;
            $rows[] = [
                $inv['code'],
                $inv['severity'],
                $count === 0 ? '✅ 0' : "❌ {$count}",
                $inv['label'],
            ];

            if ($count > 0 && $this->option('sample') && $inv['sample_sql']) {
                $this->showSamples($inv);
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

    private function showSamples(array $inv): void
    {
        try {
            $samples = DB::select($inv['sample_sql']);
            $ids = array_map(static fn ($r) => (string) ($r->id ?? json_encode($r)), $samples);
            $this->line("   ↳ {$inv['code']} sample ids: ".implode(', ', $ids));
        } catch (Throwable $e) {
            $this->line("   ↳ {$inv['code']} sample error: ".$e->getMessage());
        }
    }

    private function defineInvariants(): void
    {
        $this->invariants = [
            [
                'code' => 'INV-1',
                'severity' => 'CRITICAL',
                'label' => 'Payment over-allocated (SUM applications > payment.amount)',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01
                ) t',
                'sample_sql' => 'SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01 LIMIT 5',
            ],
            [
                'code' => 'INV-2',
                'severity' => 'HIGH',
                'label' => 'student_invoices.cached_paid_amount cache drifts from live (active lines)',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT si.id FROM student_invoices si
                    LEFT JOIN invoice_lines il ON il.invoice_id = si.id AND il.status = "active"
                    LEFT JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    GROUP BY si.id, si.cached_paid_amount
                    HAVING ABS(si.cached_paid_amount - COALESCE(SUM(pa.amount),0)) > 0.01
                ) t',
                'sample_sql' => 'SELECT si.id FROM student_invoices si
                    LEFT JOIN invoice_lines il ON il.invoice_id = si.id AND il.status = "active"
                    LEFT JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    GROUP BY si.id, si.cached_paid_amount
                    HAVING ABS(si.cached_paid_amount - COALESCE(SUM(pa.amount),0)) > 0.01 LIMIT 5',
            ],
            [
                'code' => 'INV-3',
                'severity' => 'CRITICAL',
                'label' => 'Active positive charge NOT linked to exactly 1 active invoice_line',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT fc.id FROM finance_charges fc
                    LEFT JOIN invoice_lines il ON il.charge_id = fc.id AND il.status = "active"
                    WHERE fc.status = "active" AND fc.amount > 0
                    GROUP BY fc.id HAVING COUNT(il.id) <> 1
                ) t',
                'sample_sql' => 'SELECT fc.id FROM finance_charges fc
                    LEFT JOIN invoice_lines il ON il.charge_id = fc.id AND il.status = "active"
                    WHERE fc.status = "active" AND fc.amount > 0
                    GROUP BY fc.id HAVING COUNT(il.id) <> 1 LIMIT 5',
            ],
            [
                'code' => 'INV-4',
                'severity' => 'CRITICAL',
                'label' => 'Payment still applied to a VOID invoice_line (not reversed)',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT il.id FROM invoice_lines il
                    JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE il.status = "void" GROUP BY il.id HAVING SUM(pa.amount) > 0.01
                ) t',
                'sample_sql' => 'SELECT il.id FROM invoice_lines il
                    JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE il.status = "void" GROUP BY il.id HAVING SUM(pa.amount) > 0.01 LIMIT 5',
            ],
            [
                'code' => 'INV-5',
                'severity' => 'CRITICAL',
                'label' => 'Discount status=reversed but allocations still net > 0',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT idc.id FROM invoice_discounts idc
                    JOIN discount_allocations da ON da.invoice_discount_id = idc.id
                    WHERE idc.status = "reversed" GROUP BY idc.id HAVING SUM(da.amount) > 0.01
                ) t',
                'sample_sql' => 'SELECT idc.id FROM invoice_discounts idc
                    JOIN discount_allocations da ON da.invoice_discount_id = idc.id
                    WHERE idc.status = "reversed" GROUP BY idc.id HAVING SUM(da.amount) > 0.01 LIMIT 5',
            ],
            [
                'code' => 'INV-6',
                'severity' => 'CRITICAL',
                'label' => 'Duplicate invoice for same (student_id, semester_id)',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT student_id, semester_id FROM student_invoices
                    GROUP BY student_id, semester_id HAVING COUNT(*) > 1
                ) t',
                'sample_sql' => 'SELECT MIN(id) id FROM student_invoices
                    GROUP BY student_id, semester_id HAVING COUNT(*) > 1 LIMIT 5',
            ],
            [
                'code' => 'INV-7',
                'severity' => 'HIGH',
                'label' => 'Duplicate scholarship award for same student',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT student_id FROM student_scholarship_awards
                    GROUP BY student_id HAVING COUNT(*) > 1
                ) t',
                'sample_sql' => 'SELECT student_id id FROM student_scholarship_awards
                    GROUP BY student_id HAVING COUNT(*) > 1 LIMIT 5',
            ],
            [
                'code' => 'INV-8',
                'severity' => 'CRITICAL',
                'label' => 'Negative balance (discount+paid > charge) on active positive charge',
                'count_sql' => 'SELECT COUNT(*) c FROM finance_charges fc
                    WHERE fc.status = "active" AND fc.amount > 0 AND (
                        fc.amount
                        - COALESCE((SELECT SUM(pa.amount) FROM invoice_lines il
                            JOIN payment_applications pa ON pa.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(da.amount) FROM invoice_lines il
                            JOIN discount_allocations da ON da.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                    ) < -0.01',
                'sample_sql' => 'SELECT fc.id FROM finance_charges fc
                    WHERE fc.status = "active" AND fc.amount > 0 AND (
                        fc.amount
                        - COALESCE((SELECT SUM(pa.amount) FROM invoice_lines il
                            JOIN payment_applications pa ON pa.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(da.amount) FROM invoice_lines il
                            JOIN discount_allocations da ON da.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                    ) < -0.01 LIMIT 5',
            ],
            [
                'code' => 'INV-9',
                'severity' => 'HIGH',
                'label' => 'Duplicate invoice_number',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT invoice_number FROM student_invoices
                    GROUP BY invoice_number HAVING COUNT(*) > 1
                ) t',
                'sample_sql' => 'SELECT MIN(id) id FROM student_invoices
                    GROUP BY invoice_number HAVING COUNT(*) > 1 LIMIT 5',
            ],
            [
                'code' => 'INV-10',
                'severity' => 'HIGH',
                'label' => 'Payment with non-positive amount',
                'count_sql' => 'SELECT COUNT(*) c FROM payments WHERE amount <= 0',
                'sample_sql' => 'SELECT id FROM payments WHERE amount <= 0 LIMIT 5',
            ],
            [
                'code' => 'INV-11',
                'severity' => 'HIGH',
                'label' => 'duplicate webhook payload_hash (idempotency loss)',
                'count_sql' => 'SELECT COUNT(*) c FROM (
                    SELECT payload_hash FROM dng_webhook_events
                    GROUP BY payload_hash HAVING COUNT(*) > 1
                ) t',
                'sample_sql' => 'SELECT MIN(id) id FROM dng_webhook_events
                    GROUP BY payload_hash HAVING COUNT(*) > 1 LIMIT 5',
            ],
            [
                // DB-13: the DNG provider rail must reconcile to the ledger. Every
                // DNG request bridged to a canonical Payment must carry the same
                // amount as that payment. A divergence means the two rails
                // (dng_payment_requests vs payments/applications) disagree about
                // the same money, which is how KPI code can double-count.
                'code' => 'INV-12',
                'severity' => 'HIGH',
                'label' => 'Bridged DNG request amount diverges from its canonical payment (rail mismatch)',
                'count_sql' => 'SELECT COUNT(*) c FROM dng_payment_requests dpr
                    JOIN payments p ON p.id = dpr.payment_id
                    WHERE dpr.payment_id IS NOT NULL
                      AND ABS(dpr.amount - p.amount) > 0.01',
                'sample_sql' => 'SELECT dpr.id FROM dng_payment_requests dpr
                    JOIN payments p ON p.id = dpr.payment_id
                    WHERE dpr.payment_id IS NOT NULL
                      AND ABS(dpr.amount - p.amount) > 0.01 LIMIT 5',
            ],
        ];
    }
}
