<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

class PreviewAutoAllocateQuery
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    /**
     * Preview auto allocation results without committing to database.
     *
     * @param  array  $priorityOrder  Array of charge types in order of priority.
     * @return array Preview data including affected payments, charges, and projected allocations.
     */
    public function handle(array $priorityOrder, ?array $studentIds = null): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

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

        $studentsWithPayments = $studentIds !== null
            ? collect($studentIds)->map(fn ($studentId) => (int) $studentId)->unique()->values()
            : Payment::query()
                ->where('status', Payment::STATUS_COMPLETED)
                ->when($campusId, function ($query, $campusId) {
                    $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
                })
                ->get()
                ->filter(fn (Payment $payment) => $payment->unapplied_amount > 0)
                ->pluck('student_id')
                ->unique()
                ->values();

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

        $zeroAmountInvoicesCount = StudentInvoice::query()
            ->with(['invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->where('status', '!=', 'paid')
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentsWithPayments->all()))
            ->when($campusId, function ($query, $campusId) {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
            })
            ->get()
            ->filter(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['total_amount'] <= 0)
            ->count();
        $preview['summary']['invoices_to_update'] = $zeroAmountInvoicesCount;

        return $preview;
    }

    protected function previewStudentAllocations(int $studentId, array $priorityOrder): array
    {
        $payments = Payment::with('student:id,student_id,full_name')
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderBy('paid_at', 'asc')
            ->get();

        $payments = $payments->filter(fn (Payment $payment) => $payment->unapplied_amount > 0)->values();

        if ($payments->isEmpty()) {
            return ['allocations' => []];
        }

        $hasUnpaidInvoices = StudentInvoice::where('student_id', $studentId)
            ->where('status', '!=', 'paid')
            ->exists();

        if (! $hasUnpaidInvoices) {
            return ['allocations' => []];
        }

        $lines = $this->settlementService->getOutstandingLinesForStudent($studentId, $priorityOrder);

        if ($lines->isEmpty()) {
            return ['allocations' => []];
        }

        $lineOutstanding = [];
        foreach ($lines as $line) {
            $lineOutstanding[$line->id] = $this->settlementService->getLineOutstandingAmount($line);
        }

        $allocations = [];
        $paymentUsed = [];
        $totalToAllocate = 0;

        foreach ($payments as $payment) {
            $available = $payment->unapplied_amount;
            if ($available <= 0) {
                continue;
            }

            $paymentAllocations = [];

            foreach ($lines as $line) {
                if ($available <= 0) {
                    break;
                }

                $outstanding = $lineOutstanding[$line->id] ?? 0;
                if ($outstanding <= 0) {
                    continue;
                }

                $allocateAmount = min($available, $outstanding);
                if ($allocateAmount <= 0) {
                    continue;
                }

                $paymentAllocations[] = [
                    'invoice_line_id' => $line->id,
                    'charge_id' => $line->charge?->id,
                    'charge_type' => $line->charge?->charge_type,
                    'charge_description' => $line->charge?->description ?? $line->description_snapshot,
                    'amount' => $allocateAmount,
                ];

                $available -= $allocateAmount;
                $totalToAllocate += $allocateAmount;
                $lineOutstanding[$line->id] -= $allocateAmount;
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

    private function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    {
        $lineSubtotal = (float) $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line) && (float) $line->amount_snapshot > 0)
            ->sum('amount_snapshot');

        $discountTotal = (float) $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
            ->sum(fn (InvoiceLine $line) => max(0, (float) $line->discountAllocations->sum('amount')));

        $paidAmount = (float) $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
            ->sum(fn (InvoiceLine $line) => max(0, (float) $line->paymentApplications->sum('amount')));

        $storedTotal = array_key_exists('total_amount', $invoice->getAttributes()) ? (float) $invoice->getAttributes()['total_amount'] : null;
        $storedPaid = array_key_exists('paid_amount', $invoice->getAttributes()) ? (float) $invoice->getAttributes()['paid_amount'] : null;
        $derivedTotal = max(0, $lineSubtotal - $discountTotal);

        return [
            'total_amount' => $storedTotal !== null ? max($storedTotal, $derivedTotal) : $derivedTotal,
            'paid_amount' => $storedPaid !== null ? max($storedPaid, min($paidAmount, $derivedTotal)) : min($paidAmount, $derivedTotal),
        ];
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === \App\Models\FinanceCharge::STATUS_ACTIVE;
    }
}
