<?php

declare(strict_types=1);

namespace App\Http\Requests\SyllabusTemplate;

use App\Http\Requests\SyllabusTemplate\Concerns\ValidatesGradingScheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSyllabusTemplateRequest extends FormRequest
{
    use ValidatesGradingScheme;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],

            'total_hours' => ['nullable', 'integer', 'min:0'],
            'total_sessions' => ['nullable', 'integer', 'min:0'],
            'min_attendance_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_grade_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'exam_resit_fee' => ['nullable', 'numeric', 'min:0'],
            'learning_outcomes' => ['nullable', 'array'],
            'grading_criteria' => ['nullable', 'array'],
            'required_materials' => ['nullable', 'array'],
            'assessment_policy' => ['nullable', 'string'],

            'applicable_program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'applicable_campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'delivery_mode' => ['nullable', 'in:in_person,online,hybrid,blended'],

            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'source_template_id' => ['nullable', 'integer', 'exists:syllabus_templates,id'],

            // Grading scheme JSON (null = default weighted percentage); validated
            // against the engine contract at preview/runtime, not here.
            'grading_scheme' => ['nullable', 'array'],

            // Assessment components sync (optional)
            'assessment_components' => ['nullable', 'array'],
            'assessment_components.*.id' => ['nullable', 'integer', 'exists:assessment_components,id'],
            'assessment_components.*.name' => ['required_with:assessment_components', 'string', 'max:255'],
            // Component code is the join key Metropolia grading reads. Required
            // only when a custom scheme is configured; the formula identifier
            // grammar is [A-Za-z_][A-Za-z0-9_]*.
            'assessment_components.*.code' => ['nullable', 'required_with:grading_scheme', 'string', 'max:20', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'assessment_components.*.weight' => ['required_with:assessment_components', 'numeric', 'min:0', 'max:100'],
            'assessment_components.*.type' => ['required_with:assessment_components', 'in:quiz,assignment,project,exam,online_activity,other,attendance'],
            'assessment_components.*.details' => ['nullable', 'array'],
            'assessment_components.*.details.*.id' => ['nullable', 'integer', 'exists:assessment_component_details,id'],
            'assessment_components.*.details.*.name' => ['required_with:assessment_components.*.details', 'string', 'max:255'],
            'assessment_components.*.details.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateGradingScheme($validator));
    }

    protected function prepareForValidation(): void
    {
        foreach (['learning_outcomes', 'grading_criteria', 'required_materials'] as $key) {
            if (is_string($this->input($key))) {
                $decoded = json_decode($this->input($key), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
    }
}
