<?php

namespace App\Http\Requests;

use App\Models\VoucherDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedeemVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('edit_voucher');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('voucher_id') && $this->filled('code')) {
            $voucherId = VoucherDefinition::query()
                ->where('code', (string) $this->input('code'))
                ->value('id');

            if ($voucherId) {
                $this->merge([
                    'voucher_id' => $voucherId,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $studentExists = Rule::exists('students', 'id');
        $campusId = session('current_campus_id');

        if ($campusId) {
            $studentExists = $studentExists->where(fn ($query) => $query->where('campus_id', $campusId));
        }

        return [
            'student_id' => ['required', 'integer', $studentExists],
            'voucher_id' => ['required', 'integer', Rule::exists('voucher_definitions', 'id')],
            'code' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Please select a student.',
            'student_id.exists' => 'The selected student is invalid for the current campus.',
            'voucher_id.required' => 'Voucher could not be resolved.',
            'voucher_id.exists' => 'The selected voucher does not exist.',
        ];
    }
}
