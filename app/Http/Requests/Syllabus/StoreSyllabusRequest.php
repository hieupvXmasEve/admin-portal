<?php

declare(strict_types=1);

namespace App\Http\Requests\Syllabus;

use App\Models\AssessmentComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSyllabusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization should be handled by middleware or policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'version' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'total_hours' => 'nullable|numeric|min:0',
            'hours_per_session' => 'nullable|numeric|min:0',
            'curriculum_unit_id' => 'required|exists:curriculum_units,id',
            'is_active' => 'boolean',
            'assessment_components' => 'nullable|array',
            'assessment_components.*.name' => 'required_with:assessment_components|string|max:100',
            'assessment_components.*.weight' => 'required_with:assessment_components|numeric|min:0|max:100',
            'assessment_components.*.type' => ['required_with:assessment_components', Rule::in(array_keys(AssessmentComponent::TYPES))],
            'assessment_components.*.is_required_to_sit_final_exam' => 'boolean',
            'assessment_components.*.details' => 'nullable|array',
            'assessment_components.*.details.*.name' => 'required_with:assessment_components.*.details|string|max:100',
            'assessment_components.*.details.*.weight' => 'required_with:assessment_components.*.details|numeric|min:0|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'curriculum_unit_id.required' => 'Please select a curriculum unit.',
            'curriculum_unit_id.exists' => 'The selected curriculum unit is invalid.',
            'assessment_components.*.name.required_with' => 'Assessment component name is required.',
            'assessment_components.*.weight.required_with' => 'Assessment component weight is required.',
            'assessment_components.*.weight.max' => 'Assessment component weight cannot exceed 100%.',
            'assessment_components.*.type.required_with' => 'Assessment component type is required.',
            'assessment_components.*.type.in' => 'Invalid assessment component type selected.',
            'assessment_components.*.details.*.name.required_with' => 'Assessment detail name is required.',
            'assessment_components.*.details.*.weight.required_with' => 'Assessment detail weight is required.',
            'assessment_components.*.details.*.weight.max' => 'Assessment detail weight cannot exceed 100%.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string numbers to actual numbers
        if ($this->has('total_hours') && $this->total_hours !== null) {
            $this->merge(['total_hours' => (float) $this->total_hours]);
        }

        if ($this->has('hours_per_session') && $this->hours_per_session !== null) {
            $this->merge(['hours_per_session' => (float) $this->hours_per_session]);
        }

        // Process assessment components
        if ($this->has('assessment_components') && is_array($this->assessment_components)) {
            $components = $this->assessment_components;

            foreach ($components as &$component) {
                if (isset($component['weight'])) {
                    $component['weight'] = (float) $component['weight'];
                }

                if (isset($component['details']) && is_array($component['details'])) {
                    foreach ($component['details'] as &$detail) {
                        if (isset($detail['weight'])) {
                            $detail['weight'] = $detail['weight'] ? (float) $detail['weight'] : null;
                        }
                    }
                }
            }

            $this->merge(['assessment_components' => $components]);
        }
    }
}
