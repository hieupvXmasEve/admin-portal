<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetakeCourseChargeAction
{
    public function __construct(
        protected FinanceIntakeContract $intake,
        protected ReserveAndPushSingleFeeDngAction $reserveAndPushDng,
    ) {}

    /**
     * Create a single aggregate DNG payment request for all payment_pending retake
     * registrations of a given student (one HL charge per unit).
     *
     * Behavior:
     * - Collects all CourseRetakeRegistrations in STATUS_PAYMENT_PENDING for the student.
     * - Legacy path (STATUS_APPROVED): creates the FinanceCharge first, transitions to payment_pending.
     * - Sums all charge amounts into a single DNG request (one HL record per student).
     * - Inserts a dng_payment_request_charges pivot row per charge for precise allocation on webhook.
     *
     * @param  array{
     *   registration_id: int,
     *   amount?: float|null,
     *   payment_deadline?: string|null,
     * }  $data
     */
    public function handle(array $data): CourseRetakeRegistration
    {
        $reservationInput = DB::transaction(function () use ($data): array {
            // Lock and validate the triggering registration
            $registration = CourseRetakeRegistration::lockForUpdate()->findOrFail($data['registration_id']);

            $allowedStatuses = [
                CourseRetakeRegistration::STATUS_APPROVED,
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
            ];

            if (! in_array($registration->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Chỉ có thể tạo yêu cầu DNG cho đăng ký ở trạng thái approved hoặc payment_pending.'],
                ]);
            }

            $student = $registration->student;
            $userId = auth()->id();
            $paymentDeadline = $data['payment_deadline'] ?? null;

            // Registration still approved → ensure intake debit + transition
            if ($registration->status === CourseRetakeRegistration::STATUS_APPROVED) {
                $unit = $registration->unit;
                $facts = [
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
                    'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                ];

                if ($paymentDeadline) {
                    $facts['due_date'] = $paymentDeadline;
                }

                $result = $this->intake->request(new FinanceIntakeData(
                    source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                    source_ref: AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                    financial_effect: FinancialEffect::Debit,
                    obligation_type: AcademicFinanceObligationSource::RETAKE_FEE,
                    facts: $facts,
                ));

                $chargeId = $result->finance_charge_id
                    ?? $this->findActiveIntakeCharge($registration)?->id;

                if ($chargeId === null) {
                    throw ValidationException::withMessages([
                        'registration_id' => ['Finance intake did not materialize a retake charge.'],
                    ]);
                }

                $registration->transitionToPaymentPending($userId);
                $registration->refresh();
            }

            // Collect all payment_pending registrations for this student (including this one)
            // Lock them all to prevent concurrent DNG creation
            $pendingRegistrations = CourseRetakeRegistration::query()
                ->where('student_id', $registration->student_id)
                ->where('status', CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
                ->lockForUpdate()
                ->get();

            $obligationIds = FinanceObligation::query()
                ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
                ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
                ->where('obligation_type', AcademicFinanceObligationSource::RETAKE_FEE)
                ->whereIn(
                    'source_ref',
                    $pendingRegistrations
                        ->map(fn (CourseRetakeRegistration $item): string => AcademicFinanceObligationSource::courseRetakeRegistrationRef($item))
                        ->all(),
                )
                ->pluck('id');
            $chargeIds = FinanceCharge::query()
                ->whereIn('finance_obligation_id', $obligationIds)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();
            $lineIds = InvoiceLine::query()
                ->whereIn('charge_id', $chargeIds)
                ->where('status', 'active')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($lineIds === []) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Không tìm thấy payable line hợp lệ để tạo DNG.'],
                ]);
            }

            // Build description listing all units
            $unitCodes = $pendingRegistrations
                ->map(fn (CourseRetakeRegistration $item) => $item->unit?->code ?? "#{$item->unit_id}")
                ->join(', ');
            $description = "Phí học lại: {$unitCodes}";

            // Update payment deadline on all covered registrations
            if ($paymentDeadline) {
                $pendingRegistrations->each(
                    fn (CourseRetakeRegistration $r) => $r->update(['payment_deadline' => $paymentDeadline])
                );
            }

            return [
                'registration_id' => (int) $registration->id,
                'student_id' => (int) $registration->student_id,
                'line_ids' => $lineIds,
                'description' => $description,
                'semester_id' => (int) ($registration->charge_semester_id ?? $registration->semester_id),
                'due_date' => $paymentDeadline ?? now()->addDays(30)->toDateString(),
            ];
        });

        // The reservation commits before its provider call; no registration or
        // intake transaction remains open while DNG is contacted.
        $this->reserveAndPushDng->handle(
            $reservationInput['student_id'],
            'HL',
            [
                'description' => $reservationInput['description'],
                'semester_id' => $reservationInput['semester_id'],
                'due_date' => $reservationInput['due_date'],
                'estimate_time' => now()->format('m/y'),
            ],
            $reservationInput['line_ids'],
        );

        return CourseRetakeRegistration::query()->findOrFail($reservationInput['registration_id']);
    }

    private function findActiveIntakeCharge(CourseRetakeRegistration $registration): ?FinanceCharge
    {
        $obligationId = FinanceObligation::query()
            ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
            ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration))
            ->where('obligation_type', AcademicFinanceObligationSource::RETAKE_FEE)
            ->value('id');

        if ($obligationId === null) {
            // Legacy rows still keyed by morph source until wave-7 data closure.
            return FinanceCharge::query()
                ->where('source_type', CourseRetakeRegistration::class)
                ->where('source_id', $registration->id)
                ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->first();
        }

        return FinanceCharge::query()
            ->where('finance_obligation_id', $obligationId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->first();
    }
}
