<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Charges;

use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceChargeRequest extends FormRequest
{
    private const MANUAL_CREATE_CHARGE_TYPES = [
        FinanceCharge::TYPE_TUITION_TERM,
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
        FinanceCharge::TYPE_RETAKE_FEE,
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_DEFER_CREDIT,
        FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
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
        $isManualFee = $this->input('charge_type') === FinanceCharge::TYPE_MANUAL_FEE;

        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'charge_type' => ['required', 'string', Rule::in(self::MANUAL_CREATE_CHARGE_TYPES)],
            // manual_fee is a debit read-model type — amount must be positive (money-sign checks).
            'amount' => $isManualFee
                ? ['required', 'numeric', 'gt:0']
                : ['required', 'numeric'],
            'description' => ['required', 'string', 'max:500'],
            'effective_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'invoice_id' => ['nullable', 'integer', 'exists:student_invoices,id'],
            // Never accepted from the page/source — Finance stamps pricing_rule_version.
            'pricing_rule_version' => ['prohibited'],
            'currency' => $isManualFee ? ['prohibited'] : ['sometimes', 'nullable', 'string', 'size:3'],
            'billing_account_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Manual fee amount must be greater than zero.',
            'pricing_rule_version.prohibited' => 'Pricing rule version is Finance-owned and cannot be supplied.',
            'currency.prohibited' => 'Currency is Finance-owned for manual fees and cannot be supplied.',
            'billing_account_id.prohibited' => 'Billing account is resolved by Finance and cannot be supplied.',
        ];
    }
}
