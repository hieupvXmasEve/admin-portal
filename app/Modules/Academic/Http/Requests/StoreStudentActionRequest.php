<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use App\Enums\StudentActionType;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreStudentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission check should be handled by Policy/Gate in Controller or Route
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'action_type' => ['required', 'string', new Enum(StudentActionType::class)],
            'reason' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'signed_at' => ['nullable', 'date'],
            'decision_number' => ['nullable', 'string', 'max:255'],
            'decision_signed_at' => ['nullable', 'date'],
            'decision_signer' => ['nullable', 'string', 'max:255'],
            'decision_id' => ['nullable', 'integer', 'exists:student_decisions,id'],
            'missing_documents' => ['nullable', 'boolean'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'exists:upload_records,id'],
        ];

        // Add action-specific rules based on action_type
        $actionType = $this->input('action_type');

        return match ($actionType) {
            StudentActionType::STUDENT_ENROLLMENT_NE->value => array_merge($baseRules, $this->neEnrollmentRules()),
            StudentActionType::STUDENT_MAJOR_ENROLLMENT->value => array_merge($baseRules, $this->majorEnrollmentRules()),
            StudentActionType::ACADEMIC_DEFER->value => array_merge($baseRules, $this->deferRules()),
            StudentActionType::ACADEMIC_RESUME->value => array_merge($baseRules, $this->resumeRules()),
            StudentActionType::ADMISSION_DEFERRAL->value => array_merge($baseRules, $this->admissionDeferralRules()),
            StudentActionType::ACADEMIC_DROPOUT->value => array_merge($baseRules, $this->dropoutRules()),
            StudentActionType::CAMPUS_TRANSFER->value => array_merge($baseRules, $this->campusTransferRules()),
            StudentActionType::WAITING_COURSE_OPENING->value => array_merge($baseRules, $this->waitingCourseOpeningRules()),
            default => $baseRules,
        };
    }

    protected function deferRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id', ...$this->fromSemesterNotBeforeActiveRules()],
            'return_semester_id' => [
                'required',
                'integer',
                'exists:semesters,id',
                // function (string $attribute, mixed $value, \Closure $fail): void {
                //     $scopeType = $this->input('defer_scope_type') ?? 'FULL';
                //     $fromSemesterId = $this->input('from_semester_id');

                //     if ($scopeType !== 'COURSES' && $fromSemesterId && (int) $value === (int) $fromSemesterId) {
                //         $fail('Return semester must be different from from semester.');
                //     }
                // },
            ],
            'egc_defer_from_block_number' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $studentId = (int) $this->input('student_id');
                    $lifecycleStatus = $studentId > 0
                        ? app(ProgramEnrollmentReader::class)->forStudentId($studentId)->legacyCompatibleStatus()
                        : null;

                    $isBlank = $value === null || $value === '';

                    if ($lifecycleStatus === 'intake_pre_uni_gc' && $isBlank) {
                        $fail('EGC defer from block is required for EGC students.');

                        return;
                    }

                    if ($studentId > 0 && $lifecycleStatus !== 'intake_pre_uni_gc' && ! $isBlank) {
                        $fail('EGC defer from block is only available for EGC students.');
                    }
                },
            ],
            // Defer Case fields
            'defer_scope_type' => ['required', 'string', 'in:FULL,COURSES'],
            'defer_fee_policy' => [
                'required',
                'string',
                'in:PRESERVE,FORFEIT',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== 'PRESERVE') {
                        return;
                    }

                    $studentId = (int) $this->input('student_id');
                    $semesterId = (int) $this->input('from_semester_id');
                    if ($studentId <= 0 || $semesterId <= 0) {
                        return;
                    }

                    if (app(StudentLifecycleFinanceReader::class)->paidCashForSemester($studentId, $semesterId) <= 0) {
                        $fail('Fee preserve is only available when the from semester has paid tuition.');
                    }
                },
            ],
            'defer_preserve_amount' => ['nullable', 'numeric', 'min:0'],
            'defer_course_registration_ids' => ['nullable', 'array', 'required_if:defer_scope_type,COURSES'],
            'defer_egc_charge_ids' => ['nullable', 'array'],
            'defer_course_registration_ids.*' => [
                'integer',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $semesterId = (int) $this->input('from_semester_id');
                    $studentId = (int) $this->input('student_id');
                    $registrationId = (int) $value;
                    $deferredIds = app(StudentLifecycleFinanceReader::class)
                        ->deferredCourseRegistrationIds($studentId, $semesterId);

                    if (in_array($registrationId, $deferredIds, true)) {
                        $fail('Course has already been deferred for this semester.');

                        return;
                    }

                    $deferableIds = app(StudentLifecycleCourseRegistrationGateway::class)
                        ->deferableIds($studentId, $semesterId);
                    if (! in_array($registrationId, $deferableIds, true)) {
                        $fail('Selected course is not eligible for defer in this semester.');
                    }
                },
            ],
            'defer_egc_charge_ids.*' => [
                'integer',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $feePolicy = $this->input('defer_fee_policy') ?? 'FORFEIT';
                    if ($feePolicy === 'FORFEIT') {
                        $fail('Fee policy must be preserve when selecting EGC levels.');

                        return;
                    }

                    $studentId = (int) $this->input('student_id');
                    $studyStage = $studentId > 0
                        ? app(ProgramEnrollmentReader::class)->forStudentId($studentId)->studyStage
                        : null;
                    if ($studentId > 0 && $studyStage !== 'intake_pre_uni_gc') {
                        $fail('EGC level preserve is only available for EGC students.');

                        return;
                    }

                    $error = app(StudentLifecycleFinanceReader::class)->egcPreserveValidationError(
                        (int) $value,
                        $studentId,
                        $this->input('from_semester_id') !== null ? (int) $this->input('from_semester_id') : null,
                    );

                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
        ];
    }

    protected function neEnrollmentRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id', ...$this->fromSemesterNotBeforeActiveRules()],
        ];
    }

    protected function majorEnrollmentRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id', ...$this->fromSemesterNotBeforeActiveRules()],
        ];
    }

    protected function resumeRules(): array
    {
        return [
            'return_semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ];
    }

    protected function admissionDeferralRules(): array
    {
        return [
            'intended_intake_semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ];
    }

    protected function dropoutRules(): array
    {
        return [
            'dropout_semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ];
    }

    protected function campusTransferRules(): array
    {
        return [
            'from_campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'to_campus_id' => [
                'required',
                'integer',
                'exists:campuses,id',
                'different:from_campus_id',
            ],
            'effective_at' => ['required', 'date'],
            'effective_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ];
    }

    protected function waitingCourseOpeningRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id', ...$this->fromSemesterNotBeforeActiveRules()],
            'egc_defer_from_block_number' => ['required', 'integer', Rule::in([1, 2])],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    protected function fromSemesterNotBeforeActiveRules(): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail): void {
                $currentPeriod = app(AcademicPeriodReader::class)->current();
                if ($currentPeriod === null) {
                    return;
                }

                $selectedSemester = app(GetSemesterFilterOptionsQuery::class)->find((int) $value);
                if ($selectedSemester === null) {
                    return;
                }

                if (
                    $selectedSemester->start_date
                    && $currentPeriod->start_date !== null
                    && $selectedSemester->start_date->lt($currentPeriod->start_date)
                ) {
                    $fail('From semester cannot be before the current semester.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'The selected student does not exist.',
            'action_type.required' => 'Action type is required.',
            'action_type.Illuminate\Validation\Rules\Enum' => 'Invalid action type.',
            'reason.required' => 'Reason is required.',
            'reason.max' => 'Reason cannot exceed 5000 characters.',
            'from_semester_id.required' => 'From semester is required for this action.',
            'from_semester_id.exists' => 'The selected from semester does not exist.',
            'return_semester_id.required' => 'Return semester is required.',
            'return_semester_id.exists' => 'The selected return semester does not exist.',
            'return_semester_id.different' => 'Return semester must be different from from semester.',
            'intended_intake_semester_id.required' => 'Intended intake semester is required for admission deferral.',
            'intended_intake_semester_id.exists' => 'The selected intended intake semester does not exist.',
            'dropout_semester_id.required' => 'Dropout semester is required.',
            'dropout_semester_id.exists' => 'The selected dropout semester does not exist.',
            'from_campus_id.required' => 'From campus is required for campus transfer.',
            'from_campus_id.exists' => 'The selected from campus does not exist.',
            'to_campus_id.required' => 'Target campus is required for campus transfer.',
            'to_campus_id.exists' => 'The selected target campus does not exist.',
            'to_campus_id.different' => 'Target campus must be different from current campus.',
            'effective_at.required' => 'Effective date is required for campus transfer.',
            'effective_at.date' => 'Effective date must be a valid date.',
            'egc_defer_from_block_number.required' => 'Block is required for Chờ mở môn action.',
            'egc_defer_from_block_number.integer' => 'Block must be 1 or 2.',
            'egc_defer_from_block_number.in' => 'Block must be 1 or 2.',
            // Defer case messages
            'defer_scope_type.required' => 'Defer scope type is required.',
            'defer_scope_type.in' => 'Defer scope type must be FULL or COURSES.',
            'defer_fee_policy.required' => 'Fee policy is required.',
            'defer_fee_policy.in' => 'Fee policy must be PRESERVE or FORFEIT.',
            'defer_preserve_amount.numeric' => 'Preserve amount must be a number.',
            'defer_preserve_amount.min' => 'Preserve amount must be at least 0.',
            'defer_course_registration_ids.required_if' => 'Course selection is required when scope is COURSES.',
            'defer_course_registration_ids.*.exists' => 'One or more selected courses do not exist.',
            'defer_course_registration_ids.*.distinct' => 'Duplicate courses are not allowed.',
            'defer_egc_charge_ids.*.exists' => 'One or more selected EGC fees do not exist.',
            'defer_egc_charge_ids.*.distinct' => 'Duplicate EGC fees are not allowed.',
            'egc_defer_from_block_number.integer' => 'EGC defer from block must be 1 or 2.',
            'egc_defer_from_block_number.in' => 'EGC defer from block must be 1 or 2.',
        ];
    }

    /**
     * Get validated data with user_id included.
     */
    public function validatedWithUser(): array
    {
        return array_merge($this->validated(), [
            'changed_by_user_id' => $this->user()->id,
        ]);
    }
}
