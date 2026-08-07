<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\StudentWarningLog;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use Carbon\CarbonImmutable;

final class AcademicLifecycleEventFactory
{
    public static function courseCompleted(
        Student $student,
        CourseOffering $courseOffering,
        string $grade,
        float $finalPercentage,
        float $creditPoints,
        bool $passed,
    ): DomainEvent {
        $body = $passed
            ? "Congratulations! You have successfully completed {$courseOffering->unit->name} with grade {$grade} ({$finalPercentage}%). You earned {$creditPoints} credit points."
            : "You have completed {$courseOffering->unit->name} with grade {$grade} ({$finalPercentage}%). Unfortunately, you did not meet the passing threshold.";

        return self::event(
            name: 'academic.course_completed',
            deduplicationKey: implode(':', ['academic.course_completed', 'course_offering', $courseOffering->id, 'student', $student->id, 'grade', $grade, 'score', self::decimal($finalPercentage), 'passed', (int) $passed]),
            student: $student,
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            data: [
                'title' => $passed
                    ? "Course Completed: {$courseOffering->unit->code}"
                    : "Course Completed (Not Passed): {$courseOffering->unit->code}",
                'body' => $body,
                'category' => 'academic',
                'is_important' => ! $passed,
                'action_url' => '',
                'action_text' => 'View Academic Records',
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'grade' => $grade,
                'final_percentage' => $finalPercentage,
                'credit_points' => $creditPoints,
                'passed' => $passed,
            ],
        );
    }

    public static function courseScoreUpdated(
        Student $student,
        CourseOffering $courseOffering,
        float $oldPercentage,
        float $newPercentage,
        string $grade,
    ): DomainEvent {
        return self::event(
            name: 'academic.course_score_updated',
            deduplicationKey: implode(':', ['academic.course_score_updated', 'course_offering', $courseOffering->id, 'student', $student->id, 'from', self::decimal($oldPercentage), 'to', self::decimal($newPercentage), 'grade', $grade]),
            student: $student,
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            data: [
                'title' => "Score Updated: {$courseOffering->unit->code}",
                'body' => "Your score for {$courseOffering->unit->name} was updated from {$oldPercentage}% to {$newPercentage}% (grade {$grade}).",
                'category' => 'academic',
                'is_important' => false,
                'action_url' => '',
                'action_text' => 'View Academic Records',
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'grade' => $grade,
                'old_final_percentage' => $oldPercentage,
                'new_final_percentage' => $newPercentage,
            ],
        );
    }

    public static function egcCourseCompleted(
        Student $student,
        CourseOffering $courseOffering,
        string $grade,
        bool $passed,
        bool $levelProgressed,
        int $currentLevel,
        string $message,
    ): DomainEvent {
        $body = $passed
            ? "You passed {$courseOffering->unit->code} - {$courseOffering->unit->name} with grade {$grade}. {$message}"
            : "You did not pass {$courseOffering->unit->code} - {$courseOffering->unit->name}. Grade: {$grade}. {$message}";

        return self::event(
            name: 'academic.egc_course_completed',
            deduplicationKey: implode(':', ['academic.egc_course_completed', 'course_offering', $courseOffering->id, 'student', $student->id, 'grade', $grade, 'passed', (int) $passed, 'level', $currentLevel, 'progressed', (int) $levelProgressed]),
            student: $student,
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            data: [
                'title' => $passed
                    ? "EGC Course Completed: {$courseOffering->unit->code}"
                    : "EGC Course Result: {$courseOffering->unit->code}",
                'body' => $body,
                'category' => 'academic',
                'is_important' => true,
                'action_url' => '',
                'action_text' => 'View Academic Records',
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'grade' => $grade,
                'passed' => $passed,
                'level_progressed' => $levelProgressed,
                'current_level' => $currentLevel,
            ],
        );
    }

