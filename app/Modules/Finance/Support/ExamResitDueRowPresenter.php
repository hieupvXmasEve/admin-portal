<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\ExamResitAttempt;

/**
 * Build the exam-resit (thi lại / PTL) row context for the Finance Due Reminders
 * worklist (ACAD-RET-002): the scheduled-sitting metadata, derived due state, and
 * reminder eligibility shared between enriched DNG rows and stand-alone handoff
 * rows.
 */
final class ExamResitDueRowPresenter
{
    public const SOURCE_EXAM_RESIT = 'exam_resit';

    public const FEE_TYPE_PTL = 'PTL';

    /**
     * Fields merged onto a DNG worklist row when it collects an exam-resit charge.
     *
     * @return array<string, mixed>
     */
    public static function examResitContext(ExamResitAttempt $attempt, ExamResitDueClassification $classification): array
    {
        return array_merge(
            [
                'source_type' => self::SOURCE_EXAM_RESIT,
                'source_id' => $attempt->id,
                'fee_type' => self::FEE_TYPE_PTL,
                'unpaid_allowed_reason' => $attempt->unpaid_allowed_reason,
                'reminder_state' => $classification->reminderState,
                'blocked_reason' => $classification->blockedReason,
                'due_at' => $classification->dueAt?->toIso8601String(),
                'days_overdue' => $classification->daysOverdue,
            ],
            self::unitContext($attempt),
            self::sittingContext($attempt),
        );
    }

    /**
     * A stand-alone worklist row for an overdue exam-resit source that still
     * needs charge/DNG creation (not directly remindable).
     *
     * @return array<string, mixed>
     */
    public static function handoffRow(ExamResitAttempt $attempt, ExamResitDueClassification $classification): array
    {
        $student = $attempt->student;

        return array_merge(
            [
                'id' => $attempt->id,
                'type' => self::SOURCE_EXAM_RESIT,
                'source_type' => self::SOURCE_EXAM_RESIT,
                'source_id' => $attempt->id,
                'fee_type' => self::FEE_TYPE_PTL,
                'student_id' => $attempt->student_id,
                'student_code' => $student?->student_id,
                'student_name' => $student?->full_name,
                'student_email' => $student?->email,
                'student_status_label' => $student?->status_label,
                'student_status_color' => $student?->status_color,
                'amount' => (float) $attempt->fee_amount,
                'hq_fee_status' => $attempt->hq_fee_status,
                'unpaid_allowed_reason' => $attempt->unpaid_allowed_reason,
                'reminder_state' => $classification->reminderState,
                'due_at' => $classification->dueAt?->toIso8601String(),
                'days_overdue' => $classification->daysOverdue,
                'last_reminder_at' => $attempt->last_reminded_at,
                'handoff' => self::handoffTarget(),
            ],
            self::unitContext($attempt),
            self::sittingContext($attempt),
        );
    }

    /**
     * @return array{route_name:string, label:string}
     */
    public static function handoffTarget(): array
    {
        return [
            'route_name' => 'finance.batch-studio.dng',
            'label' => 'Lập yêu cầu thanh toán DNG',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function unitContext(ExamResitAttempt $attempt): array
    {
        $unit = $attempt->unit;

        return [
            'unit_code' => $unit?->code,
            'unit_name' => $unit?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function sittingContext(ExamResitAttempt $attempt): array
    {
        $slot = $attempt->session?->roomSlot;
        $room = $slot?->room;

        return [
            'exam_date' => $slot?->exam_date?->toDateString(),
            'exam_start_time' => $slot?->start_time?->format('H:i'),
            'exam_end_time' => $slot?->end_time?->format('H:i'),
            'room' => $room ? ['name' => $room->name, 'code' => $room->code] : null,
        ];
    }
}
