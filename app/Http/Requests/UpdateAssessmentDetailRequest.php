<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AssessmentComponentDetail;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assessmentDetail = $this->route('assessmentDetail') ?? $this->route('detail');

        return $this->user()->can('manage_assessments') &&
               $assessmentDetail &&
               $this->user()->can('update', $assessmentDetail);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'due_date' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
            ],
            'max_points' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0.01',
                'max:999.99',
                'decimal:0,2',
            ],
            'weight' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:100',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Assessment detail name is required.',
            'name.max' => 'Assessment detail name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 255 characters.',
            'due_date.after' => 'Due date must be in the future.',
            'max_points.min' => 'Maximum points must be at least 0.01.',
            'max_points.max' => 'Maximum points cannot exceed 999.99.',
            'max_points.decimal' => 'Maximum points must have at most 2 decimal places.',
            'weight.required' => 'Weight is required.',
            'weight.min' => 'Weight must be at least 0.01%.',
            'weight.max' => 'Weight cannot exceed 100%.',
            'weight.decimal' => 'Weight must have at most 2 decimal places.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDetailWeight($validator);
        });
    }

    /**
     * Validate that detail weight doesn't exceed parent component weight.
     */
    protected function validateDetailWeight($validator): void
    {
        if (! $this->has('weight')) {
            return;
        }

        $assessmentDetail = $this->route('assessmentDetail') ?? $this->route('detail');
        $newWeight = (float) $this->input('weight');

        if (! $assessmentDetail || ! $newWeight) {
            return;
        }

        // Get the parent assessment component
        $assessmentComponent = $assessmentDetail->assessmentComponent;

        if (! $assessmentComponent) {
            return;
        }

        // Calculate current total weight of other details in the same component
        $currentDetailWeight = AssessmentComponentDetail::where('assessment_component_id', $assessmentComponent->id)
            ->where('id', '!=', $assessmentDetail->id)
            ->sum('weight');

        $totalDetailWeight = $currentDetailWeight + $newWeight;

        // Check if total detail weight exceeds parent component weight
        if ($totalDetailWeight > $assessmentComponent->weight) {
            $validator->errors()->add(
                'weight',
                "Total detail weight cannot exceed parent component weight of {$assessmentComponent->weight}%. Current total would be: {$totalDetailWeight}%"
            );
        }
    }
}
