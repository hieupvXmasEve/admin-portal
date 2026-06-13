<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;
use App\Modules\Finance\Support\LifecycleDueExceptionReviewEventMapper;

class BackfillLifecycleDueExceptionReviewEventsAction
{
    public function __construct(
        private readonly RecordLifecycleDueExceptionReviewEventAction $recordEventAction,
    ) {}

    public function run(): int
    {
        $reviewedRequestIds = FinanceLifecycleDueExceptionReviewEvent::query()
            ->distinct()
            ->pluck('dng_payment_request_id');

        $reviews = FinanceLifecycleDueExceptionReview::query()
            ->with('dngPaymentRequest')
            ->when($reviewedRequestIds->isNotEmpty(), function ($query) use ($reviewedRequestIds): void {
                $query->whereNotIn('dng_payment_request_id', $reviewedRequestIds);
            })
            ->get();

        $created = 0;

        foreach ($reviews as $review) {
            $request = $review->dngPaymentRequest;
            if ($request === null) {
                continue;
            }

            $metadata = array_merge($review->metadata ?? [], [
                'legacy_backfill' => true,
                'legacy_source' => 'finance_lifecycle_due_exception_reviews',
            ]);

            $fromStatus = isset($review->metadata['before_status'])
                ? LifecycleDueExceptionReviewStatus::tryFrom((string) $review->metadata['before_status'])
                : LifecycleDueExceptionReviewStatus::Open;

            $performedAt = $review->resolved_at ?? $review->updated_at ?? $review->created_at ?? now();
            $dngStatusAfter = $review->metadata['after_dng_status'] ?? $request->status;

            foreach ($this->eventTypesForReview($review) as $eventType) {
                $this->recordEventAction->run(
                    request: $request,
                    eventType: $eventType,
                    fromStatus: $fromStatus,
                    toStatus: $review->status,
                    resolutionAction: $review->resolution_action,
                    resolutionReason: $review->resolution_reason,
                    performedByUserId: $review->resolved_by_user_id,
                    review: $review,
                    dngStatusBefore: $request->status,
                    dngStatusAfter: is_string($dngStatusAfter) ? $dngStatusAfter : $request->status,
                    metadata: $metadata,
                    performedAt: $performedAt,
                );

                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array<int, LifecycleDueExceptionReviewEventType>
     */
    private function eventTypesForReview(FinanceLifecycleDueExceptionReview $review): array
    {
        $action = $review->resolution_action;
        if ($action === null) {
            return [LifecycleDueExceptionReviewEventType::Detected];
        }

        if (in_array($action, [
            LifecycleDueExceptionResolutionAction::CancelDng,
            LifecycleDueExceptionResolutionAction::CancelDngAndVoidLinkedCharge,
        ], true)) {
            if ($review->status === LifecycleDueExceptionReviewStatus::Resolved) {
                $events = [LifecycleDueExceptionReviewEventType::CancelSucceeded];

                if ($action === LifecycleDueExceptionResolutionAction::CancelDngAndVoidLinkedCharge) {
                    $events[] = LifecycleDueExceptionReviewEventType::VoidSucceeded;
                }

                return $events;
            }

            return [LifecycleDueExceptionReviewEventType::CancelRequested];
        }

        return [LifecycleDueExceptionReviewEventMapper::eventTypeForResolution($action)];
    }
}
