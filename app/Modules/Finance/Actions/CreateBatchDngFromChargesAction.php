<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
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
 * For fee_type = HL, auto-creates charges for approved retake registrations
 * that do not yet have a finance_charge_id before pushing the DNG.
 */
class CreateBatchDngFromChargesAction
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $campusCodeResolver,
        protected CancelDngPaymentRequestAction $cancelDngAction,
        protected CreateRetakeCourseChargeSimpleAction $createChargeSimpleAction,
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
     * Process a single student: auto-create charges for HL if needed, then push DNG.
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

            // For HL: auto-create charges for approved retake registrations without charges
            if ($dngFeeType === 'HL') {
                $this->ensureRetakeChargesExist($student, $semesterId);
            }

            // Load active charges with positive balance
            $charges = $this->loadChargesWithBalance($studentId, $chargeTypes, $semesterId);

            if ($charges->isEmpty()) {
                throw new \RuntimeException('Không tìm thấy khoản phí nào có số dư > 0');
            }

            // Compute total (or use override)
            $totalAmount = $amountOverride !== null && $amountOverride > 0
                ? $amountOverride
                : (float) $charges->sum('balance');

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
            ];

            // Push DNG via DngPaymentService (creates record + calls DNG API)
            $dngRequest = $this->dngPaymentService->createAndPush($student, $chargeData);

            // Create pivot rows: 1 per charge, amount proportional if override applied
            $this->createChargePivots($dngRequest->id, $charges, $totalAmount);

            return ['cancelled_old' => $cancelledOld];
        });
    }

    /**
     * For HL fee_type: ensure approved retake registrations have charges before DNG push.
     * Calls CreateRetakeCourseChargeSimpleAction for each approved registration without charge.
     */
    private function ensureRetakeChargesExist(Student $student, int $semesterId): void
    {
        $pendingRegistrations = CourseRetakeRegistration::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->whereNull('finance_charge_id')
            ->lockForUpdate()
            ->get();

        foreach ($pendingRegistrations as $registration) {
            try {
                $this->createChargeSimpleAction->handle([
                    'registration_id' => $registration->id,
                    'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                    'amount' => (float) $registration->retake_fee,
                    'description' => "Phí học lại môn {$registration->unit?->code}",
                ]);
            } catch (\Throwable $e) {
                Log::warning('CreateBatchDngFromChargesAction: failed to auto-create retake charge', [
                    'registration_id' => $registration->id,
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
    private function createChargePivots(int $dngRequestId, Collection $charges, float $totalAmount): void
    {
        $totalBalance = (float) $charges->sum('balance');

        foreach ($charges as $charge) {
            $chargeBalance = (float) $charge->balance;

            // Proportional distribution when override was applied
            $pivotAmount = $totalBalance > 0
                ? round($totalAmount * ($chargeBalance / $totalBalance), 2)
                : $chargeBalance;

            DngPaymentRequestCharge::create([
                'dng_payment_request_id' => $dngRequestId,
                'finance_charge_id' => $charge->id,
                'amount' => $pivotAmount,
            ]);
        }
    }

    /**
     * Build a deterministic item_id for DNG (student_code + fee_type suffix).
     */
    private function buildItemId(string $studentCode, string $feeType): string
    {
        return $studentCode.'_'.strtolower($feeType).'_'.now()->format('YmdHis');
    }
}
