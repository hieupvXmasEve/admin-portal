<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListPaymentsQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function handle(Request $request): array
    {
        $query = $this->buildFilteredQuery($request)
            ->with('receivedBy')
            ->withSum('applications as applied_amount_total', 'amount');

        $stats = $this->buildStats($this->buildFilteredQuery($request));

        $sort = $request->input('sort', 'paid_at');
        $direction = $request->input('direction', 'desc');
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $sort = is_string($sort) ? $sort : 'paid_at';
        if (in_array($sort, ['student_id', 'student_name'], true)) {
            $this->applyStudentSorting($query, $sort, $direction);
        } else {
            $this->applySorting($query, $sort, $direction);
        }

        $payments = $query->paginate($request->input('per_page', 15))
            ->withQueryString();
        $studentReferences = $this->studentReferences->findMany(
            $payments->getCollection()
                ->pluck('student_id')
                ->map(static fn (int|string $studentId): int => (int) $studentId)
                ->all(),
        );

        $payments->through(function (Payment $payment) use ($studentReferences): array {
            $allocatedAmount = max(0.0, (float) ($payment->applied_amount_total ?? 0));
            $student = $studentReferences[(int) $payment->student_id] ?? null;

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
                'student' => $student ? [
                    'id' => $student->id,
                    'full_name' => $student->fullName,
                    'student_id' => $student->studentCode,
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
        $search = trim((string) $request->input('search', ''));
        $campusStudentIds = $campusId === null ? null : $this->studentReferences->idsForCampus((int) $campusId);
        $matchingStudentIds = $search === '' ? [] : $this->studentReferences->idsMatchingSearch($search, $campusId === null ? null : (int) $campusId);

        $query = Payment::query()
            ->when($campusStudentIds !== null, fn (Builder $builder) => $builder->whereIn('student_id', $campusStudentIds));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search, $matchingStudentIds): void {
                $query->where('external_ref', 'like', "%{$search}%")
                    ->orWhereIn('student_id', $matchingStudentIds);
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
            'unapplied_amount' => $query->orderByRaw(
                sprintf('(payments.amount - COALESCE(applied_amount_total, 0)) %s', $direction)
            ),
            default => $query->orderBy('payments.paid_at', 'desc'),
        };

        $query->orderBy('payments.id', 'desc');
    }

    private function applyStudentSorting(Builder $query, string $sort, string $direction): void
    {
        $studentIds = (clone $query)
            ->select('student_id')
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $studentReferences = $this->studentReferences->findMany($studentIds);

        uasort($studentReferences, static function (StudentReference $left, StudentReference $right) use ($sort, $direction): int {
            $leftValue = $sort === 'student_id' ? $left->studentCode : $left->fullName;
            $rightValue = $sort === 'student_id' ? $right->studentCode : $right->fullName;
            $comparison = strnatcasecmp($leftValue, $rightValue);

            return $direction === 'asc' ? $comparison : -$comparison;
        });
        $orderedStudentIds = array_keys($studentReferences);
        if ($orderedStudentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $cases = implode(' ', array_map(
            static fn (int $index): string => 'WHEN ? THEN '.$index,
            array_keys($orderedStudentIds),
        ));
        $query->orderByRaw('CASE payments.student_id '.$cases.' ELSE '.count($orderedStudentIds).' END', $orderedStudentIds);
        $query->orderBy('payments.id', 'desc');
    }
}
