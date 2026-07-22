<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests;

use App\Models\ApplicationGuardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IngestApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'crm_admission_id' => ['required', 'string', 'max:255'], 'student_code' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'], 'gender' => ['nullable', 'string', 'max:20'], 'ethnicity' => ['nullable', 'string', 'max:100'],
            'birth_day' => ['nullable', 'integer', 'min:1', 'max:31'], 'birth_month' => ['nullable', 'integer', 'min:1', 'max:12'], 'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')], 'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string'], 'health_information' => ['nullable', 'string'],
            'campus_code' => ['required', 'string', 'max:50', 'exists:campuses,code'], 'intended_program' => ['required', 'string', 'max:255', 'exists:programs,code'], 'intended_specialization' => ['nullable', 'string', 'max:255', 'exists:specializations,code'], 'intake' => ['required', 'string', 'max:100', 'exists:semesters,code'],
            'is_international_applicant' => ['nullable', 'boolean'], 'exception_units' => ['nullable', 'string'], 'sut_id' => ['nullable', 'string', 'max:100'], 'english_qualifications' => ['nullable', 'string'], 'study_link_status' => ['nullable', 'string', 'max:100'],
            'english_test' => ['nullable', 'array'], 'english_test.test_type' => ['nullable', 'string', 'max:50'], 'english_test.exam_date' => ['nullable', 'date'], 'english_test.listening' => ['nullable', 'numeric', 'between:0,99.99'], 'english_test.reading' => ['nullable', 'numeric', 'between:0,99.99'], 'english_test.writing' => ['nullable', 'numeric', 'between:0,99.99'], 'english_test.speaking' => ['nullable', 'numeric', 'between:0,99.99'], 'english_test.overall' => ['nullable', 'numeric', 'between:0,99.99'],
            'guardians' => ['nullable', 'array'], 'guardians.*.full_name' => ['required', 'string', 'max:255'], 'guardians.*.relationship' => ['nullable', Rule::in(ApplicationGuardian::relationships())], 'guardians.*.phone' => ['nullable', 'string', 'max:50'], 'guardians.*.email' => ['nullable', 'email', 'max:255'], 'guardians.*.occupation' => ['nullable', 'string', 'max:255'], 'guardians.*.address' => ['nullable', 'string', 'max:255'], 'guardians.*.is_primary' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array'], 'documents.*.crm_file_id' => ['required', 'string', 'max:255'], 'documents.*.file_type_code' => ['required', 'string', 'max:255'], 'documents.*.file_type_name' => ['nullable', 'string', 'max:255'], 'documents.*.page_index' => ['nullable', 'integer', 'min:0'], 'documents.*.original_name' => ['nullable', 'string', 'max:255'], 'documents.*.link' => ['required', 'string', 'max:2048'], 'documents.*.mime_type' => ['nullable', 'string', 'max:100'], 'documents.*.size' => ['nullable', 'integer', 'min:0'], 'documents.*.status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
