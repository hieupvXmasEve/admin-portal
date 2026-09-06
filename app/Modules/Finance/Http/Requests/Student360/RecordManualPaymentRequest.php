<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use App\Modules\Finance\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(Payment::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
            'allocations' => ['present', 'array'],
            'allocations.*.charge_id' => ['required', 'integer', 'exists:finance_charges,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
