<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        return [
            'program_id' => ['required', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'version_code' => ['required', 'string', 'max:50'],
            'effective_from_semester_id' => ['nullable', 'exists:semesters,id'],
            'scope' => ['nullable', 'string', 'in:program,specialization'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_id.required' => 'Program is required.',
            'program_id.exists' => 'Selected program does not exist.',
            'specialization_id.exists' => 'Selected specialization does not exist.',
            'version_code.required' => 'Version code is required.',
            'version_code.max' => 'Version code must not exceed 50 characters.',
            'effective_from_semester_id.exists' => 'Selected semester does not exist.',
            'scope.in' => 'Scope must be either program or specialization.',
            'notes.max' => 'Notes must not exceed 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default scope based on specialization_id
        if (!$this->has('scope')) {
            $this->merge([
                'scope' => $this->specialization_id ? 'specialization' : 'program'
            ]);
        }
    }
}
