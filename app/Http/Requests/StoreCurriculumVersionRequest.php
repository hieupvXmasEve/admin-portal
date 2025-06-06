<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumVersionRequest extends FormRequest
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
        return [
            'program_id' => 'required|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
            'version_code' => 'nullable|string|max:20|unique:curriculum_versions,version_code',
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate version code if not provided
        if (empty($this->version_code)) {
            $program = \App\Models\Program::find($this->program_id);
            $specialization = \App\Models\Specialization::find($this->specialization_id);

            if ($program) {
                $baseCode = $specialization ? $specialization->code : $program->code;
                $year = now()->year;
                $count = \App\Models\CurriculumVersion::where('program_id', $this->program_id)
                    ->when($this->specialization_id, fn($q) => $q->where('specialization_id', $this->specialization_id))
                    ->count() + 1;

                $this->merge([
                    'version_code' => "{$baseCode}-V{$count}-{$year}"
                ]);
            }
        }
    }
}
