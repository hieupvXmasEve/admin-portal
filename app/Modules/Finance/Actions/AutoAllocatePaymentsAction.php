<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AutoAllocatePaymentsAction
{
    public const DEFAULT_PRIORITY_ORDER = [
        FinanceCharge::TYPE_TUITION_TERM,
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
        FinanceCharge::TYPE_BHYT,
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        FinanceCharge::TYPE_RETAKE_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADJUSTMENT,
    ];

    public function __construct(
        protected SettlementService $settlementService,
        private readonly StudentReferenceReader $studentReferences,
        private readonly CampusPermissionReader $permissions,
    ) {}

    /**
     * Auto allocate payments to charges based on priority.
     *
     * @param  array  $priorityOrder  Array of charge types in order of priority.
     * @param  int  $userId  The ID of the user performing the allocation.
     * @return array Result summary.
     */
    public function run(array $priorityOrder, ?int $userId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $studentsWithPayments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->when($campusId, function ($query, $campusId) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
            })
            ->get()
            ->filter(fn (Payment $payment) => $payment->unapplied_amount > 0)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();

        return $this->runForStudents($studentsWithPayments, $priorityOrder, $userId);
    }

    /**
     * Auto allocate payments for a targeted set of students.
     *
     * @param  array<int>  $studentIds
     * @param  array<int, string>  $priorityOrder
     * @return array{students_processed:int,allocations_created:int,total_allocated_amount:float,invoices_updated:int}
     */
    public function runForStudents(array $studentIds, array $priorityOrder, ?int $userId): array
    {
        $stats = [
            'students_processed' => 0,
            'allocations_created' => 0,
            'total_allocated_amount' => 0,
            'invoices_updated' => 0,
        ];
        $studentsWithAllocations = [];

        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === []) {
            return $stats;
        }

        $this->assertStudentsInCampusScope($studentIds, $userId);

        DB::transaction(function () use ($studentIds, $priorityOrder, $userId, &$stats, &$studentsWithAllocations) {

            // 0. Handle 0-amount invoices (mark as paid)
            $zeroAmountInvoices = StudentInvoice::query()
                ->with(['invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
                ->where('status', '!=', 'paid')
                ->whereIn('student_id', $studentIds)
                ->get()
                ->filter(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['total_amount'] <= 0);

            foreach ($zeroAmountInvoices as $invoice) {
                $billingAccountId = (int) app(BillingAccountProvisioner::class)->forStudent((int) $invoice->student_id)->id;
                app(SettlementMutationGuard::class)->handle($billingAccountId, function () use ($invoice): void {
                    $invoice->update(['status' => 'paid']);
                });
                $stats['invoices_updated']++;
            }

            foreach ($studentIds as $studentId) {
                // FIN-11/DB-09: lock this student's completed payments inside the
                // transaction. A concurrent allocator (DNG webhook bridge or a
                // second batch run) blocks on these locks and then re-reads the
                // reduced unapplied amount, so one payment can never be applied
                // beyond its balance.
                $payments = Payment::where('student_id', $studentId)
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->orderBy('paid_at', 'asc') // Use oldest payments first
                    ->lockForUpdate()
                    ->get();

                $payments = $payments->filter(fn (Payment $payment) => $payment->unapplied_amount > 0)->values();

                if ($payments->isEmpty()) {
                    continue;
                }

                $lines = $this->settlementService->getOutstandingLinesForStudent($studentId, $priorityOrder);

                if ($lines->isEmpty()) {
                    continue;
                }

                $studentHasAllocations = false;

                foreach ($payments as $payment) {
                    $available = $payment->unapplied_amount;
                    if ($available <= 0) {
                        continue;
                    }

                    foreach ($lines as $line) {
                        if ($available <= 0) {
                            break;
                        }

                        // FIN-11/DB-09: lock the line and re-read its outstanding
                        // under lock. The payment lock only serializes the same
                        // payment; this prevents a different payment (DNG bridge /
                        // manual) from racing onto the same line and overpaying it.
                        $lockedLine = InvoiceLine::query()->lockForUpdate()->find($line->id);
                        if (! $lockedLine || $lockedLine->status !== 'active') {
                            continue;
                        }

                        $isHeld = DngPaymentRequestReservationTarget::query()
                            ->where('invoice_line_id', $lockedLine->id)
                            ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())
                            ->lockForUpdate()
                            ->exists();
                        if ($isHeld) {
                            continue;
                        }

                        $outstanding = $this->settlementService->getLineOutstandingAmount($lockedLine);
                        if ($outstanding <= 0) {
                            continue;
                        }

                        $allocateAmount = min($available, $outstanding);
                        if ($allocateAmount <= 0) {
                            continue;
                        }

                        $this->settlementService->createPaymentApplication(
                            $payment,
                            $lockedLine,
                            $allocateAmount,
                            'application',
                            $userId,
                            self::class,
                            null,
                        );

                        $available -= $allocateAmount;

                        $stats['allocations_created']++;
                        $stats['total_allocated_amount'] += $allocateAmount;
                        $studentHasAllocations = true;
                    }
                }

                if ($studentHasAllocations) {
                    $stats['students_processed']++;
                    $studentsWithAllocations[] = $studentId;
                }
            }
        });

        foreach (array_values(array_unique($studentsWithAllocations)) as $studentId) {
            app(RetakeRegistrationPaymentSyncer::class)->runForStudent($studentId);
            app(ExamResitAttemptPaymentSyncer::class)->runForStudent($studentId);
        }

        return $stats;
    }

    /**
     * @param  list<int>  $studentIds
     */
    private function assertStudentsInCampusScope(array $studentIds, ?int $userId): void
    {
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $campusId = $campusId !== null ? (int) $campusId : null;

        if ($userId !== null && $this->canViewAllCampuses($userId, $campusId)) {
            return;
        }

        if ($campusId === null) {
            return;
        }

        $allowed = array_flip($this->studentReferences->idsForCampus($campusId));

        foreach ($studentIds as $studentId) {
            if (! isset($allowed[$studentId])) {
                throw new AuthorizationException('Students outside campus scope.');
            }
        }
    }

    private function canViewAllCampuses(int $userId, ?int $campusId): bool
    {
        return in_array(
            'view_finance_all_campus',
            $this->permissions->permissionCodesForUserId($userId, $campusId),
            true,
        );
    }

    /**
     * FIN-01: zero-amount detection reads the one canonical ledger calculation
     * in SettlementService. This adapter only remaps the canonical keys; it
     * must not re-derive or mix stale cache columns.
     */
    private function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    {
        $snapshot = $this->settlementService->deriveInvoiceSnapshot($invoice);

        return [
            'total_amount' => $snapshot['net'],
            'paid_amount' => $snapshot['paid'],
        ];
    }
}
