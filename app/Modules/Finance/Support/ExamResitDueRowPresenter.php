<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;

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
    public static function examResitContext(AcademicExamResitDueData $attempt, ExamResitDueClassification $classification): array
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
    public static function handoffRow(AcademicExamResitDueData $attempt, ExamResitDueClassification $classification): array
    {
        return array_merge(
            [
                'id' => $attempt->id,
                'type' => self::SOURCE_EXAM_RESIT,
                'source_type' => self::SOURCE_EXAM_RESIT,
                'source_id' => $attempt->id,
                'fee_type' => self::FEE_TYPE_PTL,
                'student_id' => $attempt->student_id,
                'student_code' => $attempt->student_code,
                'student_name' => $attempt->student_name,
                'student_email' => $attempt->student_email,
                'student_status_label' => $attempt->student_status_label,
                'student_status_color' => $attempt->student_status_color,
                'amount' => (float) $attempt->amount,
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
    private static function unitContext(AcademicExamResitDueData $attempt): array
    {
        return [
            'unit_code' => $attempt->unit_code,
            'unit_name' => $attempt->unit_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function sittingContext(AcademicExamResitDueData $attempt): array
    {
        return [
            'exam_date' => $attempt->exam_date,
            'exam_start_time' => $attempt->exam_start_time,
            'exam_end_time' => $attempt->exam_end_time,
            'room' => $attempt->room_name !== null || $attempt->room_code !== null
                ? ['name' => $attempt->room_name, 'code' => $attempt->room_code]
                : null,
        ];
    }
}