    public static function egcProgramCompleted(Student $student, int $totalLevels, string $newStatus): DomainEvent
    {
        return self::event(
            name: 'academic.egc_program_completed',
            deduplicationKey: implode(':', ['academic.egc_program_completed', 'student', $student->id, 'levels', $totalLevels, 'status', $newStatus]),
            student: $student,
            aggregateType: 'student',
            aggregateId: (string) $student->id,
            data: [
                'title' => 'EGC Program Completed',
                'body' => "Congratulations! You have completed all {$totalLevels} EGC levels. Please contact academic services for your next status transition.",
                'category' => 'academic',
                'is_important' => true,
                'action_url' => '',
                'action_text' => 'View Academic Records',
                'total_levels' => $totalLevels,
                'new_status' => $newStatus,
            ],
        );
    }

    public static function courseStageChanged(
        Student $student,
        string $fromStage,
        string $toStage,
        int $semesterId,
        string $semesterLabel,
        string $body,
    ): DomainEvent {
        return self::event(
            name: 'academic.course_stage_changed',
            deduplicationKey: implode(':', ['academic.course_stage_changed', 'student', $student->id, 'semester', $semesterId, 'from', $fromStage, 'to', $toStage]),
            student: $student,
            aggregateType: 'student',
            aggregateId: (string) $student->id,
            data: [
                'title' => $toStage === 'intake_course'
                    ? 'Major Admission Confirmed'
                    : 'Academic Stage Updated',
                'body' => $body,
                'category' => 'academic',
                'is_important' => true,
                'action_url' => '',
                'action_text' => 'View Academic Records',
                'from_course_stage' => $fromStage,
                'to_course_stage' => $toStage,
                'semester_id' => $semesterId,
                'semester_code' => $semesterLabel,
            ],
        );
    }

    /**
     * Student must acknowledge the interview minutes before a money decision
     * can be made, so the deduplication key includes the minutes version — a
     * re-issued request after edited minutes is a genuinely new notification.
     */
    public static function scholarshipAdjustmentConfirmationRequested(
        Student $student,
        int $dossierId,
        int $minutesVersion,
        ?string $targetSemesterName,
        ?CarbonImmutable $requestedAt = null,
    ): DomainEvent {
        $semester = $targetSemesterName !== null && $targetSemesterName !== ''
            ? " for {$targetSemesterName}"
            : '';

        // Mirrors MarkScholarshipConfirmationsOverdue: a pending confirmation
        // goes overdue one calendar day after it was requested.
        $deadline = ($requestedAt ?? CarbonImmutable::now())->addDay();

        return self::event(
            name: 'academic.scholarship_adjustment_confirmation_requested',
            deduplicationKey: implode(':', [
                'academic.scholarship_adjustment_confirmation_requested',
                'dossier', $dossierId,
                'student', $student->id,
                'minutes_version', $minutesVersion,
            ]),
            student: $student,
            aggregateType: 'scholarship_adjustment_dossier',
            aggregateId: (string) $dossierId,
            data: [
                'title' => 'Confirm your scholarship review',
                'body' => "Please read the interview notes and confirm whether you agree. Your scholarship{$semester} cannot be decided until you respond.",
                'category' => 'academic',
                'is_important' => true,
                // Literal, not an action_type: this event reaches the student
                // through EventIntentMapper, which passes `data` straight
                // through and never runs NotificationPayloadBuilder — so a
                // NotificationUrlRegistry entry would resolve for nobody.
                'action_url' => "/scholarship-review/{$dossierId}",
                'action_text' => 'Review and confirm',
                'dossier_id' => $dossierId,
                'minutes_version' => $minutesVersion,
                // Consumed by the admin-editable email template
                // (NotificationTemplateTypeKey::ScholarshipAdjustmentConfirmationRequested).
                'student_name' => (string) $student->full_name,
                'student_code' => (string) $student->student_id,
                'semester_code' => $targetSemesterName ?? '',
                'deadline' => $deadline->format('d/m/Y H:i'),
            ],
            // Unlike other academic events this one asks the student to act
            // within a day, so it must leave the app as well as sit in the bell.
            channels: ['email', 'realtime'],
        );
    }

