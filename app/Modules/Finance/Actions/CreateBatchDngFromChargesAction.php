<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Models\Student;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Create DNG payment requests from active charges for a batch of students.
 *
 * Unlike the legacy BatchDngApiController / DngPaymentController flows,
 * this action always links each DNG to its source charges via the
 * dng_payment_request_charges pivot, enabling precise per-charge allocation
 * on webhook confirmation.
 *
 * DNG pushes only existing Finance payables. Retake/resit source rows that
 * still have no legacy charge link and no FinanceObligation are blocked as
 * missing_finance_obligation instead of being silently charged here.
 */
class CreateBatchDngFromChargesAction
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $campusCodeResolver,
        protected CancelDngPaymentRequestAction $cancelDngAction,
    ) {}

    /**
     * @param  array{
     *     student_ids: int[],
     *     dng_fee_type: string,
     *     due_date: string,
     *     semester_id: int,
     *     description: string,
     *     estimate_time: string,
     *     amount_overrides: array<int, float>|null,
     * }  $data
     * @return array{created: int, failed: int, cancelled_old: int, errors: string[]}
     */
    public function handle(array $data): array
    {
        $studentIds = $data['student_ids'];
        $dngFeeType = $data['dng_fee_type'];
        $dueDate = $data['due_date'];
        $semesterId = (int) $data['semester_id'];
        $description = $data['description'];
        $estimateTime = $data['estimate_time'];
        $amountOverrides = $data['amount_overrides'] ?? [];
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
                    amountOverride: $amountOverrides[(int) $studentId] ?? null,
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
        ?float $amountOverride,
    ): array {
        $cancelledOld = 0;

        return DB::transaction(function () use (
            $studentId,
            $dngFeeType,
            $chargeTypes,
            $semesterId,
            $dueDate,
            $description,
            $estimateTime,
            $amountOverride,
            &$cancelledOld,
        ) {
            // Lock student row to prevent concurrent pushes
            $student = Student::lockForUpdate()->findOrFail($studentId);

            if ($dngFeeType === 'HL') {
                $this->assertNoMissingRetakeObligations($student, $semesterId);
            }

            if ($dngFeeType === 'PTL') {
                $this->assertNoMissingExamResitObligations($student, $semesterId);
            }

            // Load active charges with positive balance
            $charges = $this->loadChargesWithBalance($studentId, $chargeTypes, $semesterId);

            if ($charges->isEmpty()) {
                throw new \RuntimeException('Không tìm thấy khoản phí nào có số dư > 0');
            }

            // Installment-aware total: for each charge, take its next PENDING installment.
            // For charges without installments (legacy / never backfilled), fall back to balance.
            // Admin's amountOverride bypasses installment-awareness entirely (explicit override).
            $nextInstallmentByCharge = FinanceChargeInstallment::query()
                ->whereIn('finance_charge_id', $charges->pluck('id'))
                ->where('status', FinanceChargeInstallment::STATUS_PENDING)
                ->orderBy('finance_charge_id')
                ->orderBy('installment_no')
                ->get()
                ->groupBy('finance_charge_id')
                ->map(fn ($group) => $group->first()); // lowest installment_no per charge

            // Always compute installment-aware total + collect candidate IDs first.
            // Even when admin sets amount_override, we still link installments IF the
            // override happens to equal the auto-computed sum (typical case: UI populated
            // the field with our default and admin clicked Push). Only when override
            // genuinely differs do we skip linkage (treat as ad-hoc DNG; admin takes
            // manual reconciliation responsibility).
            $installmentIds = [];
            $installmentAwareTotal = 0.0;

            foreach ($charges as $charge) {
                $nextInst = $nextInstallmentByCharge[$charge->id] ?? null;

                if ($nextInst !== null) {
                    $installmentAwareTotal += (float) $nextInst->amount;
                    $installmentIds[] = $nextInst->id;
                } else {
                    // Backward-compat: no installment row → use charge balance.
                    $installmentAwareTotal += (float) $charge->balance;
                }
            }

            // Decimal-safe equality check (1 VND tolerance for float rounding).
            $adHocOverride = $amountOverride !== null
                && $amountOverride > 0
                && abs($amountOverride - $installmentAwareTotal) >= 1.0;

            if ($adHocOverride) {
                // Admin pushed a genuinely-different amount → skip installment
                // linkage so the settle webhook won't mark installments paid by
                // mistake; pivots fall back to balance-proportional (admin owns
                // reconciliation).
                Log::warning('CreateBatchDngFromChargesAction: amount override differs from installment plan; skipping installment linkage', [
                    'student_id' => $studentId,
                    'override' => $amountOverride,
                    'auto_sum' => $installmentAwareTotal,
                ]);

                $totalAmount = $amountOverride;
                $installmentIds = [];
            } else {
                // Truth mode: the request amount IS the exact sum of the per-charge
                // amounts we will collect (next installment, or balance for un-split
                // charges). Using the computed sum — not a near-equal override —
                // keeps pivot reconciliation exact (FIN-10b).
                $totalAmount = $installmentAwareTotal;
            }

            if ($totalAmount <= 0) {
                throw new \RuntimeException('Tổng số dư bằng 0, không thể tạo DNG');
            }

            // Cancel existing active DNG for same fee_type + student
            $existingDng = DngPaymentRequest::query()
                ->where('student_id', $studentId)
                ->where('fee_type', $dngFeeType)
                ->awaitingPayment()
                ->lockForUpdate()
                ->first();

            if ($existingDng !== null) {
                // Wrap in nested transaction to allow rollback of cancel independently
                try {
                    $this->cancelDngAction->run($existingDng);
                    $cancelledOld++;
                } catch (\Throwable $e) {
                    Log::warning('Could not cancel old DNG before creating new one', [
                        'dng_id' => $existingDng->id,
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue anyway — DngPaymentService::createAndPush will detect duplicate
                }
            }

            // Resolve DNG campus code
            $campusCode = $this->campusCodeResolver->requireForStudent($student);

            // Build DNG charge data
            $chargeData = [
                'campus_code' => $campusCode,
                'student_code' => $student->student_id,
                'fee_type' => $dngFeeType,
                'type' => $dngFeeType,
                'description' => $description,
                'semester_id' => $semesterId,
                'due_date' => $dueDate,
                'item_id' => $this->buildItemId($student->student_id, $dngFeeType),
                'amount' => $totalAmount,
                'student_name' => $student->full_name,
                'email' => $student->email ?? '',
                'estimate_time' => $estimateTime,
                'student_address' => $student->current_address_line ?? $student->address ?? '',
                'cccd' => $student->national_id ?? null,
                'finance_charge_id' => null, // pivot used instead
                'installment_ids' => $installmentIds, // empty array when legacy / amount override
            ];

            // Push DNG via DngPaymentService (creates record + calls DNG API)
            $dngRequest = $this->dngPaymentService->createAndPush($student, $chargeData);

            // Create pivot rows. In truth mode each pivot mirrors the installment
            // being collected; ad-hoc override falls back to balance-proportional.
            $this->createChargePivots(
                $dngRequest->id,
                $charges,
                $totalAmount,
                $nextInstallmentByCharge,
                linkInstallments: ! $adHocOverride,
            );

            return ['cancelled_old' => $cancelledOld];
        });
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

    /**
     * Load active charges with positive balance for a student.
     * Uses in-memory balance computation (safe for small sets per student).
     *
     * @param  array<int, string>  $chargeTypes
     * @return Collection<int, object{id: int, amount: float, balance: float}>
     */
    private function loadChargesWithBalance(int $studentId, array $chargeTypes, int $semesterId): Collection
    {
        $paidSubquery = DB::table('invoice_lines as il_p')
            ->join('payment_applications as pa', 'pa.invoice_line_id', '=', 'il_p.id')
            ->where('il_p.status', 'active')
            ->selectRaw('il_p.charge_id, COALESCE(SUM(pa.amount), 0) as paid_amount')
            ->groupBy('il_p.charge_id');

        $discountSubquery = DB::table('invoice_lines as il_d')
            ->join('discount_allocations as da', 'da.invoice_line_id', '=', 'il_d.id')
            ->where('il_d.status', 'active')
            ->selectRaw('il_d.charge_id, COALESCE(SUM(da.amount), 0) as discount_amount')
            ->groupBy('il_d.charge_id');

        return DB::table('finance_charges as fc')
            ->leftJoinSub($paidSubquery, 'paid', 'paid.charge_id', '=', 'fc.id')
            ->leftJoinSub($discountSubquery, 'disc', 'disc.charge_id', '=', 'fc.id')
            ->where('fc.student_id', $studentId)
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->where('fc.amount', '>', 0)
            ->whereIn('fc.charge_type', $chargeTypes)
            ->where('fc.semester_id', $semesterId)
            ->selectRaw(
                'fc.id,
                fc.amount,
                GREATEST(0,
                    fc.amount
                    - COALESCE(paid.paid_amount, 0)
                    - COALESCE(disc.discount_amount, 0)
                ) as balance'
            )
            ->get()
            ->filter(fn ($row) => (float) $row->balance > 0);
    }

    /**
     * Create dng_payment_request_charges pivot rows.
     * If amount was overridden, distribute proportionally to charge balances.
     *
     * @param  Collection<int, object{id: int, amount: float, balance: float}>  $charges
     */
    /**
     * Create dng_payment_request_charges allocation rows for one DNG request.
     *
     * Truth mode (linkInstallments): each pivot reflects the installment being
     * collected for that charge (or the charge balance for un-split charges), and
     * carries finance_charge_installment_id. The per-charge amounts already sum to
     * $totalAmount, so the pivot total reconciles to the request exactly and to the
     * linked installment amounts (FIN-10b). A pivot is NOT a balance-proportional
     * slice of the student's debt.
     *
     * Ad-hoc override mode: no installment plan to honour — distribute $totalAmount
     * proportionally to balance in integer cents with the remainder on the last
     * pivot so Σ pivot == request amount exactly (FIN-10), and leave the installment
     * link null.
     *
     * @param  Collection<int, object{id: int, amount: float, balance: float}>  $charges
     * @param  Collection<int|string, FinanceChargeInstallment>  $nextInstallmentByCharge
     */
    private function createChargePivots(
        int $dngRequestId,
        Collection $charges,
        float $totalAmount,
        Collection $nextInstallmentByCharge,
        bool $linkInstallments,
    ): void {
        if ($linkInstallments) {
            foreach ($charges as $charge) {
                $nextInst = $nextInstallmentByCharge[$charge->id] ?? null;
                $amount = $nextInst !== null ? (float) $nextInst->amount : (float) $charge->balance;

                DngPaymentRequestCharge::create([
                    'dng_payment_request_id' => $dngRequestId,
                    'finance_charge_id' => $charge->id,
                    'finance_charge_installment_id' => $nextInst?->id,
                    'amount' => number_format($this->toCents($amount) / 100, 2, '.', ''),
                ]);
            }

            return;
        }

        $chargesList = $charges->values();
        $count = $chargesList->count();

        $totalCents = $this->toCents($totalAmount);
        $totalBalanceCents = $chargesList
            ->sum(fn ($charge) => $this->toCents((float) $charge->balance));

        $allocatedCents = 0;

        foreach ($chargesList as $index => $charge) {
            $isLast = $index === $count - 1;
            $chargeBalanceCents = $this->toCents((float) $charge->balance);

            if ($isLast) {
                $pivotCents = $totalCents - $allocatedCents;
            } elseif ($totalBalanceCents > 0) {
                $pivotCents = (int) floor($totalCents * ($chargeBalanceCents / $totalBalanceCents));
            } else {
                $pivotCents = $chargeBalanceCents;
            }

            $allocatedCents += $pivotCents;

            DngPaymentRequestCharge::create([
                'dng_payment_request_id' => $dngRequestId,
                'finance_charge_id' => $charge->id,
                'finance_charge_installment_id' => null,
                'amount' => number_format($pivotCents / 100, 2, '.', ''),
            ]);
        }
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Build a unique item_id for DNG (student_code + fee_type + timestamp).
     *
     * FIN-33: a bare YmdHis suffix collided when two pushes for the same student
     * + fee_type happened within the same second, which breaks webhook resolution
     * (item_id is a reconciliation key). Append a random suffix so same-second
     * pushes stay distinct. Stays well within the varchar(100) column.
     */
    private function buildItemId(string $studentCode, string $feeType): string
    {
        $random = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        return $studentCode.'_'.strtolower($feeType).'_'.now()->format('YmdHis').$random;
    }
}
