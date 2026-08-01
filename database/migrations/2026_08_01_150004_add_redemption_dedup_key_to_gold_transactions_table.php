<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-refund guard for the Gold ledger. `redemption_dedup_key` is a
 * MariaDB STORED generated column that is non-NULL only for
 * `redemption`/`redemption_refund` rows (CONCAT(type, source_type, source_id)),
 * with a UNIQUE index on top. MariaDB unique indexes treat NULL as distinct
 * per row, so every other ledger row (NULL key) may duplicate freely — this
 * is a partial unique index in effect: a second `redemption_refund` ledger
 * entry for the same order collides at the DB and the insert throws, backing
 * up the application-level conditional status guard on the redemption order.
 *
 * Raw DB::statement because Laravel's schema builder cannot express generated
 * columns cleanly. Idempotent (information_schema guard) like
 * 2026_08_01_130001's ALTER guards.
 */
return new class extends Migration
{
    private const TABLE = 'gold_transactions';

    private const COLUMN = 'redemption_dedup_key';

    private const INDEX = 'gold_transactions_redemption_dedup_key_unique';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement(
                'ALTER TABLE `gold_transactions` ADD COLUMN `redemption_dedup_key` VARCHAR(80) '
                ."GENERATED ALWAYS AS (CASE WHEN `type` IN ('redemption', 'redemption_refund') "
                ."THEN CONCAT(`type`, ':', `source_type`, ':', `source_id`) ELSE NULL END) STORED"
            );
        }

        if (! $this->hasIndex(self::TABLE, self::INDEX)) {
            DB::statement(
                'ALTER TABLE `gold_transactions` ADD UNIQUE INDEX `'.self::INDEX.'` (`redemption_dedup_key`)'
            );
        }
    }

    public function down(): void
    {
        if ($this->hasIndex(self::TABLE, self::INDEX)) {
            DB::statement('ALTER TABLE `gold_transactions` DROP INDEX `'.self::INDEX.'`');
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement('ALTER TABLE `gold_transactions` DROP COLUMN `'.self::COLUMN.'`');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
