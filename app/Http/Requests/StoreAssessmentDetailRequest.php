<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AssessmentComponentDetail;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage_assessments');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'assessment_component_id' => [
                'required',
                'integer',
                'exists:assessment_components,id',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'due_date' => [
                'nullable',
                'date',
                'after:now',
            ],
            'max_points' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999.99',
                'decimal:0,2',
            ],
            'weight' => [
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
            'assessment_component_id.required' => 'Assessment component is required.',
            'assessment_component_id.exists' => 'The selected assessment component does not exist.',
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
        $assessmentComponentId = $this->input('assessment_component_id');
        $newWeight = (float) $this->input('weight');

        if (! $assessmentComponentId || ! $newWeight) {
            return;
        }

        // Get the parent assessment component
        $assessmentComponent = \App\Models\AssessmentComponent::find($assessmentComponentId);

        if (! $assessmentComponent) {
            return;
        }

        // Calculate current total weight of existing details
        $currentDetailWeight = AssessmentComponentDetail::where('assessment_component_id', $assessmentComponentId)
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
