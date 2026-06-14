<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-16 / DB-17: add the composite + non-FK indexes the review found missing,
 * after confirming against the live schema that none duplicate an existing FK,
 * unique, or composite index (FK columns are already auto-indexed via
 * constrained(), so only genuinely-missing access paths are added here):
 *
 *  - student_invoices(semester_id, status, due_date) and (status, due_date)
 *    for semester/status due dashboards.
 *  - dng_payment_requests(status, due_date), (student_id, status), and
 *    dng_transaction_id (string, not a FK, previously unindexed).
 *  - invoice_lines(charge_id, status) for active-line lookups by charge.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table:string, columns:array<int,string>, name:string}>
     */
    private array $indexes = [
        ['table' => 'student_invoices', 'columns' => ['semester_id', 'status', 'due_date'], 'name' => 'si_semester_status_due_idx'],
        ['table' => 'student_invoices', 'columns' => ['status', 'due_date'], 'name' => 'si_status_due_idx'],
        ['table' => 'dng_payment_requests', 'columns' => ['status', 'due_date'], 'name' => 'dng_pr_status_due_idx'],
        ['table' => 'dng_payment_requests', 'columns' => ['student_id', 'status'], 'name' => 'dng_pr_student_status_idx'],
        ['table' => 'dng_payment_requests', 'columns' => ['dng_transaction_id'], 'name' => 'dng_pr_txn_id_idx'],
        ['table' => 'invoice_lines', 'columns' => ['charge_id', 'status'], 'name' => 'il_charge_status_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            if ($this->indexExists($index['table'], $index['name'])) {
                continue;
            }

            Schema::table($index['table'], function (Blueprint $table) use ($index) {
                $table->index($index['columns'], $index['name']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            if (! $this->indexExists($index['table'], $index['name'])) {
                continue;
            }

            Schema::table($index['table'], function (Blueprint $table) use ($index) {
                $table->dropIndex($index['name']);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
