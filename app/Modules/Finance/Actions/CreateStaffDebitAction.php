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
 */
class CreateStaffDebitAction
{
    public const SOURCE_SYSTEM = 'finance';

    /**
     * charge_type => default source_kind for staff form creates.
     *
     * @var array<string, string>
     */
    public const STAFF_DEBIT_SOURCE_KINDS = [
        FinanceCharge::TYPE_MANUAL_FEE => 'manual_fee',
        FinanceCharge::TYPE_ADMISSION_FEE => 'manual_fee',
        FinanceCharge::TYPE_ADJUSTMENT => 'manual_adjustment',
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
     *     source_ref?:string|null
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
}
