<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListPaymentsQuery
{
    public function handle(Request $request): array
    {
        $query = $this->buildFilteredQuery($request)
            ->with(['student', 'receivedBy'])
            ->withSum('applications as applied_amount_total', 'amount');

        $stats = $this->buildStats($this->buildFilteredQuery($request));

        $sort = $request->input('sort', 'paid_at');
        $direction = $request->input('direction', 'desc');
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $this->applySorting($query, is_string($sort) ? $sort : 'paid_at', $direction);

        $payments = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        $payments->through(function (Payment $payment): array {
            $allocatedAmount = max(0.0, (float) ($payment->applied_amount_total ?? 0));

            return [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'source' => $payment->source,
                'external_ref' => $payment->external_ref,
                'status' => $payment->status,
                'method' => $payment->method,
                'allocated_amount' => (float) $allocatedAmount,
                'unapplied_amount' => (float) max(0.0, (float) $payment->amount - $allocatedAmount),
                'student' => $payment->student ? [
                    'id' => $payment->student->id,
                    'full_name' => $payment->student->full_name,
                    'student_id' => $payment->student->student_id,
                ] : null,
            ];
        });

        return [
            'items' => $payments,
            'stats' => $stats,
        ];
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $campusId = app('campus')?->id;

        $query = Payment::query()
            ->when($campusId, fn (Builder $builder) => $builder->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId)));

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('external_ref', 'like', "%{$term}%")
                    ->orWhereHas('student', function ($subQ) use ($term) {
                        $subQ->where('full_name', 'like', "%{$term}%")
                            ->orWhere('student_id', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        // Filter by Source
        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }

        // Filter by Status
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by Date Range
        if ($request->filled('date_range')) {
            $dateRange = $request->input('date_range');
            if (is_string($dateRange) && str_contains($dateRange, ',')) {
                [$start, $end] = explode(',', $dateRange);
                $query->whereBetween('paid_at', [$start, $end]);
            } elseif (is_array($dateRange) && count($dateRange) === 2) {
                $query->whereBetween('paid_at', $dateRange);
            }
        }

        return $query;
    }

    private function buildStats(Builder $query): array
    {
        $payments = $query
            ->withSum('applications as applied_amount_total', 'amount')
            ->get(['id', 'amount']);

        $totalPaid = (float) $payments->sum(fn (Payment $payment) => (float) $payment->amount);
        $totalApplied = (float) $payments->sum(fn (Payment $payment) => max(0.0, (float) ($payment->applied_amount_total ?? 0)));

        return [
            'payment_count' => $payments->count(),
            'total_paid' => $totalPaid,
            'total_applied' => $totalApplied,
            'total_unapplied' => (float) max(0.0, $totalPaid - $totalApplied),
        ];
    }

    private function applySorting(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'amount', 'paid_at', 'status', 'created_at' => $query->orderBy("payments.{$sort}", $direction),
            'student_id' => $query->orderBy(
                Student::query()
                    ->select('student_id')
                    ->whereColumn('students.id', 'payments.student_id')
                    ->limit(1),
                $direction
            ),
            'student_name' => $query->orderBy(
                Student::query()
                    ->select('full_name')
                    ->whereColumn('students.id', 'payments.student_id')
                    ->limit(1),
                $direction
            ),
            'unapplied_amount' => $query->orderByRaw(
                sprintf('(payments.amount - COALESCE(applied_amount_total, 0)) %s', $direction)
            ),
            default => $query->orderBy('payments.paid_at', 'desc'),
        };

        $query->orderBy('payments.id', 'desc');
    }
}
