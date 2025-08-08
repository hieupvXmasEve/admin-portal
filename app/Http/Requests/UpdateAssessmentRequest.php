<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AssessmentComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user can manage assessments for this assessment component
        $assessmentComponent = $this->route('assessment') ?? $this->route('assessmentComponent');

        return $this->user()->can('manage_assessments') &&
               $assessmentComponent &&
               $this->user()->can('update', $assessmentComponent);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $assessmentComponent = $this->route('assessment') ?? $this->route('assessmentComponent');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('assessment_components')->where(function ($query) use ($assessmentComponent) {
                    return $query->where('syllabus_id', $assessmentComponent->syllabus_id);
                })->ignore($assessmentComponent->id ?? null),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'weight' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:100',
                'decimal:0,2',
            ],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(array_keys(AssessmentComponent::TYPES)),
            ],
            'is_required_to_sit_final_exam' => [
                'sometimes',
                'boolean',
            ],
            'due_date' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
            ],
            'available_from' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:due_date',
            ],
            'late_submission_deadline' => [
                'sometimes',
                'nullable',
                'date',
                'after:due_date',
            ],
            'late_penalty_percentage' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
            'late_penalty_type' => [
                'sometimes',
                'string',
                Rule::in(['per_day', 'per_hour', 'fixed', 'none']),
            ],
            'submission_type' => [
                'sometimes',
                'string',
                Rule::in(['online', 'in_person', 'both', 'no_submission']),
            ],
            'allowed_file_types' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'allowed_file_types.*' => [
                'string',
                'max:10',
            ],
            'max_file_size_mb' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:1024',
            ],
            'max_submissions' => [
                'sometimes',
                'integer',
                'min:1',
                'max:10',
            ],
            'allow_resubmission' => [
                'sometimes',
                'boolean',
            ],
            'is_group_work' => [
                'sometimes',
                'boolean',
            ],
            'min_group_size' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'required_if:is_group_work,true',
            ],
            'max_group_size' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'gte:min_group_size',
                'required_if:is_group_work,true',
            ],
            'students_form_groups' => [
                'sometimes',
                'boolean',
            ],
            'assessment_criteria' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'grading_instructions' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'is_published' => [
                'sometimes',
                'boolean',
            ],
            'scores_published' => [
                'sometimes',
                'boolean',
            ],
            'is_extra_credit' => [
                'sometimes',
                'boolean',
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in(['draft', 'published', 'in_progress', 'grading', 'completed', 'cancelled']),
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'category' => [
                'sometimes',
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
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTotalWeight($validator);
            $this->validateStatusTransition($validator);
        });
    }

    /**
     * Validate that total weight doesn't exceed 100%.
     */
    protected function validateTotalWeight($validator): void
    {
        if (! $this->has('weight')) {
            return;
        }

        $assessmentComponent = $this->route('assessment') ?? $this->route('assessmentComponent');
        $newWeight = (float) $this->input('weight');

        if (! $assessmentComponent || ! $newWeight) {
            return;
        }

        // Calculate current total weight for this syllabus excluding this component
        $currentTotalWeight = AssessmentComponent::where('syllabus_id', $assessmentComponent->syllabus_id)
            ->where('id', '!=', $assessmentComponent->id)
            ->sum('weight');

        $totalWeight = $currentTotalWeight + $newWeight;

        if ($totalWeight > 100) {
            $validator->errors()->add(
                'weight',
                "Total assessment weight cannot exceed 100%. Current total would be: {$totalWeight}%"
            );
        }
    }

    /**
     * Validate status transitions are allowed.
     */
    protected function validateStatusTransition($validator): void
    {
        if (! $this->has('status')) {
            return;
        }

        $assessmentComponent = $this->route('assessment') ?? $this->route('assessmentComponent');
        $newStatus = $this->input('status');

        if (! $assessmentComponent) {
            return;
        }

        $currentStatus = $assessmentComponent->status;

        // Define allowed status transitions
        $allowedTransitions = [
            'draft' => ['published', 'cancelled'],
            'published' => ['in_progress', 'cancelled'],
            'in_progress' => ['grading', 'cancelled'],
            'grading' => ['completed', 'in_progress'],
            'completed' => [], // Cannot transition from completed
            'cancelled' => ['draft'], // Can only go back to draft
        ];

        if (! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            $validator->errors()->add(
                'status',
                "Cannot transition from '{$currentStatus}' to '{$newStatus}' status."
            );
        }

        // Additional validation: cannot publish without due date
        if ($newStatus === 'published' && ! $assessmentComponent->due_date && ! $this->input('due_date')) {
            $validator->errors()->add(
                'status',
                'Cannot publish assessment without a due date.'
            );
        }
    }
}
