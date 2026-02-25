<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\DeferCaseItem;
use App\Models\FinanceCharge;
use App\Models\Student;
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
            'missing_documents' => ['nullable', 'boolean'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'exists:upload_records,id'],
        ];

        // Add action-specific rules based on action_type
        $actionType = $this->input('action_type');

        return match ($actionType) {
            StudentActionType::ACADEMIC_DEFER->value => array_merge($baseRules, $this->deferRules()),
            StudentActionType::ACADEMIC_RESUME->value => array_merge($baseRules, $this->resumeRules()),
            StudentActionType::ADMISSION_DEFERRAL->value => array_merge($baseRules, $this->admissionDeferralRules()),
            StudentActionType::ACADEMIC_DROPOUT->value => array_merge($baseRules, $this->dropoutRules()),
            StudentActionType::CAMPUS_TRANSFER->value => array_merge($baseRules, $this->campusTransferRules()),
            default => $baseRules,
        };
    }

    protected function deferRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id'],
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
            // Defer Case fields
            'defer_scope_type' => ['required', 'string', 'in:FULL,COURSES'],
            'defer_fee_policy' => ['required', 'string', 'in:PRESERVE,FORFEIT,PARTIAL'],
            'defer_preserve_amount' => ['nullable', 'numeric', 'min:0', 'required_if:defer_fee_policy,PARTIAL'],
            'defer_course_registration_ids' => ['nullable', 'array', 'required_if:defer_scope_type,COURSES'],
            'defer_egc_charge_ids' => ['nullable', 'array'],
            'defer_course_registration_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_registrations', 'id')->where(function ($query) {
                    return $query
                        ->where('student_id', $this->input('student_id'))
                        ->where('semester_id', $this->input('from_semester_id'));
                }),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $registration = CourseRegistration::find($value);
                    if (! $registration) {
                        return;
                    }

                    if ($registration->registration_status === 'defer') {
                        $fail('Course has already been deferred for this semester.');

                        return;
                    }

                    $semesterId = (int) $this->input('from_semester_id');
                    $studentId = (int) $this->input('student_id');

                    $hasDefer = DeferCaseItem::query()
                        ->where('course_registration_id', $registration->id)
                        ->whereHas('deferCase', function ($query) use ($semesterId, $studentId) {
                            $query->where('student_id', $studentId)
                                ->where('semester_id', $semesterId);
                        })
                        ->exists();

                    if ($hasDefer) {
                        $fail('Course has already been deferred for this semester.');
                    }
                },
            ],
            'defer_egc_charge_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('finance_charges', 'id')->where(function ($query) {
                    return $query
                        ->where('student_id', $this->input('student_id'))
                        ->where('semester_id', $this->input('from_semester_id'))
                        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                        ->where('status', FinanceCharge::STATUS_ACTIVE);
                }),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $feePolicy = $this->input('defer_fee_policy') ?? 'FORFEIT';
                    if ($feePolicy === 'FORFEIT') {
                        $fail('Fee policy must be preserve or partial when selecting EGC levels.');

                        return;
                    }

                    $student = Student::find($this->input('student_id'));
                    if ($student && $student->status !== 'intake_pre_uni_gc') {
                        $fail('EGC level preserve is only available for EGC students.');

                        return;
                    }

                    $charge = FinanceCharge::find($value);
                    if ($charge && ! $charge->is_fully_paid) {
                        $fail('EGC fee must be fully paid before preserve is allowed.');

                        return;
                    }

                    $hasCredit = FinanceCharge::query()
                        ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                        ->where('source_type', FinanceCharge::class)
                        ->where('source_id', $value)
                        ->exists();

                    if ($hasCredit) {
                        $fail('Selected EGC level has already been preserved.');
                    }
                },
            ],
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

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'The selected student does not exist.',
            'action_type.required' => 'Action type is required.',
            'action_type.Illuminate\Validation\Rules\Enum' => 'Invalid action type.',
            'reason.required' => 'Reason is required.',
            'reason.max' => 'Reason cannot exceed 5000 characters.',
            'from_semester_id.required' => 'From semester is required for defer action.',
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
            // Defer case messages
            'defer_scope_type.required' => 'Defer scope type is required.',
            'defer_scope_type.in' => 'Defer scope type must be FULL or COURSES.',
            'defer_fee_policy.required' => 'Fee policy is required.',
            'defer_fee_policy.in' => 'Fee policy must be PRESERVE, FORFEIT, or PARTIAL.',
            'defer_preserve_amount.required_if' => 'Preserve amount is required when fee policy is PARTIAL.',
            'defer_preserve_amount.numeric' => 'Preserve amount must be a number.',
            'defer_preserve_amount.min' => 'Preserve amount must be at least 0.',
            'defer_course_registration_ids.required_if' => 'Course selection is required when scope is COURSES.',
            'defer_course_registration_ids.*.exists' => 'One or more selected courses do not exist.',
            'defer_course_registration_ids.*.distinct' => 'Duplicate courses are not allowed.',
            'defer_egc_charge_ids.*.exists' => 'One or more selected EGC fees do not exist.',
            'defer_egc_charge_ids.*.distinct' => 'Duplicate EGC fees are not allowed.',
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
