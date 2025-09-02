<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     * http://localhost:3000/api/lecturer/courses/42/assessments/details/6/grades?page=1&per_page=50&sort_by=student_name&sort_order=asc
     */
    public function rules(): array
    {
        return [
            // Pagination
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],

            // Sorting
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in([
                    'student_id',
                    'student_name',
                    'points_earned',
                    'percentage_score',
                    'letter_grade',
                    'submitted_at',
                    'graded_at',
                    'status',
                    'is_late'
                ])
            ],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],

            // Filters
            'status' => [
                'sometimes',
                'string',
                Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned'])
            ],
            'score_status' => [
                'sometimes',
                'string',
                Rule::in(['draft', 'provisional', 'final'])
            ],
            'min_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'letter_grade' => [
                'sometimes',
                'string',
                Rule::in(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'])
            ],
            'is_late' => ['sometimes', 'boolean'],
            'plagiarism_suspected' => ['sometimes', 'boolean'],
            'score_excluded' => ['sometimes', 'boolean'],
            'appeal_requested' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],

            // Additional filters
            'submitted_after' => ['sometimes', 'date'],
            'submitted_before' => ['sometimes', 'date'],
            'graded_after' => ['sometimes', 'date'],
            'graded_before' => ['sometimes', 'date'],
            'group_id' => ['sometimes', 'integer', 'exists:student_groups,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'sort_by.in' => 'Invalid sort field specified',
            'sort_order.in' => 'Sort order must be either "asc" or "desc"',
            'status.in' => 'Invalid submission status',
            'score_status.in' => 'Invalid score status',
            'letter_grade.in' => 'Invalid letter grade',
            'min_score.max' => 'Minimum score cannot exceed 100',
            'max_score.max' => 'Maximum score cannot exceed 100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure min_score is less than max_score if both provided
            if ($this->has('min_score') && $this->has('max_score')) {
                if ($this->min_score > $this->max_score) {
                    $validator->errors()->add('min_score', 'Minimum score must be less than or equal to maximum score');
                }
            }

            // Ensure date ranges are valid
            if ($this->has('submitted_after') && $this->has('submitted_before')) {
                if ($this->date('submitted_after')->gt($this->date('submitted_before'))) {
                    $validator->errors()->add('submitted_after', 'Start date must be before end date');
                }
            }

            if ($this->has('graded_after') && $this->has('graded_before')) {
                if ($this->date('graded_after')->gt($this->date('graded_before'))) {
                    $validator->errors()->add('graded_after', 'Start date must be before end date');
                }
            }
        });
    }

    /**
     * Get validated data with defaults
     */
    public function validatedWithDefaults(): array
    {
        return array_merge([
            'page' => 1,
            'per_page' => 20,
            'sort_by' => 'student_name',
            'sort_order' => 'asc',
        ], $this->validated());
    }
}
