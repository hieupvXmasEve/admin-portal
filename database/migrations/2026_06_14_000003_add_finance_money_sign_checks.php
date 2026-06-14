<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-06: the DB had no guard on money signs, so a mis-signed amount could
 * silently flip a credit into a debit (or vice versa). Add CHECK constraints:
 *
 *  - payments.amount strictly positive (refunds/reversals are negative ledger
 *    entries, never negative payments).
 *  - finance_charge_installments.amount strictly positive.
 *  - finance_charges sign follows charge_type: debit types must be >= 0, credit
 *    types must be <= 0, adjustment is unconstrained. This forbids the dangerous
 *    sign-FLIP DB-06 targets (a debit stored negative or a credit stored
 *    positive, which silently changes its effect) while still allowing a
 *    zero-amount charge — zero has no sign error and models a real zero-debt /
 *    fully-waived invoice state.
 *
 * The legacy dual-path shape (a discount stored as a negative-amount DEBIT line,
 * e.g. tuition_term = -3,000,000) can no longer be produced by current code —
 * discounts now live in invoice_discounts/discount_allocations. This CHECK
 * therefore prevents re-creating that legacy shape. The SettlementService netting
 * backstop that still READS old data is exercised by LedgerSourceOfTruthTest,
 * which seeds the legacy row with check_constraint_checks disabled (legacy data
 * is allowed to exist; new writes are not).
 *
 * Pre-checked clean: payments/installments have no non-positive rows, all
 * debit-type charges are >= 0 and credit-type charges <= 0 at authoring time.
 */
return new class extends Migration
{
    private const DEBIT_TYPES = "'tuition_term','egc_level_fee','retake_fee','exam_resit_fee','course_fee','manual_fee','admission_fee','bhyt'";

    private const CREDIT_TYPES = "'defer_credit','egc_exempt_credit','scholarship_credit','voucher_credit'";

    /**
     * @var array<string, array{table:string, expr:string}>
     */
    private array $checks;

    public function __construct()
    {
        $this->checks = [
            'chk_payments_amount_positive' => [
                'table' => 'payments',
                'expr' => 'amount > 0',
            ],
            'chk_fci_amount_positive' => [
                'table' => 'finance_charge_installments',
                'expr' => 'amount > 0',
            ],
            'chk_finance_charges_amount_sign' => [
                'table' => 'finance_charges',
                'expr' => '('
                    .'(charge_type IN ('.self::DEBIT_TYPES.') AND amount >= 0)'
                    .' OR (charge_type IN ('.self::CREDIT_TYPES.') AND amount <= 0)'
                    ." OR charge_type = 'adjustment'"
                    .')',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->checks as $name => $check) {
            if ($this->checkExists($check['table'], $name)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$check['table']}` ADD CONSTRAINT `{$name}` CHECK ({$check['expr']})");
        }
    }

    public function down(): void
    {
        foreach ($this->checks as $name => $check) {
            DB::statement("ALTER TABLE `{$check['table']}` DROP CONSTRAINT IF EXISTS `{$name}`");
        }
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
