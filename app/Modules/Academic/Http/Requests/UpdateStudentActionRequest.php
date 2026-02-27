<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use App\Enums\StudentActionType;
use App\Models\StudentActionLog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var StudentActionLog $actionLog */
        $actionLog = $this->route('actionLog');
        $actionType = $actionLog->action_type;

        $baseRules = [
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

        return match ($actionType->value) {
            StudentActionType::NE_ENROLLMENT->value => array_merge($baseRules, $this->neEnrollmentRules()),
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
        /** @var StudentActionLog $actionLog */
        $actionLog = $this->route('actionLog');

        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'return_semester_id' => [
                'required',
                'integer',
                'exists:semesters,id',
                // function (string $attribute, mixed $value, \Closure $fail) use ($actionLog): void {
                //     $scopeType = $actionLog->deferCase?->scope_type ?? 'FULL';
                //     $fromSemesterId = $this->input('from_semester_id');

                //     if ($scopeType !== 'COURSES' && $fromSemesterId && (int) $value === (int) $fromSemesterId) {
                //         $fail('Return semester must be different from from semester.');
                //     }
                // },
            ],
        ];
    }

    protected function neEnrollmentRules(): array
    {
        return [
            'from_semester_id' => ['required', 'integer', 'exists:semesters,id'],
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
        ];
    }
}
