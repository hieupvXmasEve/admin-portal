<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use Carbon\CarbonInterface;

/**
 * Immutable result of {@see ExamResitDueClassifier}: where a single exam-resit
 * (thi lại / PTL) obligation sits on the Finance Due Reminders worklist.
 *
 * Reminder eligibility has three source states (ACAD-RET-002):
 *  - needs_charge_or_dng: approved/scheduled source with no active charge or
 *    pushed DNG yet — visible for triage/handoff, not directly remindable;
 *  - remindable: an active pushed DNG request exists, unpaid, not blocked;
 *  - blocked: paid, cancelled, lifecycle exception, or missing student email.
 */
final class ExamResitDueClassification
{
    public const REMINDER_STATE_NEEDS_CHARGE_OR_DNG = 'needs_charge_or_dng';

    public const REMINDER_STATE_REMINDABLE = 'remindable';

    public const REMINDER_STATE_BLOCKED = 'blocked';

    public const DUE_STATE_UPCOMING = 'upcoming';

    public const DUE_STATE_DUE_TODAY = 'due_today';

    public const DUE_STATE_OVERDUE = 'overdue';

    public const DUE_STATE_NEEDS_CHARGE_OR_DNG = 'needs_charge_or_dng';

    public const DUE_STATE_BLOCKED = 'blocked';

    public const BLOCKED_PAID = 'paid';

    public const BLOCKED_CANCELLED = 'cancelled';

    public const BLOCKED_LIFECYCLE_EXCEPTION = 'lifecycle_exception';

    public const BLOCKED_MISSING_EMAIL = 'missing_email';

    public function __construct(
        public readonly string $reminderState,
        public readonly string $dueState,
        public readonly ?CarbonInterface $dueAt,
        public readonly ?int $daysOverdue,
        public readonly ?string $blockedReason,
    ) {}

    public function isRemindable(): bool
    {
        return $this->reminderState === self::REMINDER_STATE_REMINDABLE;
    }

    public function isOverdue(): bool
    {
        return $this->dueState === self::DUE_STATE_OVERDUE;
    }

    /**
     * @return array{
     *   reminder_state:string,
     *   due_state:string,
     *   due_at:?string,
     *   days_overdue:?int,
     *   blocked_reason:?string,
     *   is_remindable:bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'reminder_state' => $this->reminderState,
            'due_state' => $this->dueState,
            'due_at' => $this->dueAt?->toIso8601String(),
            'days_overdue' => $this->daysOverdue,
            'blocked_reason' => $this->blockedReason,
            'is_remindable' => $this->isRemindable(),
        ];
    }
}
