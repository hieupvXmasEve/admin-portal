<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Database\Eloquent\Builder;

class GetLifecycleDueExceptionSummaryQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(?int $semesterId, array $filters = []): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $baseQuery = fn () => DngPaymentRequest::query()
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->whereNotNull('due_date')
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($campusId, function ($query) use ($campusId): void {
                $query->where(function ($campusQuery) use ($campusId): void {
                    $campusQuery
                        ->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId))
                        ->orWhereNull('student_id');
                });
            });

        $exceptionQuery = $baseQuery();
        LifecycleDueItemPredicate::applyLifecycleExceptionScope($exceptionQuery);
        $this->applySharedFilters($exceptionQuery, $filters);

        $deferredCount = (clone $exceptionQuery)->whereHas('student', fn ($q) => $q->where('status', 'deferred'))->count();
        $dropoutCount = (clone $exceptionQuery)->whereHas('student', fn ($q) => $q->where('status', 'dropout'))->count();
        $transferCount = (clone $exceptionQuery)->whereHas('student', fn ($q) => $q->where('status', 'dropout_transfer'))->count();
        $otherCount = (clone $exceptionQuery)->where(function ($otherQuery): void {
            $otherQuery
                ->whereNull('student_id')
                ->orWhereDoesntHave('student')
                ->orWhereHas('student', fn ($studentQuery) => $studentQuery->whereNotIn('status', [
                    'deferred',
                    'dropout',
                    'dropout_transfer',
                    ...Student::FINANCIAL_STATUSES,
                ]));
        })->count();

        return [
            'deferred_count' => $deferredCount,
            'dropout_count' => $dropoutCount,
            'transfer_count' => $transferCount,
            'other_count' => $otherCount,
            'total_count' => $exceptionQuery->count(),
            'total_amount' => (float) $exceptionQuery->sum('amount'),
        ];
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySharedFilters($query, array $filters): void
    {
        if (! empty($filters['fee_type'])) {
            $query->where('fee_type', (string) $filters['fee_type']);
        }

        if (! empty($filters['due_status']) && $filters['due_status'] !== 'all') {
            $today = now()->startOfDay();
            match ($filters['due_status']) {
                'upcoming' => $query->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)]),
                'due_today' => $query->whereDate('due_date', $today),
                'overdue' => $query->where('due_date', '<', $today),
                default => null,
            };
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('student_code', 'like', "%{$search}%")
                    ->orWhere('item_id', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }
    }
}
