<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-05 / FIN-25: several finance status columns are plain varchars, so the DB
 * accepted any string. Add CHECK constraints whose value lists mirror the PHP
 * backed value sets (B5.2 decision: PHP value list is the single source of
 * truth, DB CHECK enforces it without a lookup table or table-locking ENUM).
 *
 *  - finance_charge_installments.status  -> FinanceChargeInstallment::STATUSES
 *  - dng_payment_requests.status         -> DngPaymentRequest status constants
 *  - dng_webhook_events.processing_status-> DngWebhookEvent status constants
 *    (plus the 'pending' column default used at insert time)
 *
 * Lifecycle review status is intentionally NOT constrained here: it is owned by
 * E-finance-lifecycle-exceptions and carries an undecided dead-status question
 * ('ignored'), so it is left for that story.
 *
 * Pre-checked clean: every distinct stored value is inside the lists below.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{table:string, column:string, values:array<int,string>}>
     */
    private array $checks;

    public function __construct()
    {
        $this->checks = [
            'chk_fci_status' => [
                'table' => 'finance_charge_installments',
                'column' => 'status',
                'values' => ['pending', 'awaiting_payment', 'paid', 'cancelled'],
            ],
            'chk_dng_pr_status' => [
                'table' => 'dng_payment_requests',
                'column' => 'status',
                'values' => [
                    'pending', 'pushed_to_dng', 'paid_uninvoiced', 'paid_invoiced',
                    'reconciled', 'failed', 'cancelled', 'cancel_pushed_to_dng',
                ],
            ],
            'chk_dng_we_processing_status' => [
                'table' => 'dng_webhook_events',
                'column' => 'processing_status',
                'values' => [
                    'pending', 'received', 'processing', 'processed',
                    'failed_retryable', 'failed_terminal', 'skipped', 'mismatch',
                ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->checks as $name => $check) {
            if ($this->checkExists($check['table'], $name)) {
                continue;
            }

            $list = $this->quoteList($check['values']);
            DB::statement(
                "ALTER TABLE `{$check['table']}` ADD CONSTRAINT `{$name}` "
                ."CHECK (`{$check['column']}` IN ({$list}))"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->checks as $name => $check) {
            DB::statement("ALTER TABLE `{$check['table']}` DROP CONSTRAINT IF EXISTS `{$name}`");
        }
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quoteList(array $values): string
    {
        return implode(',', array_map(
            static fn (string $v): string => "'".str_replace("'", "''", $v)."'",
            $values
        ));
    }

    private function checkExists(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.CHECK_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
