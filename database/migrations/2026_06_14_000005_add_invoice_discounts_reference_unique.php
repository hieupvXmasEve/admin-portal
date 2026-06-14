<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-10: nothing stopped the same scholarship/voucher being recorded twice on an
 * invoice. SettlementService::createOrRefreshInvoiceDiscount treats
 * (invoice_id, discount_type, reference_id, discount_source) as the idempotency
 * key via firstOrNew, so this makes that contract authoritative at the DB level.
 *
 * reference_id is nullable and MariaDB treats NULLs as DISTINCT, so a plain
 * unique over reference_id would NOT block two NULL-reference discounts with the
 * same (invoice, type, source). To close that hole without changing the column's
 * nullability or the firstOrNew code path, the unique runs over a persisted
 * sentinel generated column COALESCE(reference_id, 0). No real reference id is 0
 * (ids start at 1), so 0 unambiguously means "no reference".
 *
 * Pre-checked clean: 0 duplicate groups on the key at authoring time.
 */
return new class extends Migration
{
    private const INDEX = 'invoice_discounts_invoice_type_refkey_source_unique';

    private const COLUMN = 'reference_key';

    public function up(): void
    {
        if (! Schema::hasColumn('invoice_discounts', self::COLUMN)) {
            DB::statement(
                'ALTER TABLE `invoice_discounts` '
                .'ADD COLUMN `'.self::COLUMN.'` BIGINT AS (COALESCE(`reference_id`, 0)) PERSISTENT'
            );
        }

        if (! $this->indexExists(self::INDEX)) {
            DB::statement(
                'ALTER TABLE `invoice_discounts` ADD UNIQUE `'.self::INDEX.'` '
                .'(`invoice_id`, `discount_type`, `'.self::COLUMN.'`, `discount_source`)'
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists(self::INDEX)) {
            DB::statement('ALTER TABLE `invoice_discounts` DROP INDEX `'.self::INDEX.'`');
        }

        if (Schema::hasColumn('invoice_discounts', self::COLUMN)) {
            DB::statement('ALTER TABLE `invoice_discounts` DROP COLUMN `'.self::COLUMN.'`');
        }
    }

    private function indexExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['invoice_discounts', $name]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
