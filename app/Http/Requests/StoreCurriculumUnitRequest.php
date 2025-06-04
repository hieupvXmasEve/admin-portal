<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        return [
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'unit_type_id' => ['nullable', 'exists:curriculum_unit_types,id'],
            'semester_order' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_compulsory' => ['boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'curriculum_version_id.required' => 'Curriculum version is required.',
            'curriculum_version_id.exists' => 'Selected curriculum version does not exist.',
            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'Selected unit does not exist.',
            'unit_type_id.exists' => 'Selected unit type does not exist.',
            'semester_order.integer' => 'Semester order must be a number.',
            'semester_order.min' => 'Semester order must be at least 1.',
            'semester_order.max' => 'Semester order must not exceed 12.',
            'is_compulsory.boolean' => 'Compulsory field must be true or false.',
            'note.max' => 'Note must not exceed 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('is_compulsory')) {
            $this->merge(['is_compulsory' => true]);
        }
    }
}
