<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use App\Modules\Finance\Models\PaymentSurplusDisposition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisposePaymentSurplusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->input('type')) {
            PaymentSurplusDisposition::TYPE_REALLOCATE => $this->user()?->can('allocate_finance_payment') ?? false,
            PaymentSurplusDisposition::TYPE_REFUND => $this->user()?->can('refund_finance_payment') ?? false,
            PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT => $this->user()?->can('forfeit_finance_payment_surplus') ?? false,
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['reallocate', 'refund', 'retain_forfeit'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_line_id' => ['required_if:type,reallocate', 'nullable', 'integer', 'exists:invoice_lines,id'],
            'external_reference' => ['required_if:type,refund', 'nullable', 'string', 'max:255'],
            'policy_code' => ['required_if:type,retain_forfeit', 'nullable', 'string', 'max:100'],
            'reason' => ['required_if:type,retain_forfeit', 'nullable', 'string', 'max:1000'],
        ];
    }
}
