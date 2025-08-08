<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurriculumVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add authorization logic as needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $curriculumVersionId = $this->route('curriculum_version')?->id ?? $this->route('curriculumVersion')?->id;

        return [
            'program_id' => 'required|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
            'version_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('curriculum_versions', 'version_code')->ignore($curriculumVersionId),
            ],
            'semester_id' => 'required|exists:semesters,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'program_id.required' => 'Please select a program.',
            'program_id.exists' => 'The selected program does not exist.',
            'specialization_id.exists' => 'The selected specialization does not exist.',
            'version_code.unique' => 'This version code is already in use.',
            'semester_id.required' => 'Please select an effective semester.',
            'semester_id.exists' => 'The selected semester does not exist.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that specialization belongs to the selected program
            if ($this->filled(['program_id', 'specialization_id'])) {
                $specialization = \App\Models\Specialization::find($this->specialization_id);

                if ($specialization && $specialization->program_id !== (int) $this->program_id) {
                    $validator->errors()->add(
                        'specialization_id',
                        'The selected specialization does not belong to the selected program.'
                    );
                }
            }
        });
    }
}
