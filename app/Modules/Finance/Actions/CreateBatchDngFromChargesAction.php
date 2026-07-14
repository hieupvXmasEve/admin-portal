<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Dng\Support\DngCollectionCutover;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Support\Facades\Log;

/**
 * Create DNG payment requests from active charges for a batch of students.
 *
 * Unlike the legacy BatchDngApiController / DngPaymentController flows,
 * this action always links each DNG to its source charges via the
 * dng_payment_request_charges pivot, enabling precise per-charge allocation
 * on webhook confirmation.
 *
 * DNG pushes only existing Finance payables:
 * - Retake/resit source rows with no charge link and no FinanceObligation are
 *   blocked as missing_finance_obligation (no silent charge create).
 * - BHYT (wave 2): every pushed payable must already be linked to a
 *   FinanceObligation; unlinked legacy charges are blocked until backfill.
 * - HP (wave 3 tuition_term + wave 5 egc_level_fee): every pushed payable must
 *   already be linked to a FinanceObligation. course_fee is formally retired
 *   (wave 6) and is excluded from the HP reverse charge-type map.
 */
class CreateBatchDngFromChargesAction
{
    public function __construct(
        protected ?ReserveAndPushSingleFeeDngAction $guardedReservationAction = null,
        protected ?DngCollectionCutover $cutover = null,
    ) {}

