<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TuitionPlanRequest extends FormRequest
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
            'curriculum_version_id' => ['required', 'integer', 'exists:curriculum_versions,id'],
            'intake_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'terms' => ['nullable', 'array'],
            'terms.*.term_number' => ['required', 'integer', 'min:1'],
            'terms.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'terms.*.due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'curriculum_version_id.required' => 'Curriculum version is required.',
            'curriculum_version_id.exists' => 'The selected curriculum version does not exist.',
            'intake_semester_id.required' => 'Intake semester is required.',
            'intake_semester_id.exists' => 'The selected intake semester does not exist.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a number.',
            'total_amount.min' => 'Total amount must be greater than or equal to zero.',
            'total_amount.max' => 'Total amount is too large.',
            'currency.size' => 'Currency code must be exactly 3 characters.',
            'is_active.boolean' => 'Active status must be true or false.',
            'terms.array' => 'Terms must be an array.',
            'terms.*.term_number.required' => 'Term number is required.',
            'terms.*.term_number.min' => 'Term number must be greater than zero.',
            'terms.*.amount.required' => 'Amount is required for each term.',
            'terms.*.amount.numeric' => 'Term amount must be a number.',
            'terms.*.amount.min' => 'Term amount must be greater than or equal to zero.',
            'terms.*.amount.max' => 'Term amount is too large.',
            'terms.*.due_date.date' => 'Due date must be a valid date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default value for currency
        if (!$this->has('currency')) {
            $this->merge(['currency' => 'VND']);
        }

        // Set default value for is_active
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
