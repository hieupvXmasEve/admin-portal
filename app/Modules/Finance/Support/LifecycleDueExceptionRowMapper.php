<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use Carbon\CarbonInterface;

final class LifecycleDueExceptionRowMapper
{
    /**
     * @return array<int, string>
     */
    public static function blockingReasons(DngPaymentRequest $request): array
    {
        $reasons = [];

        if ($request->hasBridgedPayment() || $request->payment_id !== null) {
            $reasons[] = 'DNG request đã có payment bridge';
        }

        if (! in_array($request->status, [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        ], true)) {
            $reasons[] = "Trạng thái DNG hiện tại ({$request->status}) không cho phép hủy";
        }

        return $reasons;
    }

    /**
     * @return array<int, string>
     */
    public static function availableActions(
        DngPaymentRequest $request,
        ?FinanceLifecycleDueExceptionReview $review,
        bool $canCancelDng,
        bool $canVoidCharges,
    ): array {
        $actions = [
            'acknowledge',
            'keep_as_debt',
            'route_to_settlement',
        ];

        if ($canCancelDng && self::blockingReasons($request) === []) {
            $actions[] = 'cancel_dng';
        }

        if ($canVoidCharges && $canCancelDng && self::blockingReasons($request) === []) {
            $actions[] = 'cancel_dng_and_void_linked_charge';
        }

        if ($review?->status === LifecycleDueExceptionReviewStatus::Resolved) {
            return [];
        }

        return $actions;
    }

    public static function map(
        DngPaymentRequest $request,
        ?FinanceLifecycleDueExceptionReview $review,
        CarbonInterface $today,
        bool $canCancelDng,
        bool $canVoidCharges,
    ): array {
        $student = $request->student;
        $exceptionReason = LifecycleDueExceptionReasonResolver::resolve($student);
        $dueDate = $request->due_date;
        $daysUntilDue = $dueDate ? $today->diffInDays($dueDate, false) : 0;

        $dueStatus = 'upcoming';
        if ($daysUntilDue < 0) {
            $dueStatus = 'overdue';
        } elseif ($daysUntilDue === 0) {
            $dueStatus = 'due_today';
        }

        $reviewStatus = $review?->status ?? LifecycleDueExceptionReviewStatus::Open;
        $linkedCharges = self::resolveLinkedCharges($request);
        $latestAction = $student
            ? StudentActionLog::query()
                ->where('student_id', $student->id)
                ->latest('id')
                ->first(['id', 'action_type', 'created_at', 'notes'])
            : null;
        $latestDeferCase = $student
            ? DeferCase::query()
                ->where('student_id', $student->id)
                ->latest('id')
                ->first(['id', 'fee_policy', 'scope_type', 'created_at'])
            : null;

        return [
            'id' => $request->id,
            'item_id' => $request->item_id,
            'fee_type' => $request->fee_type,
            'amount' => (float) $request->amount,
            'dng_status' => $request->status,
            'due_date' => $dueDate?->toDateString(),
            'days_overdue' => $daysUntilDue < 0 ? abs($daysUntilDue) : 0,
            'due_status' => $dueStatus,
            'last_reminder_at' => $request->last_reminder_at,
            'student' => $student ? [
                'id' => $student->id,
                'student_code' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'status_label' => $student->status_label,
                'status_color' => $student->status_color,
            ] : null,
            'finance_charge_id' => $request->finance_charge_id,
            'linked_charges' => $linkedCharges,
            'payment_id' => $request->payment_id,
            'has_bridged_payment' => $request->hasBridgedPayment(),
            'exception_reason' => $exceptionReason->value,
            'exception_reason_label' => $exceptionReason->label(),
            'recommended_action' => LifecycleDueExceptionReasonResolver::recommendedAction($exceptionReason),
            'review_status' => $reviewStatus->value,
            'review_status_label' => $reviewStatus->label(),
            'resolution_action' => $review?->resolution_action?->value,
            'resolution_reason' => $review?->resolution_reason,
            'resolved_at' => $review?->resolved_at,
            'available_actions' => self::availableActions($request, $review, $canCancelDng, $canVoidCharges),
            'blocking_reasons' => self::blockingReasons($request),
            'latest_student_action' => $latestAction ? [
                'id' => $latestAction->id,
                'action_type' => $latestAction->action_type,
                'created_at' => $latestAction->created_at,
                'notes' => $latestAction->notes,
            ] : null,
            'defer_case' => $latestDeferCase ? [
                'id' => $latestDeferCase->id,
                'fee_policy' => $latestDeferCase->fee_policy,
                'scope_type' => $latestDeferCase->scope_type,
                'created_at' => $latestDeferCase->created_at,
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function resolveLinkedCharges(DngPaymentRequest $request): array
    {
        $request->loadMissing(['chargeLinks.financeCharge', 'financeCharge']);

        $charges = $request->chargeLinks
            ->map(fn ($link) => $link->financeCharge)
            ->filter()
            ->values();

        if ($charges->isEmpty() && $request->financeCharge) {
            $charges = collect([$request->financeCharge]);
        }

        return $charges
            ->map(fn (FinanceCharge $charge) => [
                'id' => $charge->id,
                'charge_type' => $charge->charge_type,
                'status' => $charge->status,
                'amount' => (float) $charge->amount,
            ])
            ->values()
            ->all();
    }
}
