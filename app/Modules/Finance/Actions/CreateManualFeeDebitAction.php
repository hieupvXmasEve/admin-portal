<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Str;

/**
 * Finance-owned manual_fee intake client (wave 2).
 *
 * Staff workflow on the manual charge page mints a Finance source reference and
 * enters through the intake contract. Only the materializer writes FinanceCharge.
 */
class CreateManualFeeDebitAction
{
    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND = 'manual_fee';

    public function __construct(
        private readonly FinanceIntakeContract $intake,
    ) {}

    /**
     * @param  array{
     *     student_id:int,
     *     semester_id:int,
     *     amount:float|int|string,
     *     description:string,
     *     effective_at?:string|null,
     *     due_date?:string|null,
     *     invoice_id?:int|null
     * }  $data
     */
    public function handle(array $data): FinanceIntakeResult
    {
        $facts = [
            'student_id' => (int) $data['student_id'],
            'semester_id' => (int) $data['semester_id'],
            'amount' => $data['amount'],
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

        return $this->intake->request(new FinanceIntakeData(
            source_system: self::SOURCE_SYSTEM,
            source_kind: self::SOURCE_KIND,
            source_ref: $this->mintSourceRef(),
            financial_effect: FinancialEffect::Debit,
            obligation_type: FinanceCharge::TYPE_MANUAL_FEE,
            facts: $facts,
        ));
    }

    private function mintSourceRef(): string
    {
        // Finance-owned source_ref; unique per create (source quad uniqueness).
        return 'manual_fee:'.Str::ulid()->toBase32();
    }
}
