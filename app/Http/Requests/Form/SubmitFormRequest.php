<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'target_scope_type' => ['required', 'string', 'in:global,course,section,class_session,department,semester'],
            'target_scope_id' => ['nullable', 'integer'],
            'anonymized' => ['boolean'],
            'origin' => ['nullable', 'string', 'in:web,mobile,api'],
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:questions,id'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:10000'],
            'answers.*.answer_number' => ['nullable', 'numeric'],
            'answers.*.answer_date' => ['nullable', 'date'],
            'answers.*.comment' => ['nullable', 'string', 'max:1000'],
            'answers.*.selected_options' => ['nullable', 'array'],
            'answers.*.selected_options.*' => ['required', 'array'],
            'answers.*.selected_options.*.option_id' => ['required', 'exists:options,id'],
            'answers.*.selected_options.*.free_text' => ['nullable', 'string', 'max:500'],

            // Query-specific fields
            'query' => ['nullable', 'array'],
            'query.topic_id' => ['nullable', 'exists:query_topics,id'],
            'query.custom_topic_text' => ['nullable', 'string', 'max:255'],
            'query.priority' => ['nullable', 'string', 'in:low,normal,high'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'At least one answer is required.',
            'answers.*.question_id.required' => 'Question ID is required for each answer.',
            'answers.*.question_id.exists' => 'Invalid question ID provided.',
            'answers.*.answer_text.max' => 'Answer text cannot exceed 10,000 characters.',
            'answers.*.answer_number.numeric' => 'Answer must be a valid number.',
            'answers.*.answer_date.date' => 'Answer must be a valid date.',
            'answers.*.comment.max' => 'Comment cannot exceed 1,000 characters.',
            'answers.*.selected_options.*.option_id.required' => 'Option ID is required for selected options.',
            'answers.*.selected_options.*.option_id.exists' => 'Invalid option ID provided.',
            'answers.*.selected_options.*.free_text.max' => 'Free text cannot exceed 500 characters.',
            'query.topic_id.exists' => 'Invalid query topic selected.',
            'query.custom_topic_text.max' => 'Custom topic text cannot exceed 255 characters.',
            'query.priority.in' => 'Priority must be low, normal, or high.',
            'target_scope_type.in' => 'Invalid scope type. Must be global, course, section, class_session, department, or semester.',
            'campus_id.exists' => 'Invalid campus selected.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'anonymized' => $this->boolean('anonymized', false),
            'origin' => $this->input('origin', 'web'),
            'target_scope_type' => $this->input('target_scope_type', 'global'),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get the form from the route
            $form = $this->route('form');
            $isQueryForm = ($form instanceof \App\Models\Form && $form->type === 'query');

            // Validate that if scope_type is not global, scope_id is required (unless it's a query form)
            if (!$isQueryForm && in_array($this->input('target_scope_type'), ['course', 'section', 'class_session', 'department', 'semester'])) {
                if (empty($this->input('target_scope_id'))) {
                    $validator->errors()->add('target_scope_id', 'Scope ID is required when scope type is not global.');
                }
            }

            // Validate that for query type, either topic_id or custom_topic_text is provided
            if ($this->has('query')) {
                $query = $this->input('query', []);
                if (empty($query['topic_id']) && empty($query['custom_topic_text'])) {
                    $validator->errors()->add('query', 'Either topic ID or custom topic text is required for queries.');
                }
            }
        });
    }
}