    /**
     * Batch-studio skipped tuition_term generation because a scholarship-
     * adjustment dossier is still in flight (timing invariant — see plan
     * skip-tuition-generation-pending-scholarship-review). Dedup key includes
     * minutes_version: a re-run of the same unresolved dossier is a no-op,
     * but edited minutes make it a legitimately new notice.
     */
    public static function scholarshipAdjustmentTuitionDeferred(
        Student $student,
        int $dossierId,
        int $minutesVersion,
        ?string $targetSemesterName,
        ?string $scholarshipName,
    ): DomainEvent {
        $semester = $targetSemesterName !== null && $targetSemesterName !== ''
            ? " for {$targetSemesterName}"
            : '';
        $scholarship = $scholarshipName !== null && $scholarshipName !== ''
            ? $scholarshipName
            : 'your scholarship';

        return self::event(
            name: 'academic.scholarship_adjustment_tuition_deferred',
            deduplicationKey: implode(':', [
                'academic.scholarship_adjustment_tuition_deferred',
                'dossier', $dossierId,
                'student', $student->id,
                'minutes_version', $minutesVersion,
            ]),
            student: $student,
            aggregateType: 'scholarship_adjustment_dossier',
            aggregateId: (string) $dossierId,
            data: [
                'title' => 'Tuition on hold pending scholarship review',
                'body' => "Your tuition{$semester} is on hold while {$scholarship} is under review. Nothing is owed yet — no decision has been made.",
                'category' => 'academic',
                'is_important' => true,
                'action_url' => "/scholarship-review/{$dossierId}",
                'action_text' => 'View review status',
                'dossier_id' => $dossierId,
                'minutes_version' => $minutesVersion,
                // Consumed by the admin-editable email template
                // (NotificationTemplateTypeKey::ScholarshipAdjustmentTuitionDeferred).
                'student_name' => (string) $student->full_name,
                'student_code' => (string) $student->student_id,
                'semester_code' => $targetSemesterName ?? '',
                'scholarship_name' => $scholarshipName ?? '',
                'deferral_reason' => 'Đang chờ xét duyệt điều chỉnh học bổng cho học kỳ này.',
            ],
            channels: ['email', 'realtime'],
        );
    }

    /**
     * @param  array<int, string>  $requestedChannels
     * @param  array<string, mixed>  $data
     */
    public static function warningSent(StudentWarningLog $log, Student $student, array $requestedChannels, array $data): DomainEvent
    {
        return new DomainEvent(
            name: 'academic.warning_sent',
            deduplicationKey: 'academic.warning_sent:'.$log->dedupe_key,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'student_warning_log',
            aggregateId: (string) $log->id,
            campusId: $log->campus_id ?? $student->campus_id,
            actorUserId: $log->actor_user_id,
            payload: [
                'student_id' => (int) $student->id,
                'warning_type' => $log->warning_type,
                'requested_channels' => $requestedChannels,
                'data' => [
                    ...$data,
                    'title' => $log->message_title,
                    'body' => $log->message_body,
                    'warning_log_id' => (int) $log->id,
                    'warning_type' => $log->warning_type,
                    'category' => str_starts_with($log->warning_type, 'attendance') ? 'attendance' : 'academic',
                    'is_important' => true,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $channels  Overrides EventIntentMapper's
     *                                             academic default of realtime-only.
     */
    private static function event(
        string $name,
        string $deduplicationKey,
        Student $student,
        string $aggregateType,
        string $aggregateId,
        array $data,
        ?array $channels = null,
    ): DomainEvent {
        return new DomainEvent(
            name: $name,
            deduplicationKey: $deduplicationKey,
            occurredAt: CarbonImmutable::now(),
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: array_filter([
                'student_id' => (int) $student->id,
                'data' => $data,
                'channels' => $channels,
            ], fn ($value) => $value !== null),
        );
    }

    private static function decimal(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
