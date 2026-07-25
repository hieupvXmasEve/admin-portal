<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Shared\Contracts\Academic\StudentLifecycleActionReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListLifecycleDueExceptionsQuery
{
    public function __construct(
        private readonly StudentLifecycleActionReader $lifecycleActions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();
        $user = Auth::user();
        $canCancelDng = $user?->can('create_finance_payments') ?? false;
        $canVoidCharges = $user?->can('void_finance_charges') ?? false;

        $query = DngPaymentRequest::query()
            ->with(['student:id,student_id,full_name,status,campus_id'])
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->whereNotNull('due_date');

        LifecycleDueItemPredicate::applyLifecycleExceptionScope($query);

        if ($campusId) {
            $query->where(function ($campusQuery) use ($campusId): void {
                $campusQuery
                    ->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId))
                    ->orWhereNull('student_id');
            });
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', (int) $filters['semester_id']);
        }

        if (! empty($filters['lifecycle_status'])) {
            $reason = LifecycleDueExceptionReason::tryFrom((string) $filters['lifecycle_status']);
            if ($reason !== null) {
                $this->applyLifecycleReasonFilter($query, $reason);
            }
        }

        if (! empty($filters['fee_type'])) {
            $query->where('fee_type', (string) $filters['fee_type']);
        }

        if (! empty($filters['due_status']) && $filters['due_status'] !== 'all') {
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
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['review_status']) && $filters['review_status'] !== 'all') {
            $reviewStatus = (string) $filters['review_status'];
            $reviewedRequestIds = FinanceLifecycleDueExceptionReview::query()
                ->where('status', $reviewStatus)
                ->pluck('dng_payment_request_id');

            if ($reviewStatus === 'open') {
                $query->whereNotIn('id', FinanceLifecycleDueExceptionReview::query()
                    ->where('status', '!=', 'open')
                    ->pluck('dng_payment_request_id'));
            } else {
                $query->whereIn('id', $reviewedRequestIds);
            }
        }

        $sort = (string) ($filters['sort'] ?? 'due_date');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['due_date', 'amount', 'id', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'due_date';
        }
        $query->orderBy($sort, $direction);

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $paginator = $query->paginate($perPage)->withQueryString();
        $requestIds = $paginator->getCollection()->pluck('id');
        $reviews = FinanceLifecycleDueExceptionReview::query()
            ->whereIn('dng_payment_request_id', $requestIds)
            ->get()
            ->keyBy('dng_payment_request_id');
        $latestActions = $this->lifecycleActions->latestForStudentIds(
            $paginator->getCollection()->pluck('student_id')->filter()->map(static fn (int|string $id): int => (int) $id)->all(),
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (DngPaymentRequest $request) => LifecycleDueExceptionRowMapper::map(
                    $request,
                    $reviews->get($request->id),
                    $request->student_id === null ? null : ($latestActions[(int) $request->student_id] ?? null),
                    $today,
                    $canCancelDng,
                    $canVoidCharges,
                )
            )
        );

        return $paginator;
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    private function applyLifecycleReasonFilter($query, LifecycleDueExceptionReason $reason): void
    {
        match ($reason) {
            LifecycleDueExceptionReason::MissingStudent => $query->where(function ($missingQuery): void {
                $missingQuery->whereNull('student_id')->orWhereDoesntHave('student');
            }),
            LifecycleDueExceptionReason::Deferred,
            LifecycleDueExceptionReason::Dropout,
            LifecycleDueExceptionReason::DropoutTransfer => $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->where('status', $reason->value)
            ),
            LifecycleDueExceptionReason::InactiveOrNonFinancial => $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->whereNotIn('status', [
                    'deferred',
                    'dropout',
                    'dropout_transfer',
                    ...Student::FINANCIAL_STATUSES,
                ])
            ),
        };
    }
}
