<?php

namespace App\Http\Requests;

use App\Models\ScholarshipDefinition;
use App\Modules\Finance\Support\ScholarshipFixedAmountCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScholarshipRequest extends FormRequest
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
        $scholarship = $this->route('scholarship');
        $scholarshipId = $scholarship instanceof ScholarshipDefinition ? $scholarship->id : $scholarship;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('scholarship_definitions', 'code')->ignore($scholarshipId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'total_amount' => ['nullable', 'required_if:type,fixed_amount', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'total_terms' => ['nullable', 'required_if:type,fixed_amount', 'integer', 'min:1', 'max:1000'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Scholarship code is required.',
            'code.unique' => 'This scholarship code has already been taken.',
            'code.regex' => 'Scholarship code must contain only uppercase letters, numbers, hyphens, and underscores.',
            'code.max' => 'Scholarship code cannot exceed 50 characters.',
            'name.required' => 'Scholarship name is required.',
            'name.max' => 'Scholarship name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'type.required' => 'Scholarship type is required.',
            'type.in' => 'Scholarship type must be either percentage or fixed_amount.',
            'amount.required' => 'Scholarship amount is required.',
            'amount.numeric' => 'Scholarship amount must be a number.',
            'amount.min' => 'Scholarship amount must be greater than zero.',
            'amount.max' => 'Scholarship amount is too large.',
            'total_amount.required_if' => 'Total amount is required for fixed amount scholarships.',
            'total_amount.numeric' => 'Total amount must be a number.',
            'total_amount.min' => 'Total amount must be greater than zero.',
            'total_amount.max' => 'Total amount is too large.',
            'total_terms.required_if' => 'Total terms is required for fixed amount scholarships.',
            'total_terms.integer' => 'Total terms must be a whole number.',
            'total_terms.min' => 'Total terms must be at least one.',
            'total_terms.max' => 'Total terms is too large.',
            'valid_from.required' => 'Valid from date is required.',
            'valid_from.date' => 'Valid from must be a valid date.',
            'valid_until.required' => 'Valid until date is required.',
            'valid_until.date' => 'Valid until must be a valid date.',
            'valid_until.after' => 'Valid until date must be after valid from date.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert code to uppercase
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->code)]);
        }

        // Set default value for is_active
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if ($this->input('type') === 'percentage') {
            $this->merge([
                'total_amount' => null,
                'total_terms' => null,
            ]);

            return;
        }

        if ($this->input('type') === 'fixed_amount' && $this->hasValidFixedCalculationInputs()) {
            $this->merge([
                'amount' => ScholarshipFixedAmountCalculator::calculate(
                    $this->input('total_amount'),
                    $this->input('total_terms'),
                ),
            ]);
        }
    }

    private function hasValidFixedCalculationInputs(): bool
    {
        $totalAmount = $this->input('total_amount');
        $totalTerms = $this->input('total_terms');

        return is_numeric($totalAmount)
            && is_numeric($totalTerms)
            && (float) $totalAmount > 0
            && (int) $totalTerms >= 1;
    }
}
