<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
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
            'dng_status' => 'nullable|string|in:all,has_dng,no_dng',
            'student_status' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $readiness = $validated['readiness'] ?? 'all';
        $dngStatus = $validated['dng_status'] ?? 'all';
        $studentStatus = $validated['student_status'] ?? '';
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $sort = self::SORTABLE[$validated['sort'] ?? 'active_due'] ?? self::SORTABLE['active_due'];
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $invoiceQuery = StudentInvoice::query()
            ->with(['student', 'semester', 'invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
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

        if ($studentStatus !== '') {
            $invoiceQuery->whereHas('student', function ($q) use ($studentStatus) {
                $q->where('status', $studentStatus);
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

        $latestDngRequestsByStudent = DngPaymentRequest::query()
            ->whereIn('student_id', $studentIds)
            ->latest('created_at')
            ->get([
                'id',
                'student_id',
                'status',
                'item_id',
                'description',
                'created_at',
            ])
            ->groupBy('student_id')
            ->map(fn (Collection $requests) => $requests->first());

        // Batch query: latest awaitingPayment DNG per (student_id, fee_type) — 1 query, no N+1
        $activeDngByStudentAndFeeType = DngPaymentRequest::query()
            ->whereIn('student_id', $studentIds)
            ->awaitingPayment()
            ->latest('created_at')
            ->get(['id', 'student_id', 'fee_type', 'amount', 'status'])
            ->groupBy('student_id')
            ->map(fn (Collection $requests) => $requests->groupBy('fee_type')
                ->map(fn (Collection $byType) => $byType->first()));

        $students = $invoices
            ->groupBy('student_id')
            ->map(function (Collection $studentInvoices, int $studentId) use ($paymentsByStudent, $latestDngRequestsByStudent, $activeDngByStudentAndFeeType) {
                $student = $studentInvoices->first()?->student;
                $payments = $paymentsByStudent->get($studentId, collect());
                $latestDngRequest = $latestDngRequestsByStudent->get($studentId);
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
                    'student_status' => $student?->status,
                    'invoice_count' => $studentInvoices->count(),
                    'overdue_invoice_count' => $overdueCount,
                    'active_due' => $activeDue,
                    'unapplied_balance' => $unappliedBalance,
                    'allocated_amount' => $allocatedAmount,
                    'total_payments' => $totalPayments,
                    'net_amount_to_collect' => max(0, $activeDue - $unappliedBalance),
                    'actionable' => $activeDue > 0 && $unappliedBalance > 0,
                    'latest_dng_request' => $latestDngRequest ? [
                        'id' => $latestDngRequest->id,
                        'status' => $latestDngRequest->status,
                        'item_id' => $latestDngRequest->item_id,
                        'description' => $latestDngRequest->description,
                        'created_at' => $latestDngRequest->created_at?->toIso8601String(),
                    ] : null,
                    'fee_type_breakdown' => $this->computeFeeTypeBreakdown($studentInvoices, $activeDngByStudentAndFeeType->get($studentId, collect())),
                    'invoices' => $studentInvoices->map(function (StudentInvoice $invoice) {
                        $snapshot = $this->deriveInvoiceSnapshot($invoice);

                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'semester_id' => $invoice->semester_id,
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
            ->filter(function (array $student) use ($readiness, $dngStatus) {
                $passReadiness = match ($readiness) {
                    'ready' => $student['actionable'],
                    'no_cash' => ! $student['actionable'],
                    default => true,
                };

                $passDng = match ($dngStatus) {
                    'has_dng' => $student['latest_dng_request'] !== null,
                    'no_dng' => $student['latest_dng_request'] === null,
                    default => true,
                };

                return $passReadiness && $passDng;
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
                'dng_status' => $dngStatus,
                'student_status' => $studentStatus,
                'per_page' => $perPage,
                'page' => $page,
                'sort' => array_search($sort, self::SORTABLE, true) ?: 'active_due',
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentInvoice>  $studentInvoices
     * @param  Collection<string, DngPaymentRequest>  $activeDngByFeeType  fee_type => latest awaitingPayment DNG
     * @return array<int, array{fee_type: string, label: string, gross: float, discount: float, net_remaining: float, semester_id: int|null, active_dng: array{id: int, amount: float, status: string}|null}>
     */
    private function computeFeeTypeBreakdown(Collection $studentInvoices, Collection $activeDngByFeeType): array
    {
        $feeTypeLabelMap = collect(DngFeeTypeOptions::all())->pluck('label', 'value')->all();

        // groups: fee_type => [gross, discount, paid, semester_id]
        $groups = [];

        foreach ($studentInvoices as $invoice) {
            foreach ($invoice->invoiceLines as $line) {
                if (! $this->isBillableActiveLine($line)) {
                    continue;
                }

                $amountSnapshot = (float) $line->amount_snapshot;

                // Only positive charge lines contribute to fee_type rows
                if ($amountSnapshot <= 0) {
                    continue;
                }

                $chargeType = $line->charge?->charge_type ?? '';
                $feeType = DngFeeTypeOptions::fromChargeType($chargeType);

                if (! isset($groups[$feeType])) {
                    $groups[$feeType] = [
                        'gross' => 0.0,
                        'discount' => 0.0,
                        'paid' => 0.0,
                        'semester_id' => $invoice->semester_id,
                    ];
                }

                $groups[$feeType]['gross'] += $amountSnapshot;
                $groups[$feeType]['discount'] += (float) $line->discountAllocations->sum('amount');
                $groups[$feeType]['paid'] += (float) $line->paymentApplications->sum('amount');
            }
        }

        $result = [];

        foreach ($groups as $feeType => $data) {
            $netRemaining = max(0.0, $data['gross'] - $data['discount'] - $data['paid']);

            if ($netRemaining <= 0) {
                continue;
            }

            $activeDng = $activeDngByFeeType->get($feeType);

            $result[] = [
                'fee_type' => $feeType,
                'label' => $feeTypeLabelMap[$feeType] ?? $feeType,
                'gross' => $data['gross'],
                'discount' => $data['discount'],
                'net_remaining' => $netRemaining,
                'semester_id' => $data['semester_id'],
                'active_dng' => $activeDng !== null ? [
                    'id' => $activeDng->id,
                    'amount' => (float) $activeDng->amount,
                    'status' => $activeDng->status,
                ] : null,
            ];
        }

        return $result;
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
