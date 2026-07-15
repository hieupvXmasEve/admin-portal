<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use Closure;
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
 *   already be linked to a FinanceObligation.
 */
class CreateBatchDngFromChargesAction
{
    public function __construct(
        protected ?ReserveAndPushSingleFeeDngAction $guardedReservationAction = null,
        private readonly ?AcademicFinanceChargeSourceGateway $academicSources = null,
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
     * @return array{created: int, needs_review: int, failed: int, errors: string[], outcomes: list<array{student_id: int, reservation_id: int, status: string}>}
     */
    public function handle(array $data): array
    {
        $studentIds = $data['student_ids'];
        $dngFeeType = $data['dng_fee_type'];
        $dueDate = $data['due_date'];
        $semesterId = (int) $data['semester_id'];
        $description = $data['description'];
        $estimateTime = $data['estimate_time'];
        $chargeTypes = ListDngWorklistQuery::mapFeeTypeToChargeTypes($dngFeeType);

        $created = 0;
        $needsReview = 0;
        $failed = 0;
        $errors = [];
        $outcomes = [];

        foreach ($studentIds as $studentId) {
            $reservationIdBeforeAttempt = (int) (DngPaymentRequest::query()->max('id') ?? 0);

            try {
                $reservation = $this->processStudent(
                    studentId: (int) $studentId,
                    dngFeeType: $dngFeeType,
                    chargeTypes: $chargeTypes,
                    semesterId: $semesterId,
                    dueDate: $dueDate,
                    description: $description,
                    estimateTime: $estimateTime,
                );
                $outcomes[] = [
                    'student_id' => (int) $studentId,
                    'reservation_id' => (int) $reservation->id,
                    'status' => $reservation->status,
                ];
                if ($reservation->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
                    $created++;
                } else {
                    $needsReview++;
                }
            } catch (\Throwable $e) {
                $heldReservation = DngPaymentRequest::query()
                    ->where('id', '>', $reservationIdBeforeAttempt)
                    ->where('student_id', $studentId)
                    ->where('provider_rail', 'dng')
                    ->where('fee_type', $dngFeeType)
                    ->where('semester_id', $semesterId)
                    ->whereIn('status', [
                        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                        DngPaymentRequest::STATUS_NEEDS_REVIEW,
                    ])
                    ->latest('id')
                    ->first();
                if ($heldReservation !== null) {
                    $this->linkInstallmentsToReservation($heldReservation);
                    $outcomes[] = [
                        'student_id' => (int) $studentId,
                        'reservation_id' => (int) $heldReservation->id,
                        'status' => $heldReservation->status,
                    ];
                    $needsReview++;

                    continue;
                }

                $failed++;
                $errors[] = "Student #{$studentId}: {$e->getMessage()}";
                Log::warning('CreateBatchDngFromChargesAction: failed for student', [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $created,
            'needs_review' => $needsReview,
            'failed' => $failed,
            'errors' => $errors,
            'outcomes' => $outcomes,
        ];
    }

    /**
     * Process a single student: guard missing HL/PTL obligations, then push DNG.
     *
     * @param  array<int, string>  $chargeTypes
     */
    private function processStudent(
        int $studentId,
        string $dngFeeType,
        array $chargeTypes,
        int $semesterId,
        string $dueDate,
        string $description,
        string $estimateTime,
    ): DngPaymentRequest {
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
            ->groupBy('finance_charge_id')
            ->map(fn ($rows) => $rows->first());
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
        $this->linkInstallmentsToReservation($reservation);

        return $reservation;
    }

    private function linkInstallmentsToReservation(DngPaymentRequest $reservation): void
    {
        $installmentIds = $reservation->reservationTargets()
            ->whereNotNull('finance_charge_installment_id')
            ->pluck('finance_charge_installment_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        if ($installmentIds === []) {
            return;
        }

        app(SettlementMutationGuard::class)->handleIfChanged((int) $reservation->billing_account_id, function ($_billingAccount, Closure $markChanged) use ($installmentIds, $reservation): void {
            $updated = FinanceChargeInstallment::query()->whereIn('id', $installmentIds)->update([
                'dng_payment_request_id' => $reservation->id,
                'status' => $reservation->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
                    ? FinanceChargeInstallment::STATUS_AWAITING_PAYMENT
                    : FinanceChargeInstallment::STATUS_PENDING,
                'last_push_error' => $reservation->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
                    ? null
                    : "DNG reservation #{$reservation->id} ended as {$reservation->status} and requires Finance review.",
                'last_push_attempted_at' => now(),
            ]);
            if ($updated > 0) {
                $markChanged();
            }
        });
    }

    /**
     * For HL fee_type: fail fast when a chargeable retake source has no
     * legacy charge link and no FinanceObligation.
     */
    private function assertNoMissingRetakeObligations(Student $student, int $semesterId): void
    {
        foreach ($this->academicSources()->chargeableRetakeSourcesForStudent((int) $student->id, $semesterId, lockForUpdate: true) as $source) {
            if (! $this->financeObligationExists(
                $source->source_kind,
                $source->source_ref,
                FinanceCharge::TYPE_RETAKE_FEE,
            )) {
                throw new \RuntimeException(
                    "missing_finance_obligation: Retake registration #{$source->id} has no Finance obligation."
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
        foreach ($this->academicSources()->chargeableExamResitSourcesForStudent((int) $student->id, $semesterId, lockForUpdate: true) as $source) {
            if (! $this->financeObligationExists(
                $source->source_kind,
                $source->source_ref,
                FinanceCharge::TYPE_EXAM_RESIT_FEE,
            )) {
                throw new \RuntimeException(
                    "missing_finance_obligation: Exam resit attempt #{$source->id} has no Finance obligation."
                );
            }
        }
    }

    private function financeObligationExists(string $sourceKind, string $sourceRef, string $obligationType): bool
    {
        return FinanceObligation::query()
            ->where('source_system', AcademicFinanceSourceKeys::SOURCE_SYSTEM)
            ->where('source_kind', $sourceKind)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', $obligationType)
            ->exists();
    }

    private function academicSources(): AcademicFinanceChargeSourceGateway
    {
        return $this->academicSources ?? app(AcademicFinanceChargeSourceGateway::class);
    }
}
