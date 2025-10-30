<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverridePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller/policy
    }

    public function rules(): array
    {
        return [
            'academic_record_id' => ['required', 'integer', 'exists:academic_records,id'],
            'override_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'override_reason.required' => 'A reason for the override is required.',
            'override_reason.min' => 'The reason must be at least 10 characters long.',
            'academic_record_id.exists' => 'The selected academic record does not exist.',
        ];
    }
}
