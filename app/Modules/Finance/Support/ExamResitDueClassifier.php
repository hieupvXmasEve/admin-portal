<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\ExamResitAttempt;
use App\Modules\Finance\Models\FinanceCharge;
use Carbon\CarbonInterface;

/**
 * Classify a single exam-resit (thi lại / PTL) obligation for the Finance Due
 * Reminders worklist (ACAD-RET-002).
 *
 * Paid state is derived ONLY from canonical Finance evidence (hq_fee_status
 * paid, or an active fully-paid FinanceCharge), never a manual flag. Overdue age
 * is derived from the scheduled sitting time plus the snapshotted grace days
 * (default 14); an unscheduled source has no overdue clock yet.
 *
 * The classifier never queries DNG linkage itself — the caller passes
 * `$hasActivePushedDng` (resolved in batch by {@see ExamResitDngLinkResolver})
 * so this stays pure and N+1-free.
 */
class ExamResitDueClassifier
{
    public const DEFAULT_GRACE_DAYS = 14;

    public function classify(
        ExamResitAttempt $attempt,
        bool $hasActivePushedDng,
        ?CarbonInterface $now = null,
    ): ExamResitDueClassification {
        $now = $now ?? now();

        $dueAt = $this->deriveDueAt($attempt);
        $daysOverdue = $this->deriveDaysOverdue($dueAt, $now);

        // Hard blocks suppress the row from any collection action: a paid,
        // cancelled, or out-of-lifecycle obligation is neither remindable nor a
        // charge/DNG handoff candidate.
        $hardBlock = $this->resolveHardBlock($attempt);
        if ($hardBlock !== null) {
            return $this->blocked($hardBlock, $dueAt, $daysOverdue);
        }

        // Without an active pushed DNG the row is a triage/handoff source, not
        // remindable — and missing email is irrelevant until there is a DNG to
        // remind against.
        if (! $hasActivePushedDng) {
            return new ExamResitDueClassification(
                reminderState: ExamResitDueClassification::REMINDER_STATE_NEEDS_CHARGE_OR_DNG,
                dueState: ExamResitDueClassification::DUE_STATE_NEEDS_CHARGE_OR_DNG,
                dueAt: $dueAt,
                daysOverdue: $daysOverdue,
                blockedReason: null,
            );
        }

        if (! $this->hasStudentEmail($attempt)) {
            return $this->blocked(ExamResitDueClassification::BLOCKED_MISSING_EMAIL, $dueAt, $daysOverdue);
        }

        return new ExamResitDueClassification(
            reminderState: ExamResitDueClassification::REMINDER_STATE_REMINDABLE,
            dueState: $this->deriveDueState($dueAt, $now),
            dueAt: $dueAt,
            daysOverdue: $daysOverdue,
            blockedReason: null,
        );
    }

    private function blocked(string $reason, ?CarbonInterface $dueAt, ?int $daysOverdue): ExamResitDueClassification
    {
        return new ExamResitDueClassification(
            reminderState: ExamResitDueClassification::REMINDER_STATE_BLOCKED,
            dueState: ExamResitDueClassification::DUE_STATE_BLOCKED,
            dueAt: $dueAt,
            daysOverdue: $daysOverdue,
            blockedReason: $reason,
        );
    }

    /**
     * The payment deadline used for the overdue clock. Prefer a stored
     * `payment_deadline` (or a future actual-sitting deadline) when present;
     * otherwise derive it from the scheduled sitting time plus grace days.
     */
    public function deriveDueAt(ExamResitAttempt $attempt): ?CarbonInterface
    {
        if ($attempt->payment_deadline !== null) {
            return $attempt->payment_deadline;
        }

        $slot = $attempt->session?->roomSlot;
        if ($slot === null || $slot->exam_date === null) {
            return null;
        }

        $graceDays = $attempt->late_payment_grace_days_snapshot ?? self::DEFAULT_GRACE_DAYS;

        return $this->combineDateTime($slot->exam_date, $slot->start_time)
            ->copy()
            ->addDays($graceDays);
    }

    private function resolveHardBlock(ExamResitAttempt $attempt): ?string
    {
        if ($this->isPaid($attempt)) {
            return ExamResitDueClassification::BLOCKED_PAID;
        }

        if ($attempt->status === ExamResitAttempt::STATUS_CANCELLED
            || $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_CANCELLED) {
            return ExamResitDueClassification::BLOCKED_CANCELLED;
        }

        if (LifecycleDueItemPredicate::isLifecycleException($attempt->student)) {
            return ExamResitDueClassification::BLOCKED_LIFECYCLE_EXCEPTION;
        }

        return null;
    }

    private function isPaid(ExamResitAttempt $attempt): bool
    {
        if ($attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID) {
            return true;
        }

        $charge = $attempt->financeCharge;

        return $charge !== null
            && $charge->status === FinanceCharge::STATUS_ACTIVE
            && $charge->is_fully_paid;
    }

    private function hasStudentEmail(ExamResitAttempt $attempt): bool
    {
        $email = $attempt->student?->email;

        return is_string($email) && trim($email) !== '';
    }

    private function deriveDueState(?CarbonInterface $dueAt, CarbonInterface $now): string
    {
        if ($dueAt === null) {
            return ExamResitDueClassification::DUE_STATE_UPCOMING;
        }

        $dueDay = $dueAt->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($dueDay->lessThan($today)) {
            return ExamResitDueClassification::DUE_STATE_OVERDUE;
        }

        if ($dueDay->equalTo($today)) {
            return ExamResitDueClassification::DUE_STATE_DUE_TODAY;
        }

        return ExamResitDueClassification::DUE_STATE_UPCOMING;
    }

    private function deriveDaysOverdue(?CarbonInterface $dueAt, CarbonInterface $now): ?int
    {
        if ($dueAt === null) {
            return null;
        }

        $diff = (int) $now->copy()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false);

        return $diff < 0 ? abs($diff) : null;
    }

    private function combineDateTime(CarbonInterface $date, ?CarbonInterface $time): CarbonInterface
    {
        $base = $date->copy()->startOfDay();

        if ($time !== null) {
            $base = $base->setTime(
                (int) $time->format('H'),
                (int) $time->format('i'),
                (int) $time->format('s'),
            );
        }

        return $base;
    }
}
