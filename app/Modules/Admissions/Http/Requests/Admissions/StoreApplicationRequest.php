<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

final class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['full_name' => ['required', 'string', 'max:100'], 'gender' => ['nullable', 'in:male,female,other'], 'ethnicity' => ['nullable', 'string', 'max:100'], 'birth_day' => ['nullable', 'integer', 'min:1', 'max:31'], 'birth_month' => ['nullable', 'integer', 'min:1', 'max:12'], 'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')], 'national_id' => ['nullable', 'string', 'max:20', 'unique:student_applications,national_id'], 'phone' => ['nullable', 'string', 'max:20'], 'email' => ['required', 'email', 'max:255', 'unique:student_applications,email'], 'address' => ['nullable', 'string'], 'health_information' => ['nullable', 'string'], 'campus_code' => ['nullable', 'string', 'max:20', 'exists:campuses,code'], 'intended_program' => ['nullable', 'string', 'max:100', 'exists:programs,code'], 'intended_specialization' => ['nullable', 'string', 'max:100', 'exists:specializations,code'], 'intake' => ['nullable', 'string', 'max:50', 'exists:semesters,code'], 'exam_date' => ['nullable', 'date'], 'english_test_type' => ['nullable', 'string', 'max:50'], 'listening' => ['nullable', 'numeric', 'between:0,99.99'], 'reading' => ['nullable', 'numeric', 'between:0,99.99'], 'writing' => ['nullable', 'numeric', 'between:0,99.99'], 'speaking' => ['nullable', 'numeric', 'between:0,99.99'], 'overall' => ['nullable', 'numeric', 'between:0,99.99'], 'study_link_status' => ['nullable', 'string', 'max:50'], 'english_qualifications' => ['nullable', 'string', 'max:100'], 'sut_id' => ['nullable', 'string', 'max:50'], 'is_international_applicant' => ['nullable', 'boolean'], 'exception_units' => ['nullable', 'string'], 'student_code' => ['required', 'string', 'max:20', 'unique:student_applications,student_code'], 'crm_campus' => ['nullable', 'string', 'max:255'], 'crm_major' => ['nullable', 'string', 'max:255'], 'province' => ['nullable', 'string', 'max:255'], 'new_province' => ['nullable', 'string', 'max:255'], 'new_street' => ['nullable', 'string', 'max:255'], 'new_ward' => ['nullable', 'string', 'max:255'], 'permanent_address' => ['nullable', 'string'], 'birth_place' => ['nullable', 'string', 'max:255'], 'nationality' => ['nullable', 'string', 'max:255'], 'religion' => ['nullable', 'string', 'max:255'], 'id_card_place_of_issue' => ['nullable', 'string', 'max:255'], 'school' => ['nullable', 'string', 'max:255'], 'graduation_year' => ['nullable', 'string', 'max:10'], 'gpa' => ['nullable', 'numeric', 'min:0'], 'gpa_type' => ['nullable', 'string', 'max:255'], 'scholarship' => ['nullable', 'string', 'max:255'], 'pathway_gateway' => ['nullable', 'string', 'max:255'], 'uu_dai_gc' => ['nullable', 'string', 'max:255'], 'crm_paid_amount' => ['nullable', 'numeric', 'min:0'], 'registration_form' => ['nullable', 'boolean'], 'academic_scores' => ['nullable', 'array'], 'academic_scores.*.subject_code' => ['required', 'string', 'max:50'], 'academic_scores.*.score' => ['required', 'numeric', 'min:0', 'max:99.99'], 'academic_scores.*.source' => ['required', 'in:school_report,national_exam']];
    }
}
