<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MoveStudentBetweenCourseOfferingSectionsAction
{
    /**
     * @param  array{student_id: int, target_course_offering_id: int, force_move?: bool}  $data
     */
    public static function run(array $data): void
    {
        $student = app(StudentReferenceReader::class)->find($data['student_id']);
        if ($student === null) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected student does not exist.',
            ]);
        }

        DB::transaction(function () use ($data, $student): void {
            $targetOffering = CourseOffering::query()->findOrFail($data['target_course_offering_id']);
            $registration = CourseRegistration::query()
                ->where('student_id', $student->id)
                ->where('semester_id', $targetOffering->semester_id)
                ->whereHas('courseOffering', fn ($query) => $query->where('unit_id', $targetOffering->unit_id))
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->lockForUpdate()
                ->first();

            if ($registration === null) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student is not currently registered in any section of this unit for the target semester.',
                ]);
            }

            $offerings = CourseOffering::query()
                ->whereKey([$registration->course_offering_id, $targetOffering->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sourceOffering = $offerings->get($registration->course_offering_id);
            $targetOffering = $offerings->get($targetOffering->id);

            if ($sourceOffering === null || $targetOffering === null) {
                throw ValidationException::withMessages([
                    'target_course_offering_id' => 'The selected course offering does not exist.',
                ]);
            }

            if ($student->campusId !== (int) $targetOffering->campus_id) {
                throw ValidationException::withMessages([
                    'student_id' => 'The selected student does not belong to this course offering campus.',
                ]);
            }

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

            EnrollStudentInCourseOfferingAction::assertOfferingIsEnrollable(
                $targetOffering,
                (bool) ($data['force_move'] ?? false),
            );

            $registration->update(['course_offering_id' => $targetOffering->id]);
            AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where('course_offering_id', $sourceOffering->id)
                ->update([
                    'course_offering_id' => $targetOffering->id,
                    'instructor_id' => $targetOffering->lecture_id,
                ]);
            self::migrateAttendance($student->id, $sourceOffering, $targetOffering);

            $sourceOffering->decrement('current_enrollment');
            $targetOffering->increment('current_enrollment');
            $targetOffering->refresh();
            if ((int) $targetOffering->current_enrollment >= (int) $targetOffering->max_capacity) {
                $targetOffering->update(['enrollment_status' => 'closed']);
            }
        });
    }

    private static function migrateAttendance(int $studentId, CourseOffering $source, CourseOffering $target): void
    {
        $attendances = Attendance::query()
            ->where('student_id', $studentId)
            ->whereHas('classSession', fn ($query) => $query->where('course_offering_id', $source->id))
            ->with('classSession')
            ->get();
        $targetSessions = ClassSession::query()
            ->where('course_offering_id', $target->id)
            ->get()
            ->keyBy('sequence_number');

        foreach ($attendances as $attendance) {
            $sourceSession = $attendance->classSession;
            $targetSession = $targetSessions->get($sourceSession->sequence_number);
            if ($targetSession === null) {
                continue;
            }

            $attendance->update(['class_session_id' => $targetSession->id]);
            $sourceSession->updateAttendanceStatistics();
        }
    }
}
