<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use App\Modules\Finance\Models\PaymentSurplusDisposition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisposePaymentSurplusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->input('type')) {
            PaymentSurplusDisposition::TYPE_REALLOCATE => $this->user()?->can('allocate_finance_payment') ?? false,
            PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT => $this->user()?->can('forfeit_finance_payment_surplus') ?? false,
            default => false,
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === PaymentSurplusDisposition::TYPE_REFUND) {
            throw ValidationException::withMessages([
                'type' => [PaymentSurplusDisposition::REFUND_BLOCKED_MESSAGE],
            ]);
        }
    }

    public function rules(): array
    {
        $operatorId = (int) ($this->user()?->id ?? 0);

        return [
            'idempotency_key' => ['required', 'uuid'],
            'type' => ['required', Rule::in([
                PaymentSurplusDisposition::TYPE_REALLOCATE,
                PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_line_id' => ['required_if:type,reallocate', 'nullable', 'integer', 'exists:invoice_lines,id'],
            'policy_code' => ['required_if:type,retain_forfeit', 'nullable', 'string', 'max:100'],
            'reason' => ['required_if:type,retain_forfeit', 'nullable', 'string', 'max:1000'],
            'approved_by' => [
                'required_if:type,retain_forfeit',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::notIn([$operatorId]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'approved_by.not_in' => 'Người phê duyệt phải khác người thao tác.',
        ];
    }
}
