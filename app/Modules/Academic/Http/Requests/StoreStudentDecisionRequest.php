<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision_name' => ['required', 'string', 'max:255'],
            'decision_number' => ['required', 'string', 'max:255'],
            'decision_signer' => ['required', 'string', 'max:255'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'upload_record_id' => [
                'nullable',
                'integer',
                Rule::exists('upload_records', 'id')->where(fn ($query) => $query->where('context', 'action_attachment')),
            ],
        ];
    }
}
