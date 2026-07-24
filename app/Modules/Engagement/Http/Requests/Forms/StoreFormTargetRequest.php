<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFormTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'scope_type' => 'global',
            'scope_id' => null,
            'semester_id' => null,
        ]);
    }

    public function rules(): array
    {
        return [
            'form_id' => ['required', 'exists:forms,id'],
            'form_version_id' => [
                'nullable',
                Rule::exists('form_versions', 'id')
                    ->where('form_id', $this->input('form_id')),
            ],
            'scope_type' => ['required', 'in:course,semester,department,global'],
            'scope_id' => ['nullable'],
            'semester_id' => ['nullable', 'required_if:scope_type,semester'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'is_mandatory' => ['boolean'],
        ];
    }
}
