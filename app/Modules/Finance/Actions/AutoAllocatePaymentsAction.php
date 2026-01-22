<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\DB;

class AutoAllocatePaymentsAction
{
    public function __construct(
        protected InvoiceGenerationService $invoiceService
    ) {}

    /**
     * Auto allocate payments to charges based on priority.
     *
     * @param  array  $priorityOrder  Array of charge types in order of priority.
     * @param  int  $userId  The ID of the user performing the allocation.
     * @return array Result summary.
     */
    public function run(array $priorityOrder, int $userId): array
    {
        $stats = [
            'students_processed' => 0,
            'allocations_created' => 0,
            'total_allocated_amount' => 0,
            'invoices_updated' => 0,
        ];

        $allocatedChargeIds = [];

        DB::transaction(function () use ($priorityOrder, $userId, &$stats, &$allocatedChargeIds) {
            // 1. Find all students with unallocated payments (amount > 0)
            // We verify 'amount > 0' and check unapplied amount effectively via code logic below
            // But to optimize, we can filter students who have payments with unapplied amount.
            // However, calculation of 'unapplied_amount' is an accessor.
            // For batch performance, let's fetch students who have payments with status != 'allocating' or similar if applicable.
            // For now, simpler approach: Get IDs of students with payments that are NOT fully allocated.

            // Note: The Payment model has 'is_fully_allocated' accessor.
            // In SQL we can check: (amount - (SELECT SUM(allocated_amount) FROM payment_allocations ...)) > 0

            $studentsWithPayments = Payment::query()
                ->select('student_id')
                ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id)) > 0')
                ->distinct()
                ->pluck('student_id');

            foreach ($studentsWithPayments as $studentId) {
                // Fetch student's unallocated payments
                $payments = Payment::where('student_id', $studentId)
                    ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id)) > 0')
                    ->orderBy('paid_at', 'asc') // Use oldest payments first
                    ->get();

                if ($payments->isEmpty()) {
                    continue;
                }

                // Fetch student's outstanding charges (positive amount, not void)
                // Filter charges where (amount - paid) > 0.
                $charges = FinanceCharge::where('student_id', $studentId)
                    ->where('amount', '>', 0)
                    ->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.charge_id = finance_charges.id)) > ?', [0])
                    ->get();

                if ($charges->isEmpty()) {
                    continue;
                }

                $chargeInvoiceMap = InvoiceLine::query()
                    ->whereIn('charge_id', $charges->pluck('id'))
                    ->pluck('invoice_id', 'charge_id')
                    ->toArray();

                $invoiceRemaining = [];
                $invoiceIds = array_unique(array_values($chargeInvoiceMap));
                if (! empty($invoiceIds)) {
                    $invoiceRemaining = StudentInvoice::query()
                        ->whereIn('id', $invoiceIds)
                        ->get()
                        ->mapWithKeys(fn($invoice) => [$invoice->id => $invoice->outstanding_balance])
                        ->toArray();
                }

                // Sort charges by priority
                $charges = $charges->sortBy(function ($charge) use ($priorityOrder) {
                    $index = array_search($charge->charge_type, $priorityOrder);

                    return $index === false ? 999 : $index;
                });

                $studentHasAllocations = false;

                foreach ($payments as $payment) {
                    $available = $payment->unapplied_amount;
                    if ($available <= 0) {
                        continue;
                    }

                    foreach ($charges as $charge) {
                        if ($available <= 0) {
                            break;
                        }

                        // Calculate effective outstanding balance considering in-memory allocations
                        $initialBalance = $charge->balance; // Accessor value (from DB at fetch time)
                        $allocatedInLoop = $charge->temp_paid ?? 0;
                        $outstanding = $initialBalance - $allocatedInLoop;

                        if ($outstanding <= 0) {
                            continue;
                        }

                        $invoiceId = $chargeInvoiceMap[$charge->id] ?? null;
                        $invoiceAvailable = null;
                        if ($invoiceId) {
                            $invoiceAvailable = $invoiceRemaining[$invoiceId] ?? 0;
                            if ($invoiceAvailable <= 0) {
                                continue;
                            }
                        }

                        $allocateAmount = min(
                            $available,
                            $outstanding,
                            $invoiceAvailable ?? $outstanding
                        );
                        if ($allocateAmount <= 0) {
                            continue;
                        }

                        // Create Allocation
                        PaymentAllocation::create([
                            'payment_id' => $payment->id,
                            'charge_id' => $charge->id,
                            'allocated_amount' => $allocateAmount,
                            'allocated_at' => now(),
                            'allocated_by_user_id' => $userId,
                        ]);

                        // Track charge ID for invoice status update
                        $allocatedChargeIds[] = $charge->id;

                        // Update local variables
                        $available -= $allocateAmount;

                        if ($invoiceId) {
                            $invoiceRemaining[$invoiceId] -= $allocateAmount;
                        }

                        // Track allocations for this charge within the loop
                        if (! isset($charge->temp_paid)) {
                            $charge->temp_paid = 0;
                        }
                        $charge->temp_paid += $allocateAmount;

                        $stats['allocations_created']++;
                        $stats['total_allocated_amount'] += $allocateAmount;
                        $studentHasAllocations = true;
                    }
                }

                if ($studentHasAllocations) {
                    $stats['students_processed']++;
                }
            }

            // Update invoice statuses for all affected invoices
            if (! empty($allocatedChargeIds)) {
                $this->updateInvoiceStatuses($allocatedChargeIds, $stats);
            }
        });

        return $stats;
    }

    /**
     * Update invoice statuses for invoices containing the allocated charges.
     *
     * @param  array  $chargeIds  Array of charge IDs that were allocated.
     * @param  array  &$stats  Stats array to update with invoice count.
     */
    protected function updateInvoiceStatuses(array $chargeIds, array &$stats): void
    {
        // Get unique invoice IDs from invoice_lines that contain these charges
        $invoiceIds = InvoiceLine::whereIn('charge_id', array_unique($chargeIds))
            ->distinct()
            ->pluck('invoice_id')
            ->toArray();

        if (empty($invoiceIds)) {
            return;
        }

        // Update each invoice's status
        $invoices = StudentInvoice::whereIn('id', $invoiceIds)->get();

        foreach ($invoices as $invoice) {
            $this->invoiceService->updateInvoiceStatus($invoice);
            $stats['invoices_updated']++;
        }
    }
}
