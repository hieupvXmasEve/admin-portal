<?php

namespace App\Http\Requests;

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
        $scholarshipId = $this->route('scholarship')?->id;

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
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
