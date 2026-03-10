<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\StudentInvoice;

class PreviewAutoAllocateQuery
{
    /**
     * Preview auto allocation results without committing to database.
     *
     * @param  array  $priorityOrder  Array of charge types in order of priority.
     * @return array Preview data including affected payments, charges, and projected allocations.
     */
    public function handle(array $priorityOrder): array
    {
        $preview = [
            'students' => [],
            'summary' => [
                'total_students' => 0,
                'total_payments_affected' => 0,
                'total_allocations' => 0,
                'total_amount' => 0,
                'invoices_to_update' => 0,
            ],
        ];

        $studentsWithPayments = Payment::query()
            ->select('student_id')
            ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id)) > 0')
            ->distinct()
            ->pluck('student_id');

        foreach ($studentsWithPayments as $studentId) {
            $studentPreview = $this->previewStudentAllocations($studentId, $priorityOrder);

            if (! empty($studentPreview['allocations'])) {
                $preview['students'][] = $studentPreview;
                $preview['summary']['total_students']++;
                $preview['summary']['total_payments_affected'] += count($studentPreview['payments']);
                $preview['summary']['total_allocations'] += count($studentPreview['allocations']);
                $preview['summary']['total_amount'] += $studentPreview['total_to_allocate'];
            }
        }

        $zeroAmountInvoicesCount = StudentInvoice::where('status', '!=', 'paid')
            ->filterByStatus('zero_amount')
            ->count();
        $preview['summary']['invoices_to_update'] = $zeroAmountInvoicesCount;

        return $preview;
    }

    protected function previewStudentAllocations(int $studentId, array $priorityOrder): array
    {
        $payments = Payment::with('student:id,student_id,full_name')
            ->where('student_id', $studentId)
            ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id)) > 0')
            ->orderBy('paid_at', 'asc')
            ->get();

        if ($payments->isEmpty()) {
            return ['allocations' => []];
        }

        // Get unpaid invoice IDs for this student
        $unpaidInvoiceIds = StudentInvoice::where('student_id', $studentId)
            ->where('status', '!=', 'paid')
            ->pluck('id')
            ->toArray();

        if (empty($unpaidInvoiceIds)) {
            return ['allocations' => []];
        }

        // Get charge IDs linked to unpaid invoices
        $chargeIdsInUnpaidInvoices = InvoiceLine::whereIn('invoice_id', $unpaidInvoiceIds)
            ->pluck('charge_id')
            ->toArray();

        if (empty($chargeIdsInUnpaidInvoices)) {
            return ['allocations' => []];
        }

        // Fetch charges that belong to unpaid invoices only
        $charges = FinanceCharge::where('student_id', $studentId)
            ->where('amount', '>', 0)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->whereIn('id', $chargeIdsInUnpaidInvoices)
            ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE payment_allocations.charge_id = finance_charges.id)) > ?', [0])
            ->get();

        if ($charges->isEmpty()) {
            return ['allocations' => []];
        }

        $chargeInvoiceMap = InvoiceLine::query()
            ->whereIn('charge_id', $charges->pluck('id'))
            ->pluck('invoice_id', 'charge_id')
            ->toArray();

        $charges = $charges->sortBy(function ($charge) use ($priorityOrder) {
            $index = array_search($charge->charge_type, $priorityOrder);

            return $index === false ? 999 : $index;
        });

        $allocations = [];
        $paymentUsed = [];
        $totalToAllocate = 0;

        // Cache charge outstanding balances
        $chargeOutstanding = [];
        foreach ($charges as $charge) {
            $chargeOutstanding[$charge->id] = (float) $charge->balance;
        }

        foreach ($payments as $payment) {
            $available = $payment->unapplied_amount;
            if ($available <= 0) {
                continue;
            }

            $paymentAllocations = [];

            foreach ($charges as $charge) {
                if ($available <= 0) {
                    break;
                }

                $outstanding = $chargeOutstanding[$charge->id] ?? 0;
                if ($outstanding <= 0) {
                    continue;
                }

                $allocateAmount = min($available, $outstanding);
                if ($allocateAmount <= 0) {
                    continue;
                }

                $paymentAllocations[] = [
                    'charge_id' => $charge->id,
                    'charge_type' => $charge->charge_type,
                    'charge_description' => $charge->description,
                    'amount' => $allocateAmount,
                ];

                $available -= $allocateAmount;
                $totalToAllocate += $allocateAmount;
                $chargeOutstanding[$charge->id] -= $allocateAmount;
            }

            if (! empty($paymentAllocations)) {
                $allocations[] = [
                    'payment_id' => $payment->id,
                    'payment_amount' => $payment->amount,
                    'payment_unapplied' => $payment->unapplied_amount,
                    'payment_date' => $payment->paid_at?->toDateString(),
                    'payment_external_ref' => $payment->external_ref,
                    'to_allocate' => $paymentAllocations,
                ];
                $paymentUsed[$payment->id] = true;
            }
        }

        $student = $payments->first()?->student;

        return [
            'student_id' => $studentId,
            'student_code' => $student?->student_id,
            'student_name' => $student?->full_name,
            'payments' => array_keys($paymentUsed),
            'allocations' => $allocations,
            'total_to_allocate' => $totalToAllocate,
        ];
    }
}
