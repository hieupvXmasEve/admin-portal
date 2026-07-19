<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Enums\StudentActionType;
use App\Models\StudentActionLog;
use App\Modules\Academic\Progression\DTO\ProgramEnrollmentTransition;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use Illuminate\Support\Facades\DB;

final class TransitionProgramEnrollmentAction
{
    /**
     * @param  array{student_id: int, action_type: string, defer_scope_type?: string, egc_defer_from_block_number?: int|string|null, from_semester_id?: int|null, return_semester_id?: int|null}  $data
     */
    public static function run(array $data): ProgramEnrollmentTransition
    {
        return DB::transaction(function () use ($data): ProgramEnrollmentTransition {
            $studentId = (int) $data['student_id'];
            $actionType = StudentActionType::from($data['action_type']);
            $enrollment = self::lockedEnrollment($studentId);
            $previousStatus = self::legacyStatus($enrollment);
            $previousStudyStage = $enrollment->study_stage;

            self::validateTransition($previousStatus, $previousStudyStage, $actionType, $data);

            if (! self::changesLifecycle($actionType, $data)) {
                return new ProgramEnrollmentTransition(
                    previousStatus: $previousStatus,
                    newStatus: $previousStatus,
                    previousStudyStage: $previousStudyStage,
                    newStudyStage: $previousStudyStage,
                    changed: false,
                );
            }

            $targetStatus = $actionType === StudentActionType::ACADEMIC_RESUME
                ? self::resumeStudyStage($studentId, $previousStudyStage)
                : $actionType->targetStatus();

            if ($targetStatus === null) {
                return new ProgramEnrollmentTransition(
                    previousStatus: $previousStatus,
                    newStatus: $previousStatus,
                    previousStudyStage: $previousStudyStage,
                    newStudyStage: $previousStudyStage,
                    changed: false,
                );
            }

            $changes = self::lifecycleChanges($targetStatus);
            if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT) {
                $changes['intake_major_semester_id'] = isset($data['from_semester_id'])
                    ? (int) $data['from_semester_id']
                    : null;
            }

            $enrollment->update($changes);

            return new ProgramEnrollmentTransition(
                previousStatus: $previousStatus,
                newStatus: $targetStatus,
                previousStudyStage: $previousStudyStage,
                newStudyStage: $enrollment->fresh()->study_stage,
                changed: true,
            );
        });
    }

    private static function lockedEnrollment(int $studentId): ProgramEnrollment
    {
        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $studentId)
            ->where('is_primary', true)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($enrollment === null) {
            MaterializeProgramEnrollmentAction::run(['student_id' => $studentId]);

            $enrollment = ProgramEnrollment::query()
                ->where('student_id', $studentId)
                ->where('is_primary', true)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $enrollment;
    }

    private static function legacyStatus(ProgramEnrollment $enrollment): string
    {
        if ($enrollment->enrollment_status === 'active') {
            return $enrollment->study_stage ?? 'active';
        }

        return match ($enrollment->enrollment_status) {
            'withdrawn' => 'dropout',
            default => $enrollment->enrollment_status,
        };
    }

    /** @param array<string, mixed> $data */
    private static function validateTransition(
        string $currentStatus,
        ?string $studyStage,
        StudentActionType $actionType,
        array $data,
    ): void {
        if (in_array($currentStatus, ['dropout', 'dropout_transfer', 'graduated'], true)) {
            throw new InvalidProgressionState('action_type', sprintf(
                'Student status "%s" is terminal. No further status actions are allowed.',
                $currentStatus,
            ));
        }

        if ($currentStatus === 'deferred'
            && ! in_array($actionType, [StudentActionType::ACADEMIC_RESUME, StudentActionType::ACADEMIC_DEFER], true)) {
            throw new InvalidProgressionState('action_type', 'When status is deferred, only "Quay lại học" or "Bảo lưu tiếp" are allowed on this page.');
        }

        if ($currentStatus === 'pending_course_opening'
            && ! in_array($actionType, [StudentActionType::ACADEMIC_RESUME, StudentActionType::ACADEMIC_DEFER, StudentActionType::ACADEMIC_DROPOUT], true)) {
            throw new InvalidProgressionState('action_type', 'From "Chờ mở môn", only resume to prior stage, additional defer, or dropout are allowed.');
        }

        if ($actionType === StudentActionType::WAITING_COURSE_OPENING
            && ! in_array($currentStatus, ['intake_pre_uni_gc', 'intake_course'], true)) {
            throw new InvalidProgressionState('action_type', 'WAITING_COURSE_OPENING is only allowed when current status is intake_pre_uni_gc or intake_course.');
        }

        if ($actionType === StudentActionType::ACADEMIC_RESUME
            && ! in_array($currentStatus, ['deferred', 'pending_course_opening'], true)) {
            throw new InvalidProgressionState('action_type', 'ACADEMIC_RESUME is only allowed when current status is deferred or pending_course_opening.');
        }

        if ($actionType === StudentActionType::ADMISSION_DEFERRAL && $currentStatus !== 'pending') {
            throw new InvalidProgressionState('action_type', 'ADMISSION_DEFERRAL is only allowed when current status is pending.');
        }

        if ($actionType === StudentActionType::STUDENT_ENROLLMENT_NE && $currentStatus !== 'pending') {
            throw new InvalidProgressionState('action_type', 'STUDENT_ENROLLMENT_NE is only allowed when current status is pending.');
        }

        if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT && $currentStatus !== 'intake_pre_uni_gc') {
            throw new InvalidProgressionState('action_type', 'STUDENT_MAJOR_ENROLLMENT is only allowed when current status is intake_pre_uni_gc.');
        }

        if ($actionType === StudentActionType::ACADEMIC_DEFER) {
            self::validateDefer($studyStage, $data);
        }
    }

    /** @param array<string, mixed> $data */
    private static function validateDefer(?string $studyStage, array $data): void
    {
        $scopeType = $data['defer_scope_type'] ?? 'FULL';
        $block = $data['egc_defer_from_block_number'] ?? null;
        $hasBlock = $block !== null && $block !== '';

        if ($studyStage === 'intake_pre_uni_gc' && $scopeType !== 'FULL') {
            throw new InvalidProgressionState('defer_scope_type', 'EGC defer must be full semester.');
        }

        if ($studyStage === 'intake_pre_uni_gc' && ! $hasBlock) {
            throw new InvalidProgressionState('egc_defer_from_block_number', 'EGC defer from block is required for EGC students.');
        }

        if ($studyStage === 'intake_pre_uni_gc' && ! in_array((int) $block, [1, 2], true)) {
            throw new InvalidProgressionState('egc_defer_from_block_number', 'EGC defer from block must be 1 or 2.');
        }

        if ($studyStage !== 'intake_pre_uni_gc' && $hasBlock) {
            throw new InvalidProgressionState('egc_defer_from_block_number', 'EGC defer from block is only available for EGC students.');
        }

        $fromSemesterId = isset($data['from_semester_id']) ? (int) $data['from_semester_id'] : null;
        $returnSemesterId = isset($data['return_semester_id']) ? (int) $data['return_semester_id'] : null;
        if ($fromSemesterId && $returnSemesterId && $returnSemesterId < $fromSemesterId) {
            throw new InvalidProgressionState('return_semester_id', 'Return semester must be after the from semester.');
        }

        if ($scopeType === 'COURSES') {
            self::validateCourseScope($data, $fromSemesterId);
        }
    }

    /** @param array<string, mixed> $data */
    private static function validateCourseScope(array $data, ?int $semesterId): void
    {
        $studentId = (int) $data['student_id'];
        $registrationIds = array_values(array_unique(array_map(
            'intval',
            $data['defer_course_registration_ids'] ?? [],
        )));

        if ($semesterId === null || $registrationIds === []) {
            throw new InvalidProgressionState('defer_course_registration_ids', 'Course selection is required when scope is COURSES.');
        }

        $alreadyDeferredIds = app(StudentLifecycleFinanceReader::class)
            ->deferredCourseRegistrationIds($studentId, $semesterId);
        if (array_intersect($registrationIds, $alreadyDeferredIds) !== []) {
            throw new InvalidProgressionState('defer_course_registration_ids', 'Course has already been deferred for this semester.');
        }

        $deferableIds = app(StudentLifecycleCourseRegistrationGateway::class)
            ->deferableIds($studentId, $semesterId);
        if (array_diff($registrationIds, $deferableIds) !== []) {
            throw new InvalidProgressionState('defer_course_registration_ids', 'Selected course is not eligible for defer in this semester.');
        }
    }

    /** @param array<string, mixed> $data */
    private static function changesLifecycle(StudentActionType $actionType, array $data): bool
    {
        if (! $actionType->changesStatus()) {
            return false;
        }

        return $actionType !== StudentActionType::ACADEMIC_DEFER
            || ($data['defer_scope_type'] ?? 'FULL') !== 'COURSES';
    }

    private static function resumeStudyStage(int $studentId, ?string $retainedStudyStage): string
    {
        if ($retainedStudyStage !== null && $retainedStudyStage !== 'pending_course_opening') {
            return $retainedStudyStage;
        }

        $validStudyStages = ['intake_pre_uni_gc', 'intake_course', 'intake_major'];
        $logs = StudentActionLog::query()
            ->where('student_id', $studentId)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['previous_status', 'new_status']);

        foreach ($logs as $log) {
            if (in_array($log->previous_status, $validStudyStages, true)) {
                return $log->previous_status;
            }

            if (in_array($log->new_status, $validStudyStages, true)) {
                return $log->new_status;
            }
        }

        throw new InvalidProgressionState('action_type', 'The prior study stage could not be resolved for this resume.');
    }

    /** @return array{enrollment_status: string, study_stage?: string} */
    private static function lifecycleChanges(string $targetStatus): array
    {
        if (in_array($targetStatus, ['intake_pre_uni_gc', 'intake_course', 'intake_major', 'pending_course_opening'], true)) {
            return [
                'enrollment_status' => 'active',
                'study_stage' => $targetStatus,
            ];
        }

        return [
            'enrollment_status' => match ($targetStatus) {
                'deferred', 'admission_deferred' => 'deferred',
                'dropout', 'dropout_transfer' => 'withdrawn',
                'graduated' => 'graduated',
                'pending' => 'pending',
                default => 'active',
            },
        ];
    }
}
