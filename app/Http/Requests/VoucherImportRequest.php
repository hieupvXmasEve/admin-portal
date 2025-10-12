<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoucherImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create_voucher');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'billing_cycle_id' => 'required|integer|exists:billing_cycles,id',
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'billing_cycle_id.required' => 'Please select a billing cycle.',
            'billing_cycle_id.exists' => 'The selected billing cycle does not exist.',
            'file.required' => 'Please upload an Excel file.',
            'file.mimes' => 'The file must be an Excel file (.xlsx or .xls).',
            'file.max' => 'The file size must not exceed 10MB.',
        ];
    }
}
