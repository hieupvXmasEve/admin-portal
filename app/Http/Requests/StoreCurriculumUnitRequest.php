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
            'semester_id' => ['required', 'exists:semesters,id'],
            'type' => ['required', 'in:core,major,elective'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
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
            'semester_id.required' => 'Semester is required.',
            'semester_id.exists' => 'Selected semester does not exist.',
            'type.required' => 'Unit type is required.',
            'type.in' => 'Unit type must be core, major, or elective.',
            'year_level.required' => 'Year level is required.',
            'year_level.integer' => 'Year level must be a number.',
            'year_level.min' => 'Year level must be at least 1.',
            'year_level.max' => 'Year level must not exceed 6.',
            'semester_number.required' => 'Semester number is required.',
            'semester_number.integer' => 'Semester number must be a number.',
            'semester_number.min' => 'Semester number must be at least 1.',
            'semester_number.max' => 'Semester number must not exceed 9.',
            'note.max' => 'Note must not exceed 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // No default values needed for simplified form
    }
}
