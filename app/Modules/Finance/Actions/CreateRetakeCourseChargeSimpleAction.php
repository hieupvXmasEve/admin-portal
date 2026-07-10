<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ensure a retake fee debit exists for an approved registration via intake.
 *
 * Wave 7: no direct CreateFinanceChargeAction / source_type coupling. Look up
 * the materialized charge through the FinanceObligation source triple.
 */
class CreateRetakeCourseChargeSimpleAction
{
    public function __construct(
        private readonly FinanceIntakeContract $intake,
    ) {}

    /**
     * @param  array{
     *   registration_id: int,
     *   charge_type?: string,
     *   amount?: float,
     *   description?: string,
     * }  $data
     */
    public function handle(array $data): CourseRetakeRegistration
    {
        return DB::transaction(function () use ($data) {
            $registration = CourseRetakeRegistration::lockForUpdate()->findOrFail($data['registration_id']);

            if ($registration->status !== CourseRetakeRegistration::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Chỉ có thể tạo charge cho đăng ký ở trạng thái approved.'],
                ]);
            }

            $unit = $registration->unit;
            $description = $data['description']
                ?? "Phí học lại: {$unit?->code} - {$unit?->name}";

            $result = $this->intake->request(new FinanceIntakeData(
                source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                source_kind: AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                source_ref: AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                financial_effect: FinancialEffect::Debit,
                obligation_type: AcademicFinanceObligationSource::RETAKE_FEE,
                facts: [
                    'student_id' => $registration->student_id,
                    'semester_id' => $registration->charge_semester_id ?? $registration->semester_id,
                    'campus_id' => $registration->campus_id,
                    'unit_id' => $registration->unit_id,
                    'course_offering_id' => $registration->course_offering_id,
                    'original_academic_record_id' => $registration->original_academic_record_id,
                    'original_semester_id' => $registration->original_semester_id,
                    'operation_semester_id' => $registration->operation_semester_id,
                    'charge_semester_id' => $registration->charge_semester_id,
                    'attempt_number' => $registration->attempt_number,
                    'description' => $description,
                ],
            ));

            $chargeId = $result->finance_charge_id
                ?? $this->chargeIdFromObligation($result->finance_obligation_id);

            if ($chargeId === null) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Finance intake did not materialize a retake charge.'],
                ]);
            }

            $registration->transitionToPaymentPending($chargeId, auth()->id());

            return $registration->fresh();
        });
    }

    private function chargeIdFromObligation(?int $obligationId): ?int
    {
        if ($obligationId === null) {
            return null;
        }

        $charge = FinanceCharge::query()
            ->where('finance_obligation_id', $obligationId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->first();

        return $charge?->id;
    }
}
