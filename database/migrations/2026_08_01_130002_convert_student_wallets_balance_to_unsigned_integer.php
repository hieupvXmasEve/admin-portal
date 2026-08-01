<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gold debt is forbidden: balance becomes UNSIGNED INT (a DB-level floor at 0).
 *
 * The legacy reclaim path could push a wallet negative on purpose, so any
 * existing negative row is clamped to 0 first — with a `write_off` audit entry
 * recording the magnitude that was forgiven — otherwise the unsigned convert
 * would fail (SQLSTATE 22003). Runs AFTER the gold_transactions convert so the
 * balance_after/type columns exist for the backfill entry.
 */
return new class extends Migration
{
    private const TABLE = 'student_wallets';

    public function up(): void
    {
        $this->clampNegativeBalances();

        if (! str_contains($this->columnType(self::TABLE, 'balance'), 'unsigned')) {
            DB::statement('ALTER TABLE `student_wallets` MODIFY `balance` INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        // Only widen back to signed decimal; the clamped rows are not restored
        // (their real negative value is preserved in the write_off audit entry).
        DB::statement('ALTER TABLE `student_wallets` MODIFY `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    }

    private function clampNegativeBalances(): void
    {
        $negatives = DB::table('student_wallets')
            ->where('balance', '<', 0)
            ->get(['id', 'student_id', 'balance']);

        if ($negatives->isEmpty()) {
            Log::info('[gold-ledger-migration] no negative wallet balances to clamp');

            return;
        }

        $totalWrittenOff = 0;

        foreach ($negatives as $wallet) {
            $shortfall = (int) abs((float) $wallet->balance);
            $totalWrittenOff += $shortfall;

            DB::table('gold_transactions')->insert([
                'student_id' => $wallet->student_id,
                'amount' => 0,
                'balance_before' => 0,
                'balance_after' => 0,
                'type' => 'write_off',
                'source_type' => 'manual',
                'source_id' => null,
                'performed_by' => null,
                'notes' => "Pre-flight clamp: wallet balance {$wallet->balance} written off before unsigned migration (D12: gold debt forbidden)",
                'created_at' => now(),
            ]);

            DB::table('student_wallets')->where('id', $wallet->id)->update(['balance' => 0]);
        }

        Log::warning('[gold-ledger-migration] clamped negative wallet balances', [
            'wallets_clamped' => $negatives->count(),
            'total_written_off' => $totalWrittenOff,
        ]);
    }

    private function columnType(string $table, string $column): string
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return strtolower((string) ($row->t ?? ''));
    }
};
