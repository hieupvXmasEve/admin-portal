<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Assessment;

use App\Models\AssessmentComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user can manage assessments for this syllabus
        return $this->user()->can('manage_assessments');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'syllabus_id' => [
                'required',
                'integer',
                'exists:syllabus,id',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('assessment_components')->where(function ($query) {
                    return $query->where('syllabus_id', $this->input('syllabus_id'));
                }),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'weight' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100',
                'decimal:0,2',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(AssessmentComponent::TYPES)),
            ],
            'is_required_to_sit_final_exam' => [
                'boolean',
            ],
            'due_date' => [
                'nullable',
                'date',
                'after:now',
            ],
            'available_from' => [
                'nullable',
                'date',
                'before_or_equal:due_date',
            ],
            'late_submission_deadline' => [
                'nullable',
                'date',
                'after:due_date',
            ],
            'late_penalty_percentage' => [
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
            'late_penalty_type' => [
                'string',
                Rule::in(['per_day', 'per_hour', 'fixed', 'none']),
            ],
            'submission_type' => [
                'string',
                Rule::in(['online', 'in_person', 'both', 'no_submission']),
            ],
            'allowed_file_types' => [
                'nullable',
                'array',
            ],
            'allowed_file_types.*' => [
                'string',
                'max:10',
            ],
            'max_file_size_mb' => [
                'nullable',
                'integer',
                'min:1',
                'max:1024',
            ],
            'max_submissions' => [
                'integer',
                'min:1',
                'max:10',
            ],
            'allow_resubmission' => [
                'boolean',
            ],
            'is_group_work' => [
                'boolean',
            ],
            'min_group_size' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:is_group_work,true',
            ],
            'max_group_size' => [
                'nullable',
                'integer',
                'min:1',
                'gte:min_group_size',
                'required_if:is_group_work,true',
            ],
            'students_form_groups' => [
                'boolean',
            ],
            'assessment_criteria' => [
                'nullable',
                'array',
            ],
            'grading_instructions' => [
                'nullable',
                'string',
            ],
            'is_published' => [
                'boolean',
            ],
            'scores_published' => [
                'boolean',
            ],
            'is_extra_credit' => [
                'boolean',
            ],
            'status' => [
                'string',
                Rule::in(['draft', 'published', 'in_progress', 'grading', 'completed', 'cancelled']),
            ],
            'sort_order' => [
                'integer',
                'min:0',
            ],
            'category' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'syllabus_id.required' => 'Syllabus is required.',
            'syllabus_id.exists' => 'The selected syllabus does not exist.',
            'name.required' => 'Assessment component name is required.',
            'name.max' => 'Assessment component name cannot exceed 100 characters.',
            'code.unique' => 'This assessment code already exists for this syllabus.',
            'weight.required' => 'Assessment weight is required.',
            'weight.min' => 'Assessment weight must be at least 0.01%.',
            'weight.max' => 'Assessment weight cannot exceed 100%.',
            'weight.decimal' => 'Assessment weight must have at most 2 decimal places.',
            'type.required' => 'Assessment type is required.',
            'type.in' => 'Invalid assessment type selected.',
            'due_date.after' => 'Due date must be in the future.',
            'available_from.before_or_equal' => 'Available from date must be before or equal to due date.',
            'late_submission_deadline.after' => 'Late submission deadline must be after due date.',
            'late_penalty_percentage.min' => 'Late penalty percentage cannot be negative.',
            'late_penalty_percentage.max' => 'Late penalty percentage cannot exceed 100%.',
            'max_file_size_mb.min' => 'Maximum file size must be at least 1 MB.',
            'max_file_size_mb.max' => 'Maximum file size cannot exceed 1024 MB.',
            'max_submissions.min' => 'Maximum submissions must be at least 1.',
            'max_submissions.max' => 'Maximum submissions cannot exceed 10.',
            'min_group_size.required_if' => 'Minimum group size is required for group work.',
            'max_group_size.required_if' => 'Maximum group size is required for group work.',
            'max_group_size.gte' => 'Maximum group size must be greater than or equal to minimum group size.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required_to_sit_final_exam' => $this->boolean('is_required_to_sit_final_exam', true),
            'late_penalty_percentage' => $this->input('late_penalty_percentage', 0.00),
            'late_penalty_type' => $this->input('late_penalty_type', 'none'),
            'submission_type' => $this->input('submission_type', 'online'),
            'max_submissions' => $this->input('max_submissions', 1),
            'allow_resubmission' => $this->boolean('allow_resubmission', false),
            'is_group_work' => $this->boolean('is_group_work', false),
            'students_form_groups' => $this->boolean('students_form_groups', true),
            'is_published' => $this->boolean('is_published', false),
            'scores_published' => $this->boolean('scores_published', false),
            'is_extra_credit' => $this->boolean('is_extra_credit', false),
            'status' => $this->input('status', 'draft'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTotalWeight($validator);
        });
    }

    /**
     * Validate that total weight doesn't exceed 100%.
     */
    protected function validateTotalWeight($validator): void
    {
        $syllabusId = $this->input('syllabus_id');
        $newWeight = (float) $this->input('weight');

        if (! $syllabusId || ! $newWeight) {
            return;
        }

        // Calculate current total weight for this syllabus
        $currentTotalWeight = AssessmentComponent::where('syllabus_id', $syllabusId)
            ->sum('weight');

        $totalWeight = $currentTotalWeight + $newWeight;

        if ($totalWeight > 100) {
            $validator->errors()->add(
                'weight',
                "Total assessment weight cannot exceed 100%. Current total would be: {$totalWeight}%"
            );
        }
    }
}
