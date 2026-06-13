<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Support\Facades\DB;

class AcknowledgeLifecycleDueExceptionAction
{
    public function __construct(
        private readonly RecordLifecycleDueExceptionReviewEventAction $recordEventAction,
    ) {}

    /**
     * @return array{review: FinanceLifecycleDueExceptionReview}
     */
    public function run(DngPaymentRequest $request, string $reason, int $userId): array
    {
        if (! LifecycleDueItemPredicate::isOpenDueRequest($request)) {
            throw new \RuntimeException('DNG request is no longer an open due item.');
        }

        if (! LifecycleDueItemPredicate::isLifecycleException($request->student)) {
            throw new \RuntimeException('DNG request is not a lifecycle due exception.');
        }

        $exceptionReason = LifecycleDueExceptionReasonResolver::resolve($request->student);
        $existingReview = FinanceLifecycleDueExceptionReview::query()
            ->where('dng_payment_request_id', $request->id)
            ->first();
        $fromStatus = $existingReview?->status ?? LifecycleDueExceptionReviewStatus::Open;
        $dngStatusBefore = $request->status;

        $review = DB::transaction(function () use ($request, $reason, $userId, $exceptionReason): FinanceLifecycleDueExceptionReview {
            return FinanceLifecycleDueExceptionReview::query()->updateOrCreate(
                ['dng_payment_request_id' => $request->id],
                [
                    'student_id' => $request->student_id,
                    'exception_reason' => $exceptionReason,
                    'status' => LifecycleDueExceptionReviewStatus::Acknowledged,
                    'resolution_action' => LifecycleDueExceptionResolutionAction::Acknowledge,
                    'resolution_reason' => $reason,
                    'resolved_at' => now(),
                    'resolved_by_user_id' => $userId,
                    'last_seen_at' => now(),
                    'metadata' => [
                        'before_status' => 'open',
                        'after_status' => LifecycleDueExceptionReviewStatus::Acknowledged->value,
                    ],
                ]
            );
        });

        $this->recordEventAction->run(
            request: $request,
            eventType: LifecycleDueExceptionReviewEventType::Acknowledged,
            fromStatus: $fromStatus,
            toStatus: LifecycleDueExceptionReviewStatus::Acknowledged,
            resolutionAction: LifecycleDueExceptionResolutionAction::Acknowledge,
            resolutionReason: $reason,
            performedByUserId: $userId,
            review: $review,
            dngStatusBefore: $dngStatusBefore,
            dngStatusAfter: $request->fresh()->status,
        );

        return ['review' => $review];
    }
}
