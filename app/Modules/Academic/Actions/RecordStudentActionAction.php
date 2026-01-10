<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Enums\StudentActionType;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentChange;
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

        $student = Student::findOrFail($studentId);

        // Validate action-specific requirements
        self::validateActionRequirements($student, $actionType, $data);

        // Determine target status
        $targetStatus = $actionType->targetStatus();

        // Special handling for ACADEMIC_RESUME: Restore previous status from deferral
        if ($actionType === StudentActionType::ACADEMIC_RESUME) {
            $lastDeferral = StudentActionLog::query()
                ->where('student_id', $student->id)
                ->where('action_type', StudentActionType::ACADEMIC_DEFER)
                ->latest('id')
                ->first();

            if ($lastDeferral && $lastDeferral->previous_status) {
                $targetStatus = $lastDeferral->previous_status;
            }
        }

        return DB::transaction(function () use ($student, $actionType, $targetStatus, $data, $userId) {
            $previousStatus = $student->status;
            $previousCampusId = $student->campus_id;

            // 1. Create action log
            $actionLog = StudentActionLog::create([
                'student_id' => $student->id,
                'action_type' => $actionType->value,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'signed_at' => $data['signed_at'] ?? null,
                'missing_documents' => $data['missing_documents'] ?? false,
                'changed_by_user_id' => $userId,

                // Semester fields
                'from_semester_id' => $data['from_semester_id'] ?? null,
                'return_semester_id' => $data['return_semester_id'] ?? null,
                'intended_intake_semester_id' => $data['intended_intake_semester_id'] ?? null,
                'dropout_semester_id' => $data['dropout_semester_id'] ?? null,
                'effective_semester_id' => $data['effective_semester_id'] ?? null,

                // Campus transfer fields
                'from_campus_id' => $data['from_campus_id'] ?? null,
                'to_campus_id' => $data['to_campus_id'] ?? null,
                'effective_at' => $data['effective_at'] ?? null,

                // Snapshot
                'previous_status' => $previousStatus,
                'new_status' => $targetStatus ?? $previousStatus,
                'previous_campus_id' => $actionType->changesCampus() ? $previousCampusId : null,
            ]);

            // 2. Update student snapshot
            self::updateStudentSnapshot($student, $actionType, $targetStatus, $data, $userId);

            // 3. Record field-level changes in StudentChange
            self::recordStudentChanges(
                $student,
                $actionType,
                $targetStatus,
                $previousStatus,
                $previousCampusId,
                $data['reason'],
                $userId
            );

            // 4. Link attachments if provided
            if (! empty($data['attachment_ids'])) {
                $actionLog->attachments()->sync($data['attachment_ids']);
            }

            Log::info("Student action recorded", [
                'student_id' => $student->id,
                'action_type' => $actionType->value,
                'action_log_id' => $actionLog->id,
                'target_status' => $targetStatus,
                'changed_by' => $userId,
            ]);

            return $actionLog->load(['student', 'changedBy', 'attachments']);
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
        match ($actionType) {
            StudentActionType::ACADEMIC_DEFER => self::validateDeferAction($data),
            StudentActionType::ACADEMIC_RESUME => self::validateResumeAction($student, $data),
            StudentActionType::ADMISSION_DEFERRAL => null, // No special validation
            StudentActionType::ACADEMIC_DROPOUT => null, // No special validation
            StudentActionType::CAMPUS_TRANSFER => self::validateCampusTransferAction($student, $data),
        };
    }

    /**
     * Validate ACADEMIC_DEFER action.
     */
    private static function validateDeferAction(array $data): void
    {
        $fromSemesterId = $data['from_semester_id'] ?? null;
        $returnSemesterId = $data['return_semester_id'] ?? null;

        if ($fromSemesterId && $returnSemesterId && $fromSemesterId >= $returnSemesterId) {
            throw ValidationException::withMessages([
                'return_semester_id' => 'Return semester must be after the from semester.',
            ]);
        }
    }

    /**
     * Validate ACADEMIC_RESUME action.
     */
    private static function validateResumeAction(Student $student, array $data): void
    {
        // Soft warning: student should be in deferred status
        // We don't block this in Phase 1 for flexibility
        if ($student->status !== 'deferred') {
            Log::warning("Student resuming but not in deferred status", [
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
        int $userId
    ): void {
        $updateData = [];

        // Update status if action changes it
        if ($actionType->changesStatus()) {
            $updateData['status'] = $targetStatus ?? $actionType->targetStatus();
            $updateData['status_change_date'] = now()->toDateString();
            $updateData['status_reason'] = $data['reason'];
            $updateData['status_changed_by'] = $userId;
        }

        // Update campus if action changes it
        if ($actionType->changesCampus() && isset($data['to_campus_id'])) {
            $updateData['campus_id'] = $data['to_campus_id'];
        }

        if (! empty($updateData)) {
            $student->update($updateData);
        }
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
        string $reason,
        int $userId
    ): void {
        $now = now();

        // Record status change
        if ($actionType->changesStatus()) {
            StudentChange::create([
                'student_id' => $student->id,
                'user_id' => $userId,
                'field_name' => 'status',
                'old_value' => $previousStatus,
                'new_value' => $targetStatus ?? $actionType->targetStatus(),
                'reason' => $reason,
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
                'reason' => $reason,
                'changed_at' => $now,
            ]);
        }
    }
}
