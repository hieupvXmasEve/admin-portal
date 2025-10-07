<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class AssignScholarshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
//        return $this->user()->can('scholarships.assign');
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If student_identifier is provided, resolve to student_id
        if ($this->has('student_identifier')) {
            $identifier = $this->input('student_identifier');

            // Try to find student by email or student_id
            $student = Student::where('email', $identifier)
                ->orWhere('student_id', $identifier)
                ->first();

            if ($student) {
                $this->merge([
                    'student_id' => $student->id,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_identifier' => ['required', 'string', 'max:255'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'scholarship_code' => ['required', 'string', 'max:50', 'exists:scholarship_definitions,code'],
            'awarded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'student_identifier.required' => 'Please provide a student email or ID.',
            'student_id.required' => 'Student not found. Please check the email or ID.',
            'student_id.exists' => 'The student with the provided email or ID does not exist.',
            'scholarship_code.required' => 'Please select a scholarship.',
            'scholarship_code.exists' => 'The selected scholarship does not exist.',
        ];
    }
}
