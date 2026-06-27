<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ApplicationGuardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationGuardianRequest extends FormRequest
{
    /**
     * Route-level `can:edit_student_application` middleware gates access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => 'sometimes|required|string|max:150',
            'relationship' => ['nullable', 'string', Rule::in(ApplicationGuardian::relationships())],
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
