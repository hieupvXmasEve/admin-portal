<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchDngRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create_finance_payments')
            && $this->user()?->can('void_finance_charges'));
    }

    /**
     * semester_id + dng_fee_type come from the trusted token scope (not the client).
     * due_date / description / estimate_time are operator commit-time inputs.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'due_date' => 'required|date|after_or_equal:today',
            'description' => 'required|string|max:255',
            'estimate_time' => 'required|string|max:10',
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1|max:100',
            'selected_keys.*' => 'required|string',
            'acknowledged' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['selected_keys.max' => 'Tối đa 100 sinh viên mỗi lần. Hệ thống sẽ chia lô tự động.'];
    }
}
