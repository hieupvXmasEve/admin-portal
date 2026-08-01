<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gold is an integer currency (no fractional gold). Convert the ledger to an
 * integer + audit shape:
 *  - type/source_type: DB enum -> varchar(32) so new value-sets (reward,
 *    redemption, redemption_refund, manual_adjustment, write_off, ...) grow via
 *    a backend allow-list instead of a schema migration.
 *  - amount: decimal(10,2) -> INT (signed; spend/write-off are negative).
 *  - balance_before/balance_after: new per-entry audit snapshot (unsigned;
 *    balance can never be negative). Nullable — legacy rows stay null.
 *  - performed_by: real actor FK; adjustBalance previously smuggled the staff id
 *    into source_id, which collided with the source_id audit meaning.
 *
 * MariaDB DDL is not transactional, so each ALTER is idempotent (guarded by
 * information_schema) and this migration touches ONE table — a mid-run failure
 * has a clear boundary. Pre-flight for fractional amounts is logged, not
 * silently truncated; production should reconcile those before deploy.
 */
return new class extends Migration
{
    private const TABLE = 'gold_transactions';

    public function up(): void
    {
        $this->preflight();

        if ($this->columnType(self::TABLE, 'type') !== 'varchar(32)') {
            DB::statement('ALTER TABLE `gold_transactions` MODIFY `type` VARCHAR(32) NOT NULL');
        }

        if ($this->columnType(self::TABLE, 'source_type') !== 'varchar(32)') {
            DB::statement('ALTER TABLE `gold_transactions` MODIFY `source_type` VARCHAR(32) NOT NULL');
        }

        if (! str_contains($this->columnType(self::TABLE, 'amount'), 'int')) {
            DB::statement('ALTER TABLE `gold_transactions` MODIFY `amount` INT NOT NULL');
        }

        if (! Schema::hasColumn(self::TABLE, 'balance_before')) {
            DB::statement('ALTER TABLE `gold_transactions` ADD COLUMN `balance_before` INT UNSIGNED NULL AFTER `amount`');
        }

        if (! Schema::hasColumn(self::TABLE, 'balance_after')) {
            DB::statement('ALTER TABLE `gold_transactions` ADD COLUMN `balance_after` INT UNSIGNED NULL AFTER `balance_before`');
        }

        if (! Schema::hasColumn(self::TABLE, 'performed_by')) {
            DB::statement('ALTER TABLE `gold_transactions` ADD COLUMN `performed_by` BIGINT UNSIGNED NULL AFTER `source_id`');
            DB::statement(
                'ALTER TABLE `gold_transactions` ADD CONSTRAINT `gold_transactions_performed_by_foreign` '
                .'FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(self::TABLE, 'performed_by')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign('gold_transactions_performed_by_foreign');
                $table->dropColumn('performed_by');
            });
        }

        foreach (['balance_after', 'balance_before'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        // amount: INT -> DECIMAL is a lossless widening, always safe.
        if (str_contains($this->columnType(self::TABLE, 'amount'), 'int')) {
            DB::statement('ALTER TABLE `gold_transactions` MODIFY `amount` DECIMAL(10,2) NOT NULL');
        }

        // type/source_type are intentionally left as varchar. Reverting to the
        // original ENUM would destroy the new value-set (reward, redemption,
        // write_off, ...) that live rows now carry — strict mode aborts, and
        // non-strict mode silently coerces those rows to '' and loses the audit
        // classification on a money ledger. varchar is a safe superset, so the
        // down path keeps it.
    }

    private function preflight(): void
    {
        $fractional = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM gold_transactions WHERE amount != FLOOR(amount)'
        )->c ?? 0);

        $rows = (int) (DB::selectOne('SELECT COUNT(*) AS c FROM gold_transactions')->c ?? 0);

        Log::info('[gold-ledger-migration] pre-flight gold_transactions', [
            'total_rows' => $rows,
            'fractional_amount_rows' => $fractional,
        ]);

        if ($fractional > 0) {
            // Non-blocking: MariaDB rounds on MODIFY. Surface it loudly so a
            // production run reconciles the rounding rule beforehand.
            Log::warning('[gold-ledger-migration] gold_transactions has fractional amounts; they will be rounded on convert', [
                'fractional_amount_rows' => $fractional,
            ]);
        }
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
