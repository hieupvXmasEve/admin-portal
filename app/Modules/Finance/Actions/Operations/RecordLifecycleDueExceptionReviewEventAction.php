<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;

class RecordLifecycleDueExceptionReviewEventAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function run(
        DngPaymentRequest $request,
        LifecycleDueExceptionReviewEventType $eventType,
        ?LifecycleDueExceptionReviewStatus $fromStatus,
        ?LifecycleDueExceptionReviewStatus $toStatus,
        ?LifecycleDueExceptionResolutionAction $resolutionAction,
        ?string $resolutionReason,
        ?int $performedByUserId,
        ?FinanceLifecycleDueExceptionReview $review = null,
        ?string $dngStatusBefore = null,
        ?string $dngStatusAfter = null,
        array $metadata = [],
        ?\DateTimeInterface $performedAt = null,
    ): FinanceLifecycleDueExceptionReviewEvent {
        return FinanceLifecycleDueExceptionReviewEvent::query()->create([
            'finance_lifecycle_due_exception_review_id' => $review?->id,
            'dng_payment_request_id' => $request->id,
            'student_id' => $request->student_id,
            'event_type' => $eventType,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'resolution_action' => $resolutionAction?->value,
            'resolution_reason' => $resolutionReason,
            'performed_by_user_id' => $performedByUserId,
            'performed_at' => $performedAt ?? now(),
            'dng_status_before' => $dngStatusBefore ?? $request->status,
            'dng_status_after' => $dngStatusAfter,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
