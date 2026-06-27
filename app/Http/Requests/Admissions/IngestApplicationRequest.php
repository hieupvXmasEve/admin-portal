<?php

declare(strict_types=1);

namespace App\Http\Requests\Admissions;

use App\Http\Responses\ApiResponse;
use App\Models\ApplicationGuardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a CRM ingestion payload for an Application plus its Guardians and
 * Document references (ADR-0004).
 *
 * The route group already authenticated the service token and asserted the
 * `admissions:ingest` ability, so authorization here is open. Validation failures
 * surface in the {@see ApiResponse} envelope via the global
 * API exception handler.
 *
 * Lifecycle is never set by the CRM: `status` and the approve/reject/revoke audit
 * columns are not accepted here — an Application is born `pending` and only staff
 * move it (ADR-0001/0004). The single English-test result is accepted as a nested
 * `english_test` object and mapped onto the inline columns by the ingestion
 * service, decoupling the CRM contract from Swinx's column names.
 */
class IngestApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // External key + CRM-issued code.
            'crm_admission_id' => 'required|string|max:255',
            'student_code' => 'nullable|string|max:255',

            // Applicant identity.
            'full_name' => 'required|string|max:255',
            'gender' => 'nullable|string|max:20',
            'ethnicity' => 'nullable|string|max:100',
            'birth_day' => 'nullable|integer|min:1|max:31',
            'birth_month' => 'nullable|integer|min:1|max:12',
            'birth_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'national_id' => 'nullable|string|max:50',

            // Contact.
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'health_information' => 'nullable|string',

            // Admission intent.
            'campus_code' => 'nullable|string|max:50',
            'intended_program' => 'nullable|string|max:255',
            'intended_specialization' => 'nullable|string|max:255',
            'intake' => 'nullable|string|max:100',
            'is_international_applicant' => 'nullable|boolean',
            'exception_units' => 'nullable|string',
            'sut_id' => 'nullable|string|max:100',
            'english_qualifications' => 'nullable|string',
            'study_link_status' => 'nullable|string|max:100',

            // Inline single English-test result (nested; mapped by the service).
            'english_test' => 'nullable|array',
            'english_test.test_type' => 'nullable|string|max:50',
            'english_test.exam_date' => 'nullable|date',
            'english_test.listening' => 'nullable|numeric|between:0,99.99',
            'english_test.reading' => 'nullable|numeric|between:0,99.99',
            'english_test.writing' => 'nullable|numeric|between:0,99.99',
            'english_test.speaking' => 'nullable|numeric|between:0,99.99',
            'english_test.overall' => 'nullable|numeric|between:0,99.99',

            // Guardians (1-n). Replace-set on upsert; at least one becomes primary.
            'guardians' => 'nullable|array',
            'guardians.*.full_name' => 'required|string|max:255',
            'guardians.*.relationship' => ['nullable', Rule::in(ApplicationGuardian::relationships())],
            'guardians.*.phone' => 'nullable|string|max:50',
            'guardians.*.email' => 'nullable|email|max:255',
            'guardians.*.occupation' => 'nullable|string|max:255',
            'guardians.*.address' => 'nullable|string|max:255',
            'guardians.*.is_primary' => 'nullable|boolean',

            // Documents (1-n external link references). Idempotent by crm_file_id.
            'documents' => 'nullable|array',
            'documents.*.crm_file_id' => 'required|string|max:255',
            'documents.*.file_type_code' => 'required|string|max:255',
            'documents.*.file_type_name' => 'nullable|string|max:255',
            'documents.*.page_index' => 'nullable|integer|min:0',
            'documents.*.original_name' => 'nullable|string|max:255',
            'documents.*.link' => 'required|string|max:2048',
            'documents.*.mime_type' => 'nullable|string|max:100',
            'documents.*.size' => 'nullable|integer|min:0',
            'documents.*.status' => 'nullable|string|max:50',
        ];
    }
}
