<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Finance-owned staff debit intake client (wave 7).
 *
 * Manual charge page creates staff-supplied debit obligations through the intake
 * contract. Only the materializer writes FinanceCharge.
 *
 * Adjustment 3-way split (PRD Q10 / wave 6 issue 03):
 *  - positive_debit → FinanceObligation (charge_type=adjustment) via intake
 *  - credit_memo → never a negative charge; staff must use credit entitlement flow
 *  - settlement_correction → ledger reallocation only; never a charge row
 *
 * Defer FORFEIT keeps minting a positive debit via intake (source_kind=defer_forfeit)
 * — option (a) minimal change; not a pure reallocation.
 */
class CreateStaffDebitAction
{
    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const SOURCE_KIND_DEFER_FORFEIT = 'defer_forfeit';

    /** Staff must declare which of the three adjustment shapes they intend. */
    public const ADJUSTMENT_INTENT_POSITIVE_DEBIT = 'positive_debit';

    public const ADJUSTMENT_INTENT_CREDIT_MEMO = 'credit_memo';

    public const ADJUSTMENT_INTENT_SETTLEMENT_CORRECTION = 'settlement_correction';

    /**
     * @var list<string>
     */
    public const ADJUSTMENT_INTENTS = [
        self::ADJUSTMENT_INTENT_POSITIVE_DEBIT,
        self::ADJUSTMENT_INTENT_CREDIT_MEMO,
        self::ADJUSTMENT_INTENT_SETTLEMENT_CORRECTION,
    ];

    /**
     * charge_type => default source_kind for staff form creates.
     *
     * @var array<string, string>
     */
    public const STAFF_DEBIT_SOURCE_KINDS = [
        FinanceCharge::TYPE_MANUAL_FEE => 'manual_fee',
        FinanceCharge::TYPE_ADMISSION_FEE => 'manual_fee',
        FinanceCharge::TYPE_ADJUSTMENT => self::SOURCE_KIND_MANUAL_ADJUSTMENT,
    ];

    /**
     * source_kinds allowed to materialize an adjustment debit charge.
     * settlement_correction is intentionally absent — never a charge row.
     *
     * @var list<string>
     */
    public const ADJUSTMENT_CHARGE_SOURCE_KINDS = [
        self::SOURCE_KIND_MANUAL_ADJUSTMENT,
        self::SOURCE_KIND_DEFER_FORFEIT,
    ];

    public function __construct(
        private readonly FinanceIntakeContract $intake,
    ) {}

    /**
     * @param  array{
     *     student_id:int,
     *     semester_id:int,
     *     charge_type:string,
     *     amount:float|int|string,
     *     description:string,
     *     effective_at?:string|null,
     *     due_date?:string|null,
     *     invoice_id?:int|null,
     *     source_kind?:string|null,
     *     source_ref?:string|null,
     *     adjustment_intent?:string|null
     * }  $data
     */
    public function handle(array $data): FinanceIntakeResult
    {
        $chargeType = (string) $data['charge_type'];
        $sourceKind = $data['source_kind'] ?? self::STAFF_DEBIT_SOURCE_KINDS[$chargeType] ?? null;

        if ($sourceKind === null || ! array_key_exists($chargeType, self::STAFF_DEBIT_SOURCE_KINDS)) {
            throw new InvalidArgumentException(
                "Charge type [{$chargeType}] is not a staff-create debit. Use intake generators or credit memo flows."
            );
        }

        $definition = ObligationTypeRegistry::get($chargeType);

        if ($definition->financialEffect !== FinancialEffect::Debit) {
            throw new InvalidArgumentException(
                "Charge type [{$chargeType}] is not a debit. Use the credit entitlement flow."
            );
        }

        if ($chargeType === FinanceCharge::TYPE_ADJUSTMENT) {
            $this->assertAdjustmentMayCreateCharge($data, $sourceKind);
        }

        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Staff debit amount must be greater than zero. Negative credits use the credit-memo / entitlement flow.'
            );
        }

        $facts = [
            'student_id' => (int) $data['student_id'],
            'semester_id' => (int) $data['semester_id'],
            'amount' => $amount,
            'description' => $data['description'],
        ];

        if (! empty($data['effective_at'])) {
            $facts['effective_at'] = $data['effective_at'];
        }

        if (! empty($data['due_date'])) {
            $facts['due_date'] = $data['due_date'];
        }

        if (! empty($data['invoice_id'])) {
            $facts['invoice_id'] = (int) $data['invoice_id'];
        }

        $sourceRef = is_string($data['source_ref'] ?? null) && $data['source_ref'] !== ''
            ? (string) $data['source_ref']
            : $sourceKind.':'.Str::ulid()->toBase32();

        return $this->intake->request(new FinanceIntakeData(
            source_system: self::SOURCE_SYSTEM,
            source_kind: $sourceKind,
            source_ref: $sourceRef,
            financial_effect: FinancialEffect::Debit,
            obligation_type: $chargeType,
            facts: $facts,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAdjustmentMayCreateCharge(array $data, string $sourceKind): void
    {
        if (! in_array($sourceKind, self::ADJUSTMENT_CHARGE_SOURCE_KINDS, true)) {
            throw new InvalidArgumentException(
                'Settlement correction is a ledger reallocation operation and must never create an adjustment charge row.'
            );
        }

        // System path (defer FORFEIT): source_kind already stamps the debit shape.
        if ($sourceKind === self::SOURCE_KIND_DEFER_FORFEIT) {
            return;
        }

        $intent = $data['adjustment_intent'] ?? null;

        if ($intent === self::ADJUSTMENT_INTENT_CREDIT_MEMO) {
            throw new InvalidArgumentException(
                'Credit reductions must use the FinanceCreditEntitlement credit-memo flow — never a negative adjustment charge.'
            );
        }

        if ($intent === self::ADJUSTMENT_INTENT_SETTLEMENT_CORRECTION) {
            throw new InvalidArgumentException(
                'Settlement correction is a ledger reallocation operation and must never create an adjustment charge row.'
            );
        }

        if ($intent !== self::ADJUSTMENT_INTENT_POSITIVE_DEBIT) {
            throw new InvalidArgumentException(
                'Adjustment creates require adjustment_intent=positive_debit. Credit memo and settlement correction are not charge rows.'
            );
        }
    }
}
