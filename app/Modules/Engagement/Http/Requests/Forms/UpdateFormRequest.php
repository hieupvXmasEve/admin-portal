<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $formId = $this->route('form')->id ?? null;

        return [
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('forms', 'code')->ignore($formId)],
            'type' => ['sometimes', 'string', Rule::in(['feedback', 'survey', 'query'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'active', 'archived'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],

            // Sections
            'sections' => ['nullable', 'array'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.questions' => ['nullable', 'array'],

            // Questions (either in sections or directly)
            'questions' => ['nullable', 'array'],
            'sections.*.questions.*.code' => ['required', 'string', 'max:100'],
            'sections.*.questions.*.text' => ['required', 'string'],
            'sections.*.questions.*.type' => [
                'required',
                'string',
                Rule::in([
                    'short_text',
                    'long_text',
                    'single_choice',
                    'multi_choice',
                    'likert',
                    'rating',
                    'date',
                    'number',
                    'file',
                    'matrix',
                    'yes_no',
                ]),
            ],
            'sections.*.questions.*.is_required' => ['nullable', 'boolean'],
            'sections.*.questions.*.help_text' => ['nullable', 'string'],
            'sections.*.questions.*.validation_json' => ['nullable', 'array'],
            'sections.*.questions.*.visibility_condition_json' => ['nullable', 'array'],

            // Question options
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.options.*.value' => ['required', 'string', 'max:100'],
            'sections.*.questions.*.options.*.label' => ['required', 'string', 'max:255'],
            'sections.*.questions.*.options.*.allows_free_text' => ['nullable', 'boolean'],

            // Root level questions (same rules)
            'questions.*.code' => ['required', 'string', 'max:100'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.type' => [
                'required',
                'string',
                Rule::in([
                    'short_text',
                    'long_text',
                    'single_choice',
                    'multi_choice',
                    'likert',
                    'rating',
                    'date',
                    'number',
                    'file',
                    'matrix',
                    'yes_no',
                ]),
            ],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.help_text' => ['nullable', 'string'],
            'questions.*.validation_json' => ['nullable', 'array'],
            'questions.*.visibility_condition_json' => ['nullable', 'array'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.value' => ['required', 'string', 'max:100'],
            'questions.*.options.*.label' => ['required', 'string', 'max:255'],
            'questions.*.options.*.allows_free_text' => ['nullable', 'boolean'],

            // Visibility roles
            'visibility_roles' => ['nullable', 'array'],
            'visibility_roles.*' => ['exists:roles,id'],

            // Result visibility
            'result_visibility' => ['nullable', 'array'],
            'result_visibility.*.role_id' => ['required', 'exists:roles,id'],
            'result_visibility.*.visibility_level' => [
                'required',
                'string',
                Rule::in(['own_submission', 'aggregated', 'full_detail']),
            ],
            'result_visibility.*.min_aggregation_threshold' => ['nullable', 'integer', 'min:1'],
            // Targets
            'targets' => ['nullable', 'array'],
            'targets.*.campus_id' => ['nullable', 'exists:campuses,id'],
            'targets.*.scope_type' => [
                'required',
                'string',
                Rule::in(['section', 'class_session', 'course', 'global']),
            ],
            'targets.*.scope_id' => ['nullable', 'integer'],
            'targets.*.start_at' => ['required', 'date'],
            'targets.*.end_at' => ['nullable', 'date', 'after:targets.*.start_at'],
            'targets.*.submission_limit_per_user' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Form code must be unique',
            'type.in' => 'Form type must be one of: feedback, survey, query',
            'sections.*.title.required' => 'Section title is required',
            'sections.*.questions.*.code.required' => 'Question code is required',
            'sections.*.questions.*.text.required' => 'Question text is required',
            'sections.*.questions.*.type.required' => 'Question type is required',
            'sections.*.questions.*.options.*.value.required' => 'Option value is required',
            'sections.*.questions.*.options.*.label.required' => 'Option label is required',
            'questions.*.code.required' => 'Question code is required',
            'questions.*.text.required' => 'Question text is required',
            'questions.*.type.required' => 'Question type is required',
            'visibility_roles.*.exists' => 'Selected role does not exist',
            'result_visibility.*.role_id.required' => 'Role is required for visibility setting',
            'result_visibility.*.visibility_level.required' => 'Visibility level is required',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateQuestionCodes($validator);
            $this->validateChoiceQuestions($validator);
            $this->validateResultVisibilityThresholds($validator);
        });
    }

    /**
     * Validate that question codes are unique within the form.
     */
    protected function validateQuestionCodes($validator): void
    {
        $codes = [];

        // Collect codes from root questions
        if ($this->has('questions')) {
            foreach ($this->input('questions', []) as $index => $question) {
                if (isset($question['code'])) {
                    if (in_array($question['code'], $codes)) {
                        $validator->errors()->add("questions.{$index}.code", 'Question codes must be unique within the form');
                    }
                    $codes[] = $question['code'];
                }
            }
        }

        // Collect codes from section questions
        if ($this->has('sections')) {
            foreach ($this->input('sections', []) as $sectionIndex => $section) {
                if (isset($section['questions'])) {
                    foreach ($section['questions'] as $questionIndex => $question) {
                        if (isset($question['code'])) {
                            if (in_array($question['code'], $codes)) {
                                $validator->errors()->add("sections.{$sectionIndex}.questions.{$questionIndex}.code", 'Question codes must be unique within the form');
                            }
                            $codes[] = $question['code'];
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate that choice questions have options.
     */
    protected function validateChoiceQuestions($validator): void
    {
        // Rating doesn't need options as it uses a fixed 5-star scale
        $choiceTypes = ['single_choice', 'multi_choice', 'likert'];

        // Validate root questions
        if ($this->has('questions')) {
            foreach ($this->input('questions', []) as $index => $question) {
                if (in_array($question['type'] ?? '', $choiceTypes)) {
                    if (empty($question['options'])) {
                        $validator->errors()->add("questions.{$index}.options", 'Choice questions must have at least one option');
                    }
                }
            }
        }

        // Validate section questions
        if ($this->has('sections')) {
            foreach ($this->input('sections', []) as $sectionIndex => $section) {
                if (isset($section['questions'])) {
                    foreach ($section['questions'] as $questionIndex => $question) {
                        if (in_array($question['type'] ?? '', $choiceTypes)) {
                            if (empty($question['options'])) {
                                $validator->errors()->add("sections.{$sectionIndex}.questions.{$questionIndex}.options", 'Choice questions must have at least one option');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate aggregated visibility has threshold.
     */
    protected function validateResultVisibilityThresholds($validator): void
    {
        if ($this->has('result_visibility')) {
            foreach ($this->input('result_visibility', []) as $index => $visibility) {
                if (($visibility['visibility_level'] ?? '') === 'aggregated') {
                    if (empty($visibility['min_aggregation_threshold'])) {
                        $validator->errors()->add("result_visibility.{$index}.min_aggregation_threshold", 'Aggregated visibility requires a minimum threshold');
                    }
                }
            }
        }
    }
}
