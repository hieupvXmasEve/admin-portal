<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentApplicationRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $applicationId = $this->route('studentApplication')?->id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'birth_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'birth_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'national_id' => ['nullable', 'string', 'max:20', 'unique:student_applications,national_id,'.$applicationId],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:student_applications,email,'.$applicationId],
            'address' => ['nullable', 'string'],
            'health_information' => ['nullable', 'string'],
            'campus_code' => ['required', 'string', 'exists:campuses,code'],
            'intended_program' => ['required', 'string', 'max:255', 'exists:programs,code'],
            'intended_specialization' => ['nullable', 'string', 'max:255', 'exists:specializations,code'],
            'intake' => ['required', 'string', 'max:50', 'exists:semesters,code'],
            'exam_date' => ['nullable', 'date'],
            'english_test_type' => ['nullable', 'string', 'max:50'],
            'listening' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'reading' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'writing' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'speaking' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'overall' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'study_link_status' => ['nullable', 'string', 'max:50'],
            'english_qualifications' => ['nullable', 'string'],
            'sut_id' => ['nullable', 'string', 'max:50'],
            'is_international_applicant' => ['nullable', 'boolean'],
            'exception_units' => ['nullable', 'string'],
            // `status` is intentionally not editable here — lifecycle transitions
            // happen only through Approve/Reject (no path auto-approves).
            'student_code' => ['required', 'string', 'max:20', 'unique:student_applications,student_code,'.$applicationId],
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
            'full_name.required' => 'The full name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'The phone number is required.',
            'campus_code.required' => 'The campus code is required.',
            'campus_code.exists' => 'The selected campus does not exist.',
            'national_id.unique' => 'This national ID is already registered.',
            'birth_day.integer' => 'Birth day must be a number.',
            'birth_day.min' => 'Birth day must be between 1 and 31.',
            'birth_day.max' => 'Birth day must be between 1 and 31.',
            'birth_month.integer' => 'Birth month must be a number.',
            'birth_month.min' => 'Birth month must be between 1 and 12.',
            'birth_month.max' => 'Birth month must be between 1 and 12.',
            'birth_year.integer' => 'Birth year must be a number.',
            'birth_year.min' => 'Birth year must be 1900 or later.',
            'birth_year.max' => 'Birth year cannot be in the future.',
            'listening.numeric' => 'Listening score must be a number.',
            'listening.min' => 'Listening score must be at least 0.',
            'listening.max' => 'Listening score cannot exceed 10.',
            'reading.numeric' => 'Reading score must be a number.',
            'reading.min' => 'Reading score must be at least 0.',
            'reading.max' => 'Reading score cannot exceed 10.',
            'writing.numeric' => 'Writing score must be a number.',
            'writing.min' => 'Writing score must be at least 0.',
            'writing.max' => 'Writing score cannot exceed 10.',
            'speaking.numeric' => 'Speaking score must be a number.',
            'speaking.min' => 'Speaking score must be at least 0.',
            'speaking.max' => 'Speaking score cannot exceed 10.',
            'overall.numeric' => 'Overall score must be a number.',
            'overall.min' => 'Overall score must be at least 0.',
            'overall.max' => 'Overall score cannot exceed 10.',
            'student_code.required' => 'The student code field is required.',
            'student_code.unique' => 'This student code is already in use.',
        ];
    }
}
