<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentChange;
use App\Modules\Finance\Services\DeferCaseService;
use App\Modules\Finance\Services\FinanceChargeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecordStudentActionAction
{
    /**
     * Record a student administrative action.
     *
     * @param  array  $data  Action data including student_id, action_type, reason, etc.
     * @return StudentActionLog The created action log
     *
     * @throws ValidationException
     */
    public static function run(array $data): StudentActionLog
    {
        $studentId = $data['student_id'];
        $actionType = StudentActionType::from($data['action_type']);
        $userId = $data['changed_by_user_id'] ?? Auth::id();
        $fromSemesterId = isset($data['from_semester_id']) ? (int) $data['from_semester_id'] : null;

        $student = Student::findOrFail($studentId);

        // Validate action-specific requirements
        self::validateActionRequirements($student, $actionType, $data);

        // Determine target status
        $targetStatus = $actionType->targetStatus();

        // Special handling for ACADEMIC_RESUME: resolve the nearest prior *valid study status* from action history.
        // This fixes consecutive defer issues and supports resume from pending_course_opening (or after defer from waiting).
        // Never returns 'active' (retired), 'pending', 'deferred', or terminal statuses.
        if ($actionType === StudentActionType::ACADEMIC_RESUME) {
            $resolved = self::resolvePriorStudyStatusFromHistory($student);
            if ($resolved) {
                $targetStatus = $resolved;
            }
            // If no history found, fall back to enum (legacy 'active') but policy + UI should prevent bad cases.
        }

        return DB::transaction(function () use ($student, $actionType, $targetStatus, $data, $userId, $fromSemesterId) {
            $previousStatus = $student->status;
            $previousCampusId = $student->campus_id;
            $shouldUpdateStatus = self::shouldUpdateStatus($actionType, $data);
            $isEgcDefer = $previousStatus === 'intake_pre_uni_gc';

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
                'egc_defer_from_block_number' => self::resolveEgcDeferBlockNumber($student, $actionType, $data),
                'intended_intake_semester_id' => $data['intended_intake_semester_id'] ?? null,
                'dropout_semester_id' => $data['dropout_semester_id'] ?? null,
                'effective_semester_id' => $data['effective_semester_id'] ?? null,

                // Campus transfer fields
                'from_campus_id' => $data['from_campus_id'] ?? null,
                'to_campus_id' => $data['to_campus_id'] ?? null,
                'effective_at' => $data['effective_at'] ?? null,

                // Snapshot
                'previous_status' => $shouldUpdateStatus ? $previousStatus : null,
                'new_status' => $shouldUpdateStatus ? ($targetStatus ?? $previousStatus) : null,
                'previous_campus_id' => $actionType->changesCampus() ? $previousCampusId : null,
            ]);

            // 2. Update student snapshot
            self::updateStudentSnapshot($student, $actionType, $targetStatus, $data, $userId, $fromSemesterId);

            // 2.5 Record academic progression event for stage transition
            self::recordAcademicProgressionEvent($student, $actionType, $previousStatus, $data, $userId, $fromSemesterId);

            // 3. Record field-level changes in StudentChange
            self::recordStudentChanges(
                $student,
                $actionType,
                $targetStatus,
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
                $deferCase = self::createDeferCase($actionLog, $student, $data, $userId);

                if ($isEgcDefer) {
                    self::createEgcDeferCredits($deferCase, $student, $data, $userId);
                }
            }

            if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT) {
                app(PublishCourseStageChangedNotificationAction::class)->run(
                    $student,
                    $previousStatus,
                    (string) $student->status,
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

    /**
     * Validate action-specific requirements.
     */
    private static function validateActionRequirements(
        Student $student,
        StudentActionType $actionType,
        array $data
    ): void {
        self::validateStatusTransitionPolicy($student, $actionType);

        match ($actionType) {
            StudentActionType::STUDENT_ENROLLMENT_NE => null, // No special validation
            StudentActionType::STUDENT_MAJOR_ENROLLMENT => null, // No special validation
            StudentActionType::ACADEMIC_DEFER => self::validateDeferAction($student, $data),
            StudentActionType::ACADEMIC_RESUME => self::validateResumeAction($student, $data),
            StudentActionType::ADMISSION_DEFERRAL => null, // No special validation
            StudentActionType::ACADEMIC_DROPOUT => null, // No special validation
            StudentActionType::CAMPUS_TRANSFER => self::validateCampusTransferAction($student, $data),
        };
    }

    /**
     * Validate status transition policy before action-specific validation.
     * Enforces the rules for pending_course_opening, no-revert-to-pending, deferred restrictions,
     * and prevents using this page for pre-uni <-> course stage changes (handled elsewhere).
     */
    private static function validateStatusTransitionPolicy(Student $student, StudentActionType $actionType): void
    {
        $currentStatus = (string) $student->status;

        $terminalStatuses = ['dropout', 'dropout_transfer', 'graduated'];
        if (in_array($currentStatus, $terminalStatuses, true)) {
            throw ValidationException::withMessages([
                'action_type' => sprintf(
                    'Student status "%s" is terminal. No further status actions are allowed.',
                    $currentStatus
                ),
            ]);
        }

        // No action may ever target 'pending' (pending is only initial source for NE/admission deferral)
        $intendedTarget = $actionType->targetStatus();
        if ($intendedTarget === 'pending') {
            throw ValidationException::withMessages([
                'action_type' => 'No action may transition a student to "pending".',
            ]);
        }

        // Deferred students: only resume (Quay lại học) or additional defer (Bảo lưu tiếp)
        if ($currentStatus === 'deferred') {
            if (! in_array($actionType, [StudentActionType::ACADEMIC_RESUME, StudentActionType::ACADEMIC_DEFER], true)) {
                throw ValidationException::withMessages([
                    'action_type' => 'When status is deferred, only "Quay lại học" or "Bảo lưu tiếp" are allowed on this page.',
                ]);
            }
        }

        // pending_course_opening: resume (to prior study status), defer, or dropout. No direct waiting again.
        if ($currentStatus === 'pending_course_opening') {
            $allowed = [StudentActionType::ACADEMIC_RESUME, StudentActionType::ACADEMIC_DEFER, StudentActionType::ACADEMIC_DROPOUT];
            if (! in_array($actionType, $allowed, true)) {
                throw ValidationException::withMessages([
                    'action_type' => 'From "Chờ mở môn", only resume to prior stage, additional defer, or dropout are allowed.',
                ]);
            }
        }

        // WAITING_COURSE_OPENING only from active study stages (pre-uni or course). Not from pending or deferred.
        if ($actionType === StudentActionType::WAITING_COURSE_OPENING && ! in_array($currentStatus, ['intake_pre_uni_gc', 'intake_course'], true)) {
            throw ValidationException::withMessages([
                'action_type' => 'WAITING_COURSE_OPENING is only allowed when current status is intake_pre_uni_gc or intake_course.',
            ]);
        }

        // Resume allowed from deferred or from pending_course_opening (history will resolve correct prior study status)
        if ($actionType === StudentActionType::ACADEMIC_RESUME && ! in_array($currentStatus, ['deferred', 'pending_course_opening'], true)) {
            throw ValidationException::withMessages([
                'action_type' => 'ACADEMIC_RESUME is only allowed when current status is deferred or pending_course_opening.',
            ]);
        }

        if ($actionType === StudentActionType::ADMISSION_DEFERRAL && $currentStatus !== 'pending') {
            throw ValidationException::withMessages([
                'action_type' => 'ADMISSION_DEFERRAL is only allowed when current status is pending.',
            ]);
        }

        if ($actionType === StudentActionType::STUDENT_ENROLLMENT_NE && $currentStatus !== 'pending') {
            throw ValidationException::withMessages([
                'action_type' => 'STUDENT_ENROLLMENT_NE is only allowed when current status is pending.',
            ]);
        }

        if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT && $currentStatus !== 'intake_pre_uni_gc') {
            throw ValidationException::withMessages([
                'action_type' => 'STUDENT_MAJOR_ENROLLMENT is only allowed when current status is intake_pre_uni_gc.',
            ]);
        }
    }

    /**
     * Validate ACADEMIC_DEFER action.
     */
    private static function validateDeferAction(Student $student, array $data): void
    {
        $fromSemesterId = $data['from_semester_id'] ?? null;
        $returnSemesterId = $data['return_semester_id'] ?? null;
        $scopeType = $data['defer_scope_type'] ?? 'FULL';

        if ($student->status === 'intake_pre_uni_gc' && $scopeType !== 'FULL') {
            throw ValidationException::withMessages([
                'defer_scope_type' => 'EGC defer must be full semester.',
            ]);
        }

        $egcDeferBlockNumber = $data['egc_defer_from_block_number'] ?? null;
        $hasEgcDeferBlockNumber = $egcDeferBlockNumber !== null && $egcDeferBlockNumber !== '';

        if ($student->status === 'intake_pre_uni_gc' && ! $hasEgcDeferBlockNumber) {
            throw ValidationException::withMessages([
                'egc_defer_from_block_number' => 'EGC defer from block is required for EGC students.',
            ]);
        }

        if ($student->status === 'intake_pre_uni_gc' && ! in_array((int) $egcDeferBlockNumber, [1, 2], true)) {
            throw ValidationException::withMessages([
                'egc_defer_from_block_number' => 'EGC defer from block must be 1 or 2.',
            ]);
        }

        if ($student->status !== 'intake_pre_uni_gc' && $hasEgcDeferBlockNumber) {
            throw ValidationException::withMessages([
                'egc_defer_from_block_number' => 'EGC defer from block is only available for EGC students.',
            ]);
        }

        if ($fromSemesterId && $returnSemesterId && $returnSemesterId < $fromSemesterId) {
            throw ValidationException::withMessages([
                'return_semester_id' => 'Return semester must be after the from semester.',
            ]);
        }

        // if ($fromSemesterId && $returnSemesterId && $fromSemesterId === $returnSemesterId && $scopeType !== 'COURSES') {
        //     throw ValidationException::withMessages([
        //         'return_semester_id' => 'Return semester must be different from from semester.',
        //     ]);
        // }
    }

    private static function resolveEgcDeferBlockNumber(
        Student $student,
        StudentActionType $actionType,
        array $data
    ): ?int {
        if ($actionType !== StudentActionType::ACADEMIC_DEFER || $student->status !== 'intake_pre_uni_gc') {
            return null;
        }

        return (int) ($data['egc_defer_from_block_number'] ?? 1);
    }

    /**
     * Validate ACADEMIC_RESUME action.
     */
    private static function validateResumeAction(Student $student, array $data): void
    {
        // Allow resume from deferred (classic) or pending_course_opening (continue after waiting).
        // The history resolver will pick the correct prior study status.
        if (! in_array((string) $student->status, ['deferred', 'pending_course_opening'], true)) {
            Log::warning('Student resuming but not in deferred or pending_course_opening status', [
                'student_id' => $student->id,
                'current_status' => $student->status,
            ]);
        }
    }

    /**
     * Validate CAMPUS_TRANSFER action.
     */
    private static function validateCampusTransferAction(Student $student, array $data): void
    {
        $fromCampusId = $data['from_campus_id'] ?? null;
        $toCampusId = $data['to_campus_id'] ?? null;

        if ($fromCampusId && $toCampusId && $fromCampusId === $toCampusId) {
            throw ValidationException::withMessages([
                'to_campus_id' => 'Target campus must be different from current campus.',
            ]);
        }

        // Validate from_campus_id matches student's current campus
        if ($fromCampusId && $student->campus_id !== (int) $fromCampusId) {
            throw ValidationException::withMessages([
                'from_campus_id' => 'From campus does not match student\'s current campus.',
            ]);
        }
    }

    /**
     * Update student's snapshot (status, campus) based on action type.
     */
    private static function updateStudentSnapshot(
        Student $student,
        StudentActionType $actionType,
        ?string $targetStatus,
        array $data,
        int $userId,
        ?int $fromSemesterId = null
    ): void {
        $updateData = [];

        // Update status if action changes it
        if (self::shouldUpdateStatus($actionType, $data)) {
            $updateData['status'] = $targetStatus ?? $actionType->targetStatus();
            $updateData['status_change_date'] = now()->toDateString();
            $updateData['status_reason'] = $data['reason'];
            $updateData['status_changed_by'] = $userId;

            if ($actionType === StudentActionType::STUDENT_MAJOR_ENROLLMENT) {
                $updateData['intake_major'] = $fromSemesterId;
            }
        }

        // Update campus if action changes it
        if ($actionType->changesCampus() && isset($data['to_campus_id'])) {
            $updateData['campus_id'] = $data['to_campus_id'];
        }

        if (! empty($updateData)) {
            $student->update($updateData);
        }
    }

    private static function recordAcademicProgressionEvent(
        Student $student,
        StudentActionType $actionType,
        string $previousStatus,
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
            'to_course_stage' => (string) $student->status,
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
                'new_value' => $targetStatus ?? $actionType->targetStatus(),
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

    /**
     * Create a DeferCase record linked to the action log.
     */
    private static function createDeferCase(
        StudentActionLog $actionLog,
        Student $student,
        array $data,
        int $userId
    ): DeferCase {
        $deferCaseService = app(DeferCaseService::class);

        // Create the defer case
        $deferCase = $deferCaseService->createDeferCase($actionLog, [
            'student_id' => $student->id,
            'semester_id' => $data['from_semester_id'],
            'applies_until_semester_id' => $data['return_semester_id'] ?? null,
            'scope_type' => $data['defer_scope_type'] ?? 'FULL',
            'fee_policy' => $data['defer_fee_policy'] ?? 'FORFEIT',
            'preserve_amount' => $data['defer_preserve_amount'] ?? null,
            'signed_at' => $data['signed_at'] ?? null,
            'changed_by_user_id' => $userId,
        ]);

        // Add course items if COURSES scope
        if (($data['defer_scope_type'] ?? 'FULL') === 'COURSES'
            && ! empty($data['defer_course_registration_ids'])) {
            $items = array_map(fn ($regId) => [
                'course_registration_id' => $regId,
                'fee_policy' => $data['defer_fee_policy'] ?? 'FORFEIT',
            ], $data['defer_course_registration_ids']);

            $deferCaseService->addDeferCaseItems($deferCase, $items);
        }

        // Process fee policy (auto-creates DEFER_CREDIT charges if PRESERVE/PARTIAL)
        $deferCaseService->processFeePolicy($deferCase);

        Log::info('DeferCase created for student action', [
            'action_log_id' => $actionLog->id,
            'defer_case_id' => $deferCase->id,
            'scope_type' => $deferCase->scope_type,
            'fee_policy' => $deferCase->fee_policy,
        ]);

        return $deferCase;
    }

    private static function createEgcDeferCredits(
        DeferCase $deferCase,
        Student $student,
        array $data,
        int $userId
    ): void {
        $chargeIds = array_values(array_unique($data['defer_egc_charge_ids'] ?? []));
        if (empty($chargeIds)) {
            return;
        }

        $charges = FinanceCharge::query()
            ->whereIn('id', $chargeIds)
            ->where('student_id', $student->id)
            ->where('semester_id', $data['from_semester_id'] ?? null)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->get();

        if ($charges->isEmpty()) {
            return;
        }

        $chargeService = app(FinanceChargeService::class);

        foreach ($charges as $charge) {
            $exists = FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                ->where('source_type', FinanceCharge::class)
                ->where('source_id', $charge->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $chargeService->createCharge([
                'student_id' => $student->id,
                'semester_id' => $charge->semester_id,
                'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
                'amount' => -abs((float) $charge->amount),
                'description' => 'EGC Defer Credit: '.($charge->description ?? 'EGC Level Fee'),
                'effective_at' => $deferCase->effective_at ?? now(),
                'source_type' => FinanceCharge::class,
                'source_id' => $charge->id,
                'created_by_user_id' => $userId,
            ]);
        }
    }

    /**
     * Walk action history backwards to find the most recent prior valid study status.
     * Used by ACADEMIC_RESUME (Quay lại học) and continue from waiting.
     * Allowed study statuses: intake_pre_uni_gc, intake_course, pending_course_opening.
     * Explicitly excludes: active (retired), pending, deferred, dropout*, graduated, admission_deferred.
     */
    private static function resolvePriorStudyStatusFromHistory(Student $student): ?string
    {
        $allowedStudyStatuses = ['intake_pre_uni_gc', 'intake_course', 'pending_course_opening'];

        $recentLogs = StudentActionLog::query()
            ->where('student_id', $student->id)
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get(['previous_status', 'new_status']);

        foreach ($recentLogs as $log) {
            if ($log->previous_status && in_array($log->previous_status, $allowedStudyStatuses, true)) {
                return $log->previous_status;
            }
            if ($log->new_status && in_array($log->new_status, $allowedStudyStatuses, true)) {
                return $log->new_status;
            }
        }

        // Last resort: if the student's current status is already a valid study one (unusual for resume)
        $current = (string) $student->status;
        if (in_array($current, $allowedStudyStatuses, true)) {
            return $current;
        }

        return null;
    }
}
