<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Support\Facades\DB;

class AutoAllocatePaymentsAction
{
    public const DEFAULT_PRIORITY_ORDER = [
        FinanceCharge::TYPE_TUITION_TERM,
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
        FinanceCharge::TYPE_RETAKE_FEE,
        FinanceCharge::TYPE_MANUAL_FEE,
    ];

    public function __construct(
        protected SettlementService $settlementService,
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

        DB::transaction(function () use ($studentIds, $priorityOrder, $userId, &$stats, &$studentsWithAllocations) {

            // 0. Handle 0-amount invoices (mark as paid)
            $zeroAmountInvoices = StudentInvoice::query()
                ->with(['invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
                ->where('status', '!=', 'paid')
                ->whereIn('student_id', $studentIds)
                ->get()
                ->filter(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['total_amount'] <= 0);

            foreach ($zeroAmountInvoices as $invoice) {
                $invoice->update(['status' => 'paid']);
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
        }

        return $stats;
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
