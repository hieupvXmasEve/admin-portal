<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level `can:create_student_application` middleware gates access.
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
        return [
            'full_name' => 'required|string|max:100',
            'gender' => 'nullable|in:male,female,other',
            'ethnicity' => 'nullable|string|max:100',
            'birth_day' => 'nullable|integer|min:1|max:31',
            'birth_month' => 'nullable|integer|min:1|max:12',
            'birth_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'national_id' => 'nullable|string|max:20|unique:student_applications,national_id',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:student_applications,email',
            'address' => 'nullable|string',
            'health_information' => 'nullable|string',
            'campus_code' => 'required|string|max:20',
            'intended_program' => 'nullable|string|max:100',
            'intended_specialization' => 'nullable|string|max:100',
            'intake' => 'nullable|string|max:50',
            'exam_date' => 'nullable|date',
            'english_test_type' => 'nullable|string|max:50',
            'listening' => 'nullable|numeric|between:0,99.99',
            'reading' => 'nullable|numeric|between:0,99.99',
            'writing' => 'nullable|numeric|between:0,99.99',
            'speaking' => 'nullable|numeric|between:0,99.99',
            'overall' => 'nullable|numeric|between:0,99.99',
            'study_link_status' => 'nullable|string|max:50',
            'english_qualifications' => 'nullable|string|max:100',
            'sut_id' => 'nullable|string|max:50',
            'is_international_applicant' => 'nullable|boolean',
            'exception_units' => 'nullable|string',
            'student_code' => 'required|string|max:20|unique:student_applications,student_code',
        ];
    }
}
