<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MoveStudentToSectionAction
{
    /**
     * Move a student from one course offering to another.
     *
     * @param  array  $data  [student_id, target_course_offering_id, force_move]
     * @return void
     *
     * @throws ValidationException
     */
    public static function run(array $data): void
    {
        $studentId = $data['student_id'];
        $targetOfferingId = $data['target_course_offering_id'];
        $forceMove = $data['force_move'] ?? false;

        $student = Student::findOrFail($studentId);
        $targetOffering = CourseOffering::findOrFail($targetOfferingId);

        // 1. Find current active registration for this unit/semester
        // We assume the student is already registered in a sibling section.
        $currentRegistration = CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $targetOffering->semester_id)
            ->whereHas('courseOffering', function ($query) use ($targetOffering) {
                $query->where('unit_id', $targetOffering->unit_id);
            })
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->first();

        if (! $currentRegistration) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not currently registered in any section of this unit for the target semester.',
            ]);
        }

        $sourceOffering = $currentRegistration->courseOffering;

        if ($sourceOffering->id === $targetOffering->id) {
            throw ValidationException::withMessages([
                'target_course_offering_id' => 'Student is already in this section.',
            ]);
        }

        if ($sourceOffering->unit_id !== $targetOffering->unit_id) {
            throw ValidationException::withMessages([
                'target_course_offering_id' => 'Target section must belong to the same unit.',
            ]);
        }

        // 2. Check Capacity
        if (! $forceMove && $targetOffering->isFull()) {
            throw ValidationException::withMessages([
                'target_course_offering_id' => 'Target section is full.',
            ]);
        }

        DB::transaction(function () use ($studentId, $sourceOffering, $targetOffering, $currentRegistration) {
            // 3. Update Course Registration
            $currentRegistration->update([
                'course_offering_id' => $targetOffering->id,
            ]);

            // 4. Update Academic Record
            AcademicRecord::query()
                ->where('student_id', $studentId)
                ->where('course_offering_id', $sourceOffering->id)
                ->update([
                    'course_offering_id' => $targetOffering->id,
                    'instructor_id' => $targetOffering->lecture_id,
                ]);

            // 5. Migrate Attendance
            self::migrateAttendance($studentId, $sourceOffering, $targetOffering);

            // 6. Update Enrollments Counters
            $sourceOffering->decrementEnrollment();
            $targetOffering->incrementEnrollment();

            Log::info("Moved student {$studentId} from offering {$sourceOffering->id} to {$targetOffering->id}");
        });
    }

    private static function migrateAttendance(int $studentId, CourseOffering $source, CourseOffering $target): void
    {
        // Get all attendance records for this student in the source offering
        $attendances = Attendance::query()
            ->where('student_id', $studentId)
            ->whereHas('classSession', function ($query) use ($source) {
                $query->where('course_offering_id', $source->id);
            })
            ->with('classSession')
            ->get();

        // Get all target sessions keyed by sequence number
        $targetSessions = ClassSession::query()
            ->where('course_offering_id', $target->id)
            ->get()
            ->keyBy('sequence_number');

        foreach ($attendances as $attendance) {
            $sourceSession = $attendance->classSession;
            $sequenceNumber = $sourceSession->sequence_number;

            if ($targetSessions->has($sequenceNumber)) {
                $targetSession = $targetSessions->get($sequenceNumber);

                $attendance->update([
                    'class_session_id' => $targetSession->id,
                ]);

                // Update stats for both sessions if needed (Attendance model hooks might handle this, but being explicit is safe)
                // The Attendance model has `saved` and `deleted` events that call updateAttendanceStatistics on the session.
                // Since we changed the class_session_id, we ideally want to update stats for OLD and NEW session.
                // However, standard Eloquent `update` on the model instance fires `saved` with the NEW state.
                // So the NEW session ($targetSession) stats will be updated.
                // We should manually trigger update for the OLD session ($sourceSession) because it lost a record.
                $sourceSession->updateAttendanceStatistics();
                // The new session will be updated by the model hook on $attendance->save()/update().

            } else {
                Log::warning("Could not migrate attendance for student {$studentId} from session {$sourceSession->id} (Seq: {$sequenceNumber}). No matching sequence in target offering {$target->id}.");
                // Option: Delete the attendance or leave it orphaned?
                // Leaving it orphaned means it points to a session in the OLD offering, but the student is now in the NEW offering.
                // This might be confusing in reports.
                // For now, we will leave it as is, effectively "losing" it from the new context but preserving the data row.
            }
        }
    }
}
