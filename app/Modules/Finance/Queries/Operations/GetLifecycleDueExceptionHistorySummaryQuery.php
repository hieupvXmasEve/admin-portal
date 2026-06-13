<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;
use Illuminate\Database\Eloquent\Builder;

class GetLifecycleDueExceptionHistorySummaryQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function handle(array $filters): array
    {
        $baseQuery = fn (): Builder => ListLifecycleDueExceptionHistoryQuery::applyFilters(
            FinanceLifecycleDueExceptionReviewEvent::query(),
            $filters,
        );

        $failedTypes = [
            LifecycleDueExceptionReviewEventType::CancelFailed->value,
            LifecycleDueExceptionReviewEventType::VoidFailed->value,
        ];

        $destructiveTypes = [
            LifecycleDueExceptionReviewEventType::CancelRequested->value,
            LifecycleDueExceptionReviewEventType::CancelSucceeded->value,
            LifecycleDueExceptionReviewEventType::CancelFailed->value,
            LifecycleDueExceptionReviewEventType::VoidRequested->value,
            LifecycleDueExceptionReviewEventType::VoidSucceeded->value,
            LifecycleDueExceptionReviewEventType::VoidFailed->value,
        ];

        $resolvedTypes = [
            LifecycleDueExceptionReviewEventType::CancelSucceeded->value,
            LifecycleDueExceptionReviewEventType::VoidSucceeded->value,
            LifecycleDueExceptionReviewEventType::KeptAsDebt->value,
            LifecycleDueExceptionReviewEventType::RoutedToSettlement->value,
        ];

        return [
            'total_events' => (clone $baseQuery())->count(),
            'failed_actions' => (clone $baseQuery())->whereIn('event_type', $failedTypes)->count(),
            'destructive_attempts' => (clone $baseQuery())->whereIn('event_type', $destructiveTypes)->count(),
            'resolved_actions' => (clone $baseQuery())->whereIn('event_type', $resolvedTypes)->count(),
        ];
    }
}
