<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * events.gold_reward_amount is the largest source of Gold, yet stayed
 * decimal(10,2) while the ledger went integer. A staff-entered 10.5 would
 * truncate silently and drift the ledger from the event. Convert to UNSIGNED
 * INT (a reward is never negative); the admin form validates integer input.
 */
return new class extends Migration
{
    private const TABLE = 'events';

    public function up(): void
    {
        $fractional = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM events WHERE gold_reward_amount != FLOOR(gold_reward_amount)'
        )->c ?? 0);

        if ($fractional > 0) {
            Log::warning('[gold-ledger-migration] events.gold_reward_amount has fractional values; they will be rounded on convert', [
                'fractional_rows' => $fractional,
            ]);
        }

        if (! str_contains($this->columnType(self::TABLE, 'gold_reward_amount'), 'unsigned')) {
            DB::statement('ALTER TABLE `events` MODIFY `gold_reward_amount` INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `events` MODIFY `gold_reward_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
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
