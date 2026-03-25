<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListSettlementWorklistQuery
{
    private const SORTABLE = [
        'student_code' => 'student_code',
        'student_name' => 'student_name',
        'invoice_count' => 'invoice_count',
        'active_due' => 'active_due',
        'unapplied_balance' => 'unapplied_balance',
        'net_amount_to_collect' => 'net_amount_to_collect',
    ];

    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'readiness' => 'nullable|string|in:all,ready,no_cash',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $readiness = $validated['readiness'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = self::SORTABLE[$validated['sort'] ?? 'active_due'] ?? self::SORTABLE['active_due'];
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $invoiceQuery = StudentInvoice::query()
            ->with(['student', 'semester', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderBy('due_date');

        $campusId = app()->bound('campus') ? app('campus')->id : null;

        if ($campusId) {
            $invoiceQuery->forCampus($campusId);
        }

        if ($search !== '') {
            $invoiceQuery->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('student_id', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $invoiceQuery->get();

        $invoices = $invoices
            ->filter(function (StudentInvoice $invoice) {
                $snapshot = $this->deriveInvoiceSnapshot($invoice);

                return $snapshot['total_amount'] > 0 && $snapshot['paid_amount'] < $snapshot['total_amount'];
            })
            ->values();

        $studentIds = $invoices->pluck('student_id')->unique()->values();

        $paymentsByStudent = Payment::query()
            ->with('applications')
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderBy('paid_at')
            ->get()
            ->groupBy('student_id');

        $students = $invoices
            ->groupBy('student_id')
            ->map(function (Collection $studentInvoices, int $studentId) use ($paymentsByStudent) {
                $student = $studentInvoices->first()?->student;
                $payments = $paymentsByStudent->get($studentId, collect());
                $totalPayments = (float) $payments->sum('amount');
                $allocatedAmount = (float) $payments->sum(fn (Payment $payment) => max(0, (float) $payment->applications->sum('amount')));
                $unappliedBalance = max(0, $totalPayments - $allocatedAmount);
                $activeDue = (float) $studentInvoices->sum(function (StudentInvoice $invoice) {
                    $snapshot = $this->deriveInvoiceSnapshot($invoice);

                    return max(0, $snapshot['total_amount'] - $snapshot['paid_amount']);
                });
                $overdueCount = $studentInvoices->filter(fn (StudentInvoice $invoice) => $invoice->due_date && $invoice->due_date->isPast())->count();

                return [
                    'student_id' => $studentId,
                    'student_code' => $student?->student_id,
                    'student_name' => $student?->full_name,
                    'invoice_count' => $studentInvoices->count(),
                    'overdue_invoice_count' => $overdueCount,
                    'active_due' => $activeDue,
                    'unapplied_balance' => $unappliedBalance,
                    'allocated_amount' => $allocatedAmount,
                    'total_payments' => $totalPayments,
                    'net_amount_to_collect' => max(0, $activeDue - $unappliedBalance),
                    'actionable' => $activeDue > 0 && $unappliedBalance > 0,
                    'invoices' => $studentInvoices->map(function (StudentInvoice $invoice) {
                        $snapshot = $this->deriveInvoiceSnapshot($invoice);

                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'semester_name' => $invoice->semester?->name,
                            'status' => $invoice->status,
                            'due_date' => $invoice->due_date?->toDateString(),
                            'total_amount' => $snapshot['total_amount'],
                            'paid_amount' => $snapshot['paid_amount'],
                            'remaining_amount' => max(0, $snapshot['total_amount'] - $snapshot['paid_amount']),
                        ];
                    })->values(),
                ];
            })
            ->filter(function (array $student) use ($readiness) {
                return match ($readiness) {
                    'ready' => $student['actionable'],
                    'no_cash' => ! $student['actionable'],
                    default => true,
                };
            })
            ->values();

        $students = $students->sort(function (array $left, array $right) use ($sort, $direction) {
            $leftRank = [
                $left['actionable'] ? 1 : 0,
                $left['overdue_invoice_count'],
                $left[$sort],
                $left['student_code'],
            ];
            $rightRank = [
                $right['actionable'] ? 1 : 0,
                $right['overdue_invoice_count'],
                $right[$sort],
                $right['student_code'],
            ];

            return $direction === 'asc'
                ? ($leftRank <=> $rightRank)
                : ($rightRank <=> $leftRank);
        })->values();

        $summary = [
            'students_with_unpaid_invoices' => $students->count(),
            'ready_students' => $students->where('actionable', true)->count(),
            'total_active_due' => (float) $students->sum('active_due'),
            'total_unapplied_balance' => (float) $students->sum('unapplied_balance'),
        ];

        $students = $this->paginateCollection($students, $perPage, $page, $request->url(), $request->query());

        return [
            'students' => $students,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'readiness' => $readiness,
                'per_page' => $perPage,
                'page' => $page,
                'sort' => array_search($sort, self::SORTABLE, true) ?: 'active_due',
                'direction' => $direction,
            ],
        ];
    }

    private function paginateCollection(Collection $items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
    {
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => $path,
            'query' => $query,
        ]))->withQueryString();
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
            'paid_amount' => $storedPaid !== null ? $storedPaid : min($paidAmount, $derivedTotal),
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
