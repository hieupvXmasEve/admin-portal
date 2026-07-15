<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;
use Carbon\CarbonImmutable;
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
        AcademicExamResitDueData $attempt,
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
    public function deriveDueAt(AcademicExamResitDueData $attempt): ?CarbonInterface
    {
        if ($attempt->payment_deadline !== null) {
            return CarbonImmutable::parse($attempt->payment_deadline);
        }

        if ($attempt->exam_date === null) {
            return null;
        }

        $graceDays = $attempt->late_payment_grace_days_snapshot ?? self::DEFAULT_GRACE_DAYS;

        return $this->combineDateTime($attempt->exam_date, $attempt->exam_start_time)
            ->addDays($graceDays);
    }

    private function resolveHardBlock(AcademicExamResitDueData $attempt): ?string
    {
        if ($this->isPaid($attempt)) {
            return ExamResitDueClassification::BLOCKED_PAID;
        }

        if ($attempt->status === AcademicExamResitDueData::STATUS_CANCELLED
            || $attempt->hq_fee_status === AcademicExamResitDueData::HQ_FEE_CANCELLED) {
            return ExamResitDueClassification::BLOCKED_CANCELLED;
        }

        if (LifecycleDueItemPredicate::isLifecycleExceptionStatus($attempt->student_status)) {
            return ExamResitDueClassification::BLOCKED_LIFECYCLE_EXCEPTION;
        }

        return null;
    }

    private function isPaid(AcademicExamResitDueData $attempt): bool
    {
        if ($attempt->hq_fee_status === AcademicExamResitDueData::HQ_FEE_PAID) {
            return true;
        }

        $charge = FinanceCharge::query()
            ->where('finance_obligation_id', FinanceObligation::query()
                ->where('source_system', AcademicFinanceSourceKeys::SOURCE_SYSTEM)
                ->where('source_kind', AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT)
                ->where('source_ref', AcademicFinanceSourceKeys::examResitAttemptRef($attempt->id))
                ->value('id'))
            ->first();

        return $charge !== null
            && $charge->status === FinanceCharge::STATUS_ACTIVE
            && $charge->is_fully_paid;
    }

    private function hasStudentEmail(AcademicExamResitDueData $attempt): bool
    {
        $email = $attempt->student_email;

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

    private function combineDateTime(string $date, ?string $time): CarbonInterface
    {
        $base = CarbonImmutable::parse($date)->startOfDay();

        if ($time !== null) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $base = $base->setTime($hour, $minute);
        }

        return $base;
    }
}
