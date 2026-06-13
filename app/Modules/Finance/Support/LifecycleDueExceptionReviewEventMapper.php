<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;

final class LifecycleDueExceptionReviewEventMapper
{
    public static function mapWithContext(
        FinanceLifecycleDueExceptionReviewEvent $event,
        ?FinanceLifecycleDueExceptionReview $currentReview = null,
    ): array {
        $dngRequest = $event->dngPaymentRequest;
        $student = $event->student ?? $dngRequest?->student;

        return array_merge(self::map($event), [
            'dng_request' => self::mapDngRequest($dngRequest),
            'student' => self::mapStudent($student),
            'current_review' => self::mapCurrentReview($currentReview),
            'links' => self::mapLinks($dngRequest, $student),
        ]);
    }

    public static function map(FinanceLifecycleDueExceptionReviewEvent $event): array
    {
        $metadata = $event->metadata ?? [];

        return [
            'id' => $event->id,
            'event_type' => $event->event_type->value,
            'event_type_label' => $event->event_type->label(),
            'performed_at' => $event->performed_at,
            'actor' => $event->performedBy ? [
                'id' => $event->performedBy->id,
                'name' => $event->performedBy->name,
            ] : null,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'resolution_action' => $event->resolution_action?->value,
            'resolution_action_label' => $event->resolution_action?->label(),
            'resolution_reason' => $event->resolution_reason,
            'dng_status_before' => $event->dng_status_before,
            'dng_status_after' => $event->dng_status_after,
            'failure_summary' => $metadata['failure_summary'] ?? null,
            'impact_preview' => $metadata['impact_preview'] ?? null,
            'is_legacy_backfill' => (bool) ($metadata['legacy_backfill'] ?? false),
            'metadata' => $metadata,
        ];
    }

    public static function eventTypeForResolution(LifecycleDueExceptionResolutionAction $action): LifecycleDueExceptionReviewEventType
    {
        return match ($action) {
            LifecycleDueExceptionResolutionAction::Acknowledge => LifecycleDueExceptionReviewEventType::Acknowledged,
            LifecycleDueExceptionResolutionAction::KeepAsDebt => LifecycleDueExceptionReviewEventType::KeptAsDebt,
            LifecycleDueExceptionResolutionAction::RouteToSettlement => LifecycleDueExceptionReviewEventType::RoutedToSettlement,
            LifecycleDueExceptionResolutionAction::CancelDng,
            LifecycleDueExceptionResolutionAction::CancelDngAndVoidLinkedCharge => LifecycleDueExceptionReviewEventType::CancelRequested,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mapDngRequest(?DngPaymentRequest $request): ?array
    {
        if ($request === null) {
            return null;
        }

        return [
            'id' => $request->id,
            'item_id' => $request->item_id,
            'status' => $request->status,
            'amount' => (float) $request->amount,
            'fee_type' => $request->fee_type,
            'due_date' => $request->due_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mapStudent(?Student $student): ?array
    {
        if ($student === null) {
            return null;
        }

        return [
            'id' => $student->id,
            'student_code' => $student->student_id,
            'full_name' => $student->full_name,
            'status' => $student->status,
            'status_label' => $student->status_label,
            'status_color' => $student->status_color,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mapCurrentReview(?FinanceLifecycleDueExceptionReview $review): ?array
    {
        if ($review === null) {
            return null;
        }

        return [
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'exception_reason' => $review->exception_reason->value,
            'exception_reason_label' => $review->exception_reason->label(),
            'resolution_action' => $review->resolution_action?->value,
            'resolution_action_label' => $review->resolution_action?->label(),
            'resolution_reason' => $review->resolution_reason,
            'resolved_at' => $review->resolved_at,
            'resolved_by' => $review->resolvedBy ? [
                'id' => $review->resolvedBy->id,
                'name' => $review->resolvedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function mapLinks(?DngPaymentRequest $request, ?Student $student): array
    {
        if ($request === null) {
            return [
                'dng_payment_request' => null,
                'student_charges' => null,
                'settlement' => null,
                'lifecycle_exceptions' => route('finance.operations.lifecycle-exceptions'),
                'history' => null,
            ];
        }

        return [
            'dng_payment_request' => route('finance.dng.payment-requests.show', $request->id),
            'student_charges' => $student ? route('finance.students.charges', $student->id) : null,
            'settlement' => $student ? route('finance.operations.settlement.index', ['search' => $student->student_id]) : null,
            'lifecycle_exceptions' => route('finance.operations.lifecycle-exceptions'),
            'history' => route('finance.operations.lifecycle-exception-history', ['dng_payment_request_id' => $request->id]),
        ];
    }
}
