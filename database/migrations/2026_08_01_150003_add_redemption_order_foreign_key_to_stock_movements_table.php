<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 left `stock_movements.redemption_order_id` as a plain nullable
 * integer because `redemption_orders` did not exist yet. Now that it does,
 * constrain it. Idempotent (information_schema guard) so a re-run after a
 * partial failure is safe — mirrors 2026_08_01_130001's ALTER guards.
 */
return new class extends Migration
{
    private const TABLE = 'stock_movements';

    private const CONSTRAINT = 'stock_movements_redemption_order_id_foreign';

    public function up(): void
    {
        if (! $this->hasForeignKey(self::TABLE, self::CONSTRAINT)) {
            DB::statement(
                'ALTER TABLE `stock_movements` ADD CONSTRAINT `'.self::CONSTRAINT.'` '
                .'FOREIGN KEY (`redemption_order_id`) REFERENCES `redemption_orders`(`id`) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if ($this->hasForeignKey(self::TABLE, self::CONSTRAINT)) {
            DB::statement('ALTER TABLE `stock_movements` DROP FOREIGN KEY `'.self::CONSTRAINT.'`');
        }
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? '
            .'AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [$table, $constraint]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
