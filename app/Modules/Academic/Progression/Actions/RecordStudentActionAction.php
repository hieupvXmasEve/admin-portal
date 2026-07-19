<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentChange;
use App\Models\StudentDecision;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Shared\Contracts\Finance\DTO\StudentLifecycleDeferData;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordStudentActionAction
{
    /**
     * Record a student administrative action.
     *
     * @param  array  $data  Action data including student_id, action_type, reason, etc.
     * @return StudentActionLog The created action log
     *
     * @throws InvalidProgressionState
     */
    public static function run(array $data): StudentActionLog
    {
        $studentId = $data['student_id'];
        $actionType = StudentActionType::from($data['action_type']);
        $userId = $data['changed_by_user_id'] ?? Auth::id();
        $fromSemesterId = isset($data['from_semester_id']) ? (int) $data['from_semester_id'] : null;

        $student = Student::findOrFail($studentId);
        if ($actionType === StudentActionType::CAMPUS_TRANSFER) {
            self::validateCampusTransferAction($student, $data);
        }

        return DB::transaction(function () use ($student, $actionType, $data, $userId, $fromSemesterId) {
            $transition = TransitionProgramEnrollmentAction::run([
                ...$data,
                'student_id' => (int) $student->id,
                'action_type' => $actionType->value,
            ]);
            $previousStatus = $transition->previousStatus;
            $targetStatus = $transition->newStatus;
            $previousCampusId = $student->campus_id;
            $isEgcDefer = $transition->previousStudyStage === 'intake_pre_uni_gc';

            // 1. Create action log
            $actionLog = StudentActionLog::create([
                'student_id' => $student->id,
                'action_type' => $actionType->value,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'signed_at' => $data['signed_at'] ?? null,
                'decision_number' => $data['decision_number'] ?? null,
                'decision_signed_at' => $data['decision_signed_at'] ?? null,
                'decision_signer' => $data['decision_signer'] ?? null,
                'decision_id' => $data['decision_id'] ?? null,
                'missing_documents' => $data['missing_documents'] ?? false,
                'changed_by_user_id' => $userId,

                // Semester fields
                'from_semester_id' => $data['from_semester_id'] ?? null,
                'return_semester_id' => $data['return_semester_id'] ?? null,
                'egc_defer_from_block_number' => self::resolveEgcDeferBlockNumber($isEgcDefer, $actionType, $data),
                'intended_intake_semester_id' => $data['intended_intake_semester_id'] ?? null,
                'dropout_semester_id' => $data['dropout_semester_id'] ?? null,
                'effective_semester_id' => $data['effective_semester_id'] ?? null,

                // Campus transfer fields
                'from_campus_id' => $data['from_campus_id'] ?? null,
                'to_campus_id' => $data['to_campus_id'] ?? null,
                'effective_at' => $data['effective_at'] ?? null,

                // Snapshot
                'previous_status' => $transition->changed ? $previousStatus : null,
                'new_status' => $transition->changed ? $targetStatus : null,
                'previous_campus_id' => $actionType->changesCampus() ? $previousCampusId : null,
            ]);

            // 1.5 If a decision authorized this transition up front, ensure the
            // decision's coverage roster includes this student (ADR-0008).
            if (! empty($data['decision_id'])) {
                StudentDecision::find($data['decision_id'])?->cover($student->id);
            }

            // 2. Program Enrollment was transitioned above under a row lock.
            // Student Registry identity remains unchanged by lifecycle actions.
            self::updateStudentCampusSnapshot($student, $actionType, $data);

            // 2.5 Record academic progression event for stage transition
            self::recordAcademicProgressionEvent(
                $student,
                $actionType,
                $previousStatus,
                $targetStatus,
                $data,
                $userId,
                $fromSemesterId,
            );

            // 3. Record field-level changes in StudentChange
            self::recordStudentChanges(
                $student,
                $actionType,
                $transition->changed ? $targetStatus : null,
                $previousStatus,
                $previousCampusId,
                $data,
                $userId
            );

            // 4. Link attachments if provided
            if (! empty($data['attachment_ids'])) {
                $actionLog->attachments()->sync($data['attachment_ids']);
            }

            // 5. Create DeferCase for ACADEMIC_DEFER
            if ($actionType === StudentActionType::ACADEMIC_DEFER) {
                app(StudentLifecycleFinanceCommand::class)->applyDefer(new StudentLifecycleDeferData(
                    studentActionLogId: (int) $actionLog->id,
                    studentId: (int) $student->id,
                    semesterId: (int) $data['from_semester_id'],
                    appliesUntilSemesterId: isset($data['return_semester_id'])
                        ? (int) $data['return_semester_id']
                        : null,
                    scopeType: $data['defer_scope_type'] ?? 'FULL',
                    feePolicy: $data['defer_fee_policy'] ?? 'FORFEIT',
                    preserveAmount: isset($data['defer_preserve_amount'])
                        ? (float) $data['defer_preserve_amount']
                        : null,
                    signedAt: isset($data['signed_at']) ? (string) $data['signed_at'] : null,
                    changedByUserId: $userId,
                    courseRegistrationIds: array_values(array_unique(array_map(
                        'intval',
                        $data['defer_course_registration_ids'] ?? [],
                    ))),
                    egcChargeIds: array_values(array_unique(array_map(
                        'intval',
                        $data['defer_egc_charge_ids'] ?? [],
                    ))),
                    isEgcDefer: $isEgcDefer,
                ));
            }

            if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT) {
                app(PublishCourseStageChangedNotificationAction::class)->run(
                    $student,
                    $previousStatus,
                    $targetStatus,
                    (int) $fromSemesterId
                );
            }

            Log::info('Student action recorded', [
                'student_id' => $student->id,
                'action_type' => $actionType->value,
                'action_log_id' => $actionLog->id,
                'target_status' => $targetStatus,
                'changed_by' => $userId,
            ]);

            return $actionLog->load(['student', 'changedBy', 'attachments', 'deferCase', 'decision']);
        });
    }

    private static function resolveEgcDeferBlockNumber(
        bool $isEgcLifecycle,
        StudentActionType $actionType,
        array $data
    ): ?int {
        // Support block number for both classic EGC defer and the new WAITING_COURSE_OPENING action
        if (! in_array($actionType, [StudentActionType::ACADEMIC_DEFER, StudentActionType::WAITING_COURSE_OPENING], true)) {
            return null;
        }

        $block = $data['egc_defer_from_block_number'] ?? null;

        if ($block === null || $block === '') {
            // Default to 1 only for pre-uni context when not provided (backward compat for defer)
            if ($isEgcLifecycle) {
                return 1;
            }

            return null;
        }

        return (int) $block;
    }

    /**
     * Validate CAMPUS_TRANSFER action.
     */
    private static function validateCampusTransferAction(Student $student, array $data): void
    {
        $fromCampusId = $data['from_campus_id'] ?? null;
        $toCampusId = $data['to_campus_id'] ?? null;

        if ($fromCampusId && $toCampusId && $fromCampusId === $toCampusId) {
            throw new InvalidProgressionState('to_campus_id', 'Target campus must be different from current campus.');
        }

        // Validate from_campus_id matches student's current campus
        if ($fromCampusId && $student->campus_id !== (int) $fromCampusId) {
            throw new InvalidProgressionState('from_campus_id', 'From campus does not match student\'s current campus.');
        }
    }

    private static function updateStudentCampusSnapshot(
        Student $student,
        StudentActionType $actionType,
        array $data,
    ): void {
        if ($actionType->changesCampus() && isset($data['to_campus_id'])) {
            $student->update(['campus_id' => $data['to_campus_id']]);
        }
    }

    private static function recordAcademicProgressionEvent(
        Student $student,
        StudentActionType $actionType,
        string $previousStatus,
        string $targetStatus,
        array $data,
        int $userId,
        ?int $fromSemesterId = null
    ): void {
        if ($actionType !== StudentActionType::STUDENT_MAJOR_ENROLLMENT) {
            return;
        }

        AcademicProgressionEvent::create([
            'student_id' => $student->id,
            'event_type' => AcademicProgressionEventType::COURSE_STAGE_CHANGED,
            'semester_id' => $fromSemesterId,
            'effective_at' => now(),
            'trigger_source' => ProgressionTriggerSource::MANUAL_ADMIN,
            'created_by_user_id' => $userId,
            'from_course_stage' => $previousStatus,
            'to_course_stage' => $targetStatus,
            'notes' => $data['notes'] ?? $data['reason'],
        ]);
    }

    /**
     * Record field-level changes in StudentChange table.
     */
    private static function recordStudentChanges(
        Student $student,
        StudentActionType $actionType,
        ?string $targetStatus,
        ?string $previousStatus,
        ?int $previousCampusId,
        array $data,
        int $userId
    ): void {
        $now = now();

        // Record status change
        if (self::shouldUpdateStatus($actionType, $data)) {
            StudentChange::create([
                'student_id' => $student->id,
                'user_id' => $userId,
                'field_name' => 'status',
                'old_value' => $previousStatus,
                'new_value' => $targetStatus,
                'reason' => $data['reason'],
                'changed_at' => $now,
            ]);
        }

        // Record campus change
        if ($actionType->changesCampus()) {
            StudentChange::create([
                'student_id' => $student->id,
                'user_id' => $userId,
                'field_name' => 'campus_id',
                'old_value' => (string) $previousCampusId,
                'new_value' => (string) $student->campus_id,
                'reason' => $data['reason'],
                'changed_at' => $now,
            ]);
        }
    }

    private static function shouldUpdateStatus(StudentActionType $actionType, array $data): bool
    {
        if (! $actionType->changesStatus()) {
            return false;
        }

        if ($actionType === StudentActionType::ACADEMIC_DEFER) {
            $scopeType = $data['defer_scope_type'] ?? 'FULL';

            return $scopeType !== 'COURSES';
        }

        return true;
    }
}
