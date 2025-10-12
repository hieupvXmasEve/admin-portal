<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'semester_id' => 'required|integer|exists:semesters,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'due_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Please select a semester.',
            'semester_id.exists' => 'The selected semester does not exist.',
            'name.required' => 'Billing cycle name is required.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after' => 'End date must be after start date.',
            'due_date.required' => 'Due date is required.',
            'due_date.after_or_equal' => 'Due date cannot be before start date.',
        ];
    }
}
