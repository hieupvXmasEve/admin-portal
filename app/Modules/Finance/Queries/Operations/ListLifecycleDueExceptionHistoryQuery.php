<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\User;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;
use App\Modules\Finance\Support\LifecycleDueExceptionReviewEventMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListLifecycleDueExceptionHistoryQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = self::applyFilters($this->baseQuery(), $filters);

        $sort = (string) ($filters['sort'] ?? 'performed_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['performed_at', 'event_type', 'id'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'performed_at';
        }

        $query->orderBy($sort, $direction)->orderByDesc('id');

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $paginator = $query->paginate($perPage)->withQueryString();

        $requestIds = $paginator->getCollection()->pluck('dng_payment_request_id')->unique()->filter();
        $reviews = FinanceLifecycleDueExceptionReview::query()
            ->with('resolvedBy:id,name')
            ->whereIn('dng_payment_request_id', $requestIds)
            ->get()
            ->keyBy('dng_payment_request_id');

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (FinanceLifecycleDueExceptionReviewEvent $event) => LifecycleDueExceptionReviewEventMapper::mapWithContext(
                    $event,
                    $reviews->get($event->dng_payment_request_id),
                )
            )
        );

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function selectedTimeline(array $filters): array
    {
        $dngPaymentRequestId = $filters['dng_payment_request_id'] ?? null;
        if ($dngPaymentRequestId === null || $dngPaymentRequestId === '') {
            return [];
        }

        $review = FinanceLifecycleDueExceptionReview::query()
            ->with('resolvedBy:id,name')
            ->where('dng_payment_request_id', (int) $dngPaymentRequestId)
            ->first();

        return self::applyFilters(
            $this->baseQuery()->where('dng_payment_request_id', (int) $dngPaymentRequestId),
            $filters,
        )
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FinanceLifecycleDueExceptionReviewEvent $event) => LifecycleDueExceptionReviewEventMapper::mapWithContext($event, $review))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $actors = User::query()
            ->select(['users.id', 'users.name'])
            ->join('finance_lifecycle_due_exception_review_events', 'users.id', '=', 'finance_lifecycle_due_exception_review_events.performed_by_user_id')
            ->distinct()
            ->orderBy('users.name')
            ->limit(50)
            ->get()
            ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])
            ->values()
            ->all();

        return [
            'event_types' => $this->enumOptions(LifecycleDueExceptionReviewEventType::cases()),
            'review_statuses' => $this->enumOptions(LifecycleDueExceptionReviewStatus::cases()),
            'exception_reasons' => $this->enumOptions(LifecycleDueExceptionReason::cases()),
            'actors' => $actors,
        ];
    }

    /**
     * @param  Builder<FinanceLifecycleDueExceptionReviewEvent>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<FinanceLifecycleDueExceptionReviewEvent>
     */
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        if ($campusId) {
            $query->where(function (Builder $campusQuery) use ($campusId): void {
                $campusQuery
                    ->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId))
                    ->orWhereHas('dngPaymentRequest', function (Builder $dngQuery) use ($campusId): void {
                        $dngQuery
                            ->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId))
                            ->orWhereNull('student_id');
                    });
            });
        }

        if (! empty($filters['dng_payment_request_id'])) {
            $query->where('dng_payment_request_id', (int) $filters['dng_payment_request_id']);
        }

        if (! empty($filters['dng_item_id'])) {
            $itemId = (string) $filters['dng_item_id'];
            $query->whereHas('dngPaymentRequest', fn (Builder $dngQuery) => $dngQuery->where('item_id', 'like', "%{$itemId}%"));
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', (string) $filters['event_type']);
        }

        if (! empty($filters['performed_by_user_id'])) {
            $query->where('performed_by_user_id', (int) $filters['performed_by_user_id']);
        }

        if (! empty($filters['performed_from'])) {
            $query->where('performed_at', '>=', $filters['performed_from']);
        }

        if (! empty($filters['performed_to'])) {
            $query->where('performed_at', '<=', $filters['performed_to'].' 23:59:59');
        }

        if (! empty($filters['review_status'])) {
            $reviewStatus = (string) $filters['review_status'];
            $reviewedRequestIds = FinanceLifecycleDueExceptionReview::query()
                ->where('status', $reviewStatus)
                ->pluck('dng_payment_request_id');

            if ($reviewStatus === LifecycleDueExceptionReviewStatus::Open->value) {
                $query->whereNotIn(
                    'dng_payment_request_id',
                    FinanceLifecycleDueExceptionReview::query()
                        ->where('status', '!=', LifecycleDueExceptionReviewStatus::Open->value)
                        ->pluck('dng_payment_request_id')
                );
            } else {
                $query->whereIn('dng_payment_request_id', $reviewedRequestIds);
            }
        }

        if (! empty($filters['exception_reason'])) {
            $query->whereIn(
                'dng_payment_request_id',
                FinanceLifecycleDueExceptionReview::query()
                    ->where('exception_reason', (string) $filters['exception_reason'])
                    ->pluck('dng_payment_request_id')
            );
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('dng_payment_request_id', 'like', "%{$search}%")
                    ->orWhereHas('dngPaymentRequest', function (Builder $dngQuery) use ($search): void {
                        $dngQuery
                            ->where('item_id', 'like', "%{$search}%")
                            ->orWhere('student_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('student', function (Builder $studentQuery) use ($search): void {
                        $studentQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * @return Builder<FinanceLifecycleDueExceptionReviewEvent>
     */
    private function baseQuery(): Builder
    {
        return FinanceLifecycleDueExceptionReviewEvent::query()
            ->with([
                'performedBy:id,name',
                'dngPaymentRequest:id,item_id,status,amount,fee_type,due_date,student_id,student_code',
                'dngPaymentRequest.student:id,student_id,full_name,status,campus_id',
                'student:id,student_id,full_name,status,campus_id',
            ]);
    }

    /**
     * @param  array<int, LifecycleDueExceptionReviewEventType|LifecycleDueExceptionReviewStatus|LifecycleDueExceptionReason>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return Collection::make($cases)
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->values()
            ->all();
    }
}
