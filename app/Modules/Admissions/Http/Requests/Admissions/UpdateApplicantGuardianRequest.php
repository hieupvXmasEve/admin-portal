<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use App\Models\ApplicationGuardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApplicantGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['full_name' => ['sometimes', 'required', 'string', 'max:150'], 'relationship' => ['nullable', 'string', Rule::in(ApplicationGuardian::relationships())], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'occupation' => ['nullable', 'string', 'max:150'], 'address' => ['nullable', 'string', 'max:255'], 'is_primary' => ['nullable', 'boolean']];
    }
}