    /**
     * @param  array{
     *     student_ids: int[],
     *     dng_fee_type: string,
     *     due_date: string,
     *     semester_id: int,
     *     description: string,
     *     estimate_time: string,
     * }  $data
     * @return array{created: int, failed: int, cancelled_old: int, errors: string[]}
     */
    public function handle(array $data): array
    {
        ($this->cutover ?? new DngCollectionCutover)->assertCollectionAllowed();
        $studentIds = $data['student_ids'];
        $dngFeeType = $data['dng_fee_type'];
        $dueDate = $data['due_date'];
        $semesterId = (int) $data['semester_id'];
        $description = $data['description'];
        $estimateTime = $data['estimate_time'];
        $chargeTypes = ListDngWorklistQuery::mapFeeTypeToChargeTypes($dngFeeType);

        $created = 0;
        $failed = 0;
        $cancelledOld = 0;
        $errors = [];

        foreach ($studentIds as $studentId) {
            try {
                $result = $this->processStudent(
                    studentId: (int) $studentId,
                    dngFeeType: $dngFeeType,
                    chargeTypes: $chargeTypes,
                    semesterId: $semesterId,
                    dueDate: $dueDate,
                    description: $description,
                    estimateTime: $estimateTime,
                );

                $created++;
                $cancelledOld += $result['cancelled_old'];
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Student #{$studentId}: {$e->getMessage()}";
                Log::warning('CreateBatchDngFromChargesAction: failed for student', [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('created', 'failed', 'cancelledOld', 'errors') + ['cancelled_old' => $cancelledOld];
    }

    /**
     * Process a single student: guard missing HL/PTL obligations, then push DNG.
     *
     * @param  array<int, string>  $chargeTypes
     * @return array{cancelled_old: int}
     */
    private function processStudent(
        int $studentId,
        string $dngFeeType,
        array $chargeTypes,
        int $semesterId,
        string $dueDate,
        string $description,
        string $estimateTime,
    ): array {
        $student = Student::query()->findOrFail($studentId);
        if ($dngFeeType === 'HL') {
            $this->assertNoMissingRetakeObligations($student, $semesterId);
        }
        if ($dngFeeType === 'PTL') {
            $this->assertNoMissingExamResitObligations($student, $semesterId);
        }

        $charges = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->whereIn('charge_type', $chargeTypes)
            ->where('semester_id', $semesterId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->get(['id', 'finance_obligation_id']);
        if ($charges->isEmpty()) {
            throw new \RuntimeException('Không tìm thấy khoản phí có thể thu cho loại DNG này.');
        }
        if ($charges->contains(fn (FinanceCharge $charge): bool => $charge->finance_obligation_id === null)) {
            throw new \RuntimeException('missing_finance_obligation: DNG cannot collect an unmaterialized Finance charge.');
        }

        // All batch requests reserve canonical payable-line targets before the
        // provider call. Partial collection is derived only from a pending
        // installment; callers cannot supply an amount.
        $lines = InvoiceLine::query()
            ->with('charge:id,finance_obligation_id')
            ->where('status', 'active')
            ->whereHas('charge', function ($query) use ($studentId, $chargeTypes, $semesterId): void {
                $query->where('student_id', $studentId)
                    ->whereIn('charge_type', $chargeTypes)
                    ->where('semester_id', $semesterId)
                    ->where('status', FinanceCharge::STATUS_ACTIVE);
            })
            ->get();
        if ($lines->isEmpty()) {
            throw new \RuntimeException('Settlement Position has no supported payable lines for this DNG fee type.');
        }
        $installments = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $lines->pluck('charge_id'))
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->orderBy('finance_charge_id')
            ->orderBy('installment_no')
            ->get()
            ->keyBy('finance_charge_id');
        $targetAmounts = [];
        $installmentIdsByLine = [];
        foreach ($lines as $line) {
            $installment = $installments->get($line->charge_id);
            if ($installment !== null) {
                $targetAmounts[(int) $line->id] = (string) $installment->amount;
                $installmentIdsByLine[(int) $line->id] = (int) $installment->id;
            }
        }

        $reservation = ($this->guardedReservationAction ?? app(ReserveAndPushSingleFeeDngAction::class))->handle($studentId, $dngFeeType, [
            'description' => $description,
            'semester_id' => $semesterId,
            'due_date' => $dueDate,
            'estimate_time' => $estimateTime,
        ], $lines->pluck('id')->map(fn ($id): int => (int) $id)->all(), $targetAmounts, $installmentIdsByLine);
        if ($installmentIdsByLine !== []) {
            $billingAccountId = (int) app(BillingAccountProvisioner::class)
                ->forStudent($studentId)
                ->id;

            app(SettlementMutationGuard::class)->handle($billingAccountId, function () use ($installmentIdsByLine, $reservation): void {
                FinanceChargeInstallment::query()->whereIn('id', $installmentIdsByLine)->update([
                    'dng_payment_request_id' => $reservation->id,
                    'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                    'last_push_error' => null,
                    'last_push_attempted_at' => now(),
                ]);
            });
        }

        return ['cancelled_old' => 0];
    }

    /**
     * For HL fee_type: fail fast when a chargeable retake source has no
     * legacy charge link and no FinanceObligation.
     */
    private function assertNoMissingRetakeObligations(Student $student, int $semesterId): void
    {
        $pendingRegistrations = CourseRetakeRegistration::query()
            ->where('student_id', $student->id)
            ->where(function ($query) use ($semesterId): void {
                $query->where('charge_semester_id', $semesterId)
                    ->orWhere(function ($legacyQuery) use ($semesterId): void {
                        $legacyQuery->whereNull('charge_semester_id')
                            ->where('semester_id', $semesterId);
                    });
            })
            ->whereIn('status', [
                CourseRetakeRegistration::STATUS_APPROVED,
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
            ])
            ->whereIn('hq_fee_status', [
                CourseRetakeRegistration::HQ_FEE_PENDING,
                CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
            ])
            ->whereNull('finance_charge_id')
            ->lockForUpdate()
            ->get();

        foreach ($pendingRegistrations as $registration) {
            $sourceRef = AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration);

            if (! $this->financeObligationExists(
                AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                $sourceRef,
                FinanceCharge::TYPE_RETAKE_FEE,
            )) {
                throw new \RuntimeException(
                    "missing_finance_obligation: Retake registration #{$registration->id} has no Finance obligation."
                );
            }
        }
    }

    /**
     * For PTL fee_type: fail fast when a chargeable exam-resit source has no
     * legacy charge link and no FinanceObligation.
     */
    private function assertNoMissingExamResitObligations(Student $student, int $semesterId): void
    {
        $pendingAttempts = ExamResitAttempt::query()
            ->where('student_id', $student->id)
            ->where('charge_semester_id', $semesterId)
            ->whereIn('status', [
                ExamResitAttempt::STATUS_APPROVED,
                ExamResitAttempt::STATUS_SCHEDULED,
            ])
            ->whereIn('hq_fee_status', [
                ExamResitAttempt::HQ_FEE_PENDING,
                ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
            ])
            ->whereNull('finance_charge_id')
            ->lockForUpdate()
            ->get();

        foreach ($pendingAttempts as $attempt) {
            $sourceRef = AcademicFinanceObligationSource::examResitAttemptRef($attempt);

            if (! $this->financeObligationExists(
                AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                $sourceRef,
                FinanceCharge::TYPE_EXAM_RESIT_FEE,
            )) {
                throw new \RuntimeException(
                    "missing_finance_obligation: Exam resit attempt #{$attempt->id} has no Finance obligation."
                );
            }
        }
    }

    private function financeObligationExists(string $sourceKind, string $sourceRef, string $obligationType): bool
    {
        return FinanceObligation::query()
            ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', $sourceKind)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', $obligationType)
            ->exists();
    }
}
