<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ensure an exam resit fee debit exists for an HQ-pending attempt via intake.
 *
 * Wave 7: materializer-only; charge identity resolved from FinanceObligation.
 */
class CreateExamResitChargeSimpleAction
{
    public function __construct(
        private readonly FinanceIntakeContract $intake,
    ) {}

    /**
     * @param  array{
     *   attempt_id: int,
     *   amount?: float|null,
     *   due_date?: string|null,
     * }  $data
     */
    public function handle(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data) {
            $attempt = ExamResitAttempt::lockForUpdate()->findOrFail($data['attempt_id']);

            if ($attempt->hq_fee_status !== ExamResitAttempt::HQ_FEE_PENDING) {
                throw ValidationException::withMessages([
                    'attempt_id' => ['Chỉ có thể tạo phí cho nguồn thi lại đang chờ HQ tạo phí.'],
                ]);
            }

            $unit = $attempt->unit;
            $facts = [
                'student_id' => $attempt->student_id,
                'semester_id' => $attempt->charge_semester_id,
                'campus_id' => $attempt->campus_id,
                'unit_id' => $attempt->unit_id,
                'academic_record_id' => $attempt->academic_record_id,
                'original_course_offering_id' => $attempt->original_course_offering_id,
                'original_semester_id' => $attempt->original_semester_id,
                'operation_semester_id' => $attempt->operation_semester_id,
                'charge_semester_id' => $attempt->charge_semester_id,
                'request_sequence' => $attempt->request_sequence,
                'syllabus_template_id' => $attempt->syllabus_template_id,
                'max_attempts_snapshot' => $attempt->max_attempts_snapshot,
                'late_payment_grace_days_snapshot' => $attempt->late_payment_grace_days_snapshot,
                'allow_unpaid_sitting_snapshot' => $attempt->allow_unpaid_sitting_snapshot,
                'description' => "Phí thi lại: {$unit?->code} - {$unit?->name}",
            ];

            if (! empty($data['due_date'])) {
                $facts['due_date'] = $data['due_date'];
            }

            $result = $this->intake->request(new FinanceIntakeData(
                source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                source_kind: AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                source_ref: AcademicFinanceObligationSource::examResitAttemptRef($attempt),
                financial_effect: FinancialEffect::Debit,
                obligation_type: AcademicFinanceObligationSource::EXAM_RESIT_FEE,
                facts: $facts,
            ));

            if ($result->finance_obligation_id === null || $result->finance_charge_id === null) {
                throw ValidationException::withMessages([
                    'attempt_id' => ['Finance intake did not materialize an exam resit charge.'],
                ]);
            }

            $attempt->transitionToChargeCreated((int) auth()->id());

            return $attempt->fresh();
        });
    }
}
