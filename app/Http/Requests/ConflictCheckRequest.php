<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConflictCheckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage_teaching_assignment');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'lecturer_id' => [
                'required',
                'integer',
                'exists:lectures,id'
            ],
            'course_offering_id' => [
                'required',
                'integer',
                'exists:course_offerings,id'
            ]
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'lecturer_id.required' => 'Lecturer is required for conflict checking.',
            'lecturer_id.exists' => 'The selected lecturer does not exist.',
            'course_offering_id.required' => 'Course offering is required for conflict checking.',
            'course_offering_id.exists' => 'The selected course offering does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'lecturer_id' => 'lecturer',
            'course_offering_id' => 'course offering',
        ];
    }
}
