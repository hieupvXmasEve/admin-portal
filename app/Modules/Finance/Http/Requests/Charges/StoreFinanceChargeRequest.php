<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Charges;

use App\Modules\Finance\Actions\CreateStaffDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinanceChargeRequest extends FormRequest
{
    /**
     * Wave 7: staff create form is intake-only for staff-supplied debits.
     * Catalog types use Batch Studio / Academic generators; credits use entitlement flows.
     */
    private const MANUAL_CREATE_CHARGE_TYPES = [
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_ADJUSTMENT,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'charge_type' => ['required', 'string', Rule::in(self::MANUAL_CREATE_CHARGE_TYPES)],
            // Adjustment 3-way split: staff must declare shape; only positive_debit creates a charge.
            'adjustment_intent' => [
                Rule::requiredIf(fn (): bool => $this->input('charge_type') === FinanceCharge::TYPE_ADJUSTMENT),
                'nullable',
                'string',
                Rule::in(CreateStaffDebitAction::ADJUSTMENT_INTENTS),
            ],
            // All staff-create types are positive debits (ADR-0030 / wave 7).
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:500'],
            'effective_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'invoice_id' => ['nullable', 'integer', 'exists:student_invoices,id'],
            // Never accepted from the page/source — Finance stamps pricing_rule_version.
            'pricing_rule_version' => ['prohibited'],
            'currency' => ['prohibited'],
            'billing_account_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('charge_type') !== FinanceCharge::TYPE_ADJUSTMENT) {
                return;
            }

            $intent = $this->input('adjustment_intent');

            if ($intent === CreateStaffDebitAction::ADJUSTMENT_INTENT_CREDIT_MEMO) {
                $validator->errors()->add(
                    'adjustment_intent',
                    'Credit reductions must use the FinanceCreditEntitlement credit-memo flow — never a negative adjustment charge.'
                );
            }

            if ($intent === CreateStaffDebitAction::ADJUSTMENT_INTENT_SETTLEMENT_CORRECTION) {
                $validator->errors()->add(
                    'adjustment_intent',
                    'Settlement correction is a ledger reallocation operation and must never create an adjustment charge row.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Staff debit amount must be greater than zero. Use credit memo for reductions.',
            'charge_type.in' => 'Only manual_fee, admission_fee, and positive adjustment may be created here via intake.',
            'adjustment_intent.required' => 'Adjustment must declare a shape: positive_debit, credit_memo, or settlement_correction.',
            'adjustment_intent.in' => 'Adjustment intent must be one of: positive_debit, credit_memo, settlement_correction.',
            'pricing_rule_version.prohibited' => 'Pricing rule version is Finance-owned and cannot be supplied.',
            'currency.prohibited' => 'Currency is Finance-owned for staff debits and cannot be supplied.',
            'billing_account_id.prohibited' => 'Billing account is resolved by Finance and cannot be supplied.',
        ];
    }
}
