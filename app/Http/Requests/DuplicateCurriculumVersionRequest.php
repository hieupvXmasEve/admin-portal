<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateCurriculumVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'version_code' => 'required|string|max:20|unique:curriculum_versions,version_code',
            'semester_id' => ['nullable', 'integer', Rule::exists('semesters', 'id')->where('is_archived', false)],
            'notes' => 'nullable|string|max:1000',
            'include_curriculum_units' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'version_code.required' => 'Please provide a version code for the duplicate.',
            'version_code.unique' => 'This version code is already in use.',
            'version_code.max' => 'Version code cannot exceed 20 characters.',
            'semester_id.exists' => 'Selected semester does not exist or is archived.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default value for include_curriculum_units if not provided
        $this->merge([
            'include_curriculum_units' => $this->boolean('include_curriculum_units', true),
        ]);
    }
}
