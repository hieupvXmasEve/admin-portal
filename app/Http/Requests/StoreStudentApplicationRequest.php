<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentApplicationRequest extends FormRequest
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
            'students' => 'required|array|min:1',
            'students.*.full_name' => 'required|string|max:100',
            'students.*.gender' => 'nullable|in:male,female,other',
            'students.*.ethnicity' => 'nullable|string|max:100',
            'students.*.birth_day' => 'nullable|integer|min:1|max:31',
            'students.*.birth_month' => 'nullable|integer|min:1|max:12',
            'students.*.birth_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'students.*.national_id' => 'nullable|string|max:20',
            'students.*.phone' => 'nullable|string|max:20',
            'students.*.email' => 'required|email|max:255',
            'students.*.address' => 'nullable|string',
            'students.*.health_information' => 'nullable|string',
            'students.*.parent_phone' => 'nullable|string|max:20',
            'students.*.parent_email' => 'nullable|email|max:255',
            'students.*.campus_code' => 'required|string|max:20',
            'students.*.intended_program' => 'nullable|string|max:100',
            'students.*.intended_specialization' => 'nullable|string|max:100',
            'students.*.intake' => 'nullable|string|max:50',
            'students.*.exam_date' => 'nullable|date',
            'students.*.english_test_type' => 'nullable|string|max:50',
            'students.*.listening' => 'nullable|numeric|between:0,99.99',
            'students.*.reading' => 'nullable|numeric|between:0,99.99',
            'students.*.writing' => 'nullable|numeric|between:0,99.99',
            'students.*.speaking' => 'nullable|numeric|between:0,99.99',
            'students.*.overall' => 'nullable|numeric|between:0,99.99',
            'students.*.submitted_photo' => 'nullable|url',
            'students.*.submitted_cccd' => 'nullable|url',
            'students.*.submitted_ccta' => 'nullable|url',
            'students.*.submitted_tn_translate' => 'nullable|url',
            'students.*.submitted_hb_translate' => 'nullable|url',
            'students.*.submitted_other' => 'nullable|url',
            'students.*.submitted_insurance_card' => 'nullable|url',
            'students.*.submitted_exemption_gc' => 'nullable|url',
            'students.*.study_link_status' => 'nullable|string|max:50',
            'students.*.english_qualifications' => 'nullable|string|max:100',
            'students.*.sut_id' => 'nullable|string|max:50',
            'students.*.is_international_applicant' => 'nullable|boolean',
            'students.*.exception_units' => 'nullable|string',
            'students.*.status' => 'nullable|in:pending,reviewed,approved,rejected',
            'students.*.student_code' => 'required|string|max:20',
        ];
    }
}
