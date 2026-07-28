<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Services\V1\Student\PrerequisiteValidationService;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EnrollStudentInCourseOfferingAction
{
    /**
     * @param  array{
     *   student_id: int,
     *   course_offering_id: int,
     *   registration_status?: string,
     *   registration_method?: string,
     *   force_registration?: bool,
     *   credit_hours?: float|int|null,
     *   notes?: string|null,
     *   attempt_number?: int|null,
     *   is_retake?: bool,
     *   original_registration_id?: int|null,
     *   retake_fee?: float|int|null,
     *   is_retake_paid?: bool,
     * }  $data
     */
    public static function run(array $data): CourseRegistration
    {
        $student = app(StudentReferenceReader::class)->find($data['student_id']);
        if ($student === null) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected student does not exist.',
            ]);
        }

        return DB::transaction(function () use ($data, $student): CourseRegistration {
            $offering = CourseOffering::query()
                ->lockForUpdate()
                ->findOrFail($data['course_offering_id']);

            if ($student->campusId !== (int) $offering->campus_id) {
                throw ValidationException::withMessages([
                    'student_id' => 'The selected student does not belong to this course offering campus.',
                ]);
            }

            $unit = app(CourseOfferingCatalogReader::class)->offeringUnit((int) $offering->unit_id);
            if ($unit === null) {
                throw ValidationException::withMessages([
                    'course_offering_id' => 'The selected course offering has an invalid unit.',
                ]);
            }

            $forceRegistration = (bool) ($data['force_registration'] ?? false);
            $hasCapacity = ! $offering->isFull();
            self::assertOfferingIsEnrollable($offering, $forceRegistration);

            if (! $forceRegistration && ! app(PrerequisiteValidationService::class)->hasMetPrerequisites($student->id, $offering)) {
                throw ValidationException::withMessages([
                    'student_id' => "Student has not met the prerequisites for {$unit->code}.",
                ]);
            }

            $duplicate = CourseRegistration::query()
                ->where('student_id', $student->id)
                ->where('course_offering_id', $offering->id)
                ->where('semester_id', $offering->semester_id)
                ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'course_offering_id' => 'Student is already registered for this course offering.',
                ]);
            }

            $registration = CourseRegistration::query()->create([
                'student_id' => $student->id,
                'course_offering_id' => $offering->id,
                'semester_id' => $offering->semester_id,
                'registration_status' => $data['registration_status'] ?? 'confirmed',
                'registration_date' => now(),
                'registration_method' => $data['registration_method'] ?? 'admin_override',
                'credit_hours' => $data['credit_hours'] ?? (float) $unit->credit_points,
                'notes' => $data['notes'] ?? null,
                'attempt_number' => $data['attempt_number'] ?? 1,
                'is_retake' => $data['is_retake'] ?? false,
                'original_registration_id' => $data['original_registration_id'] ?? null,
                'retake_fee' => $data['retake_fee'] ?? 0,
                'is_retake_paid' => ($data['is_retake_paid'] ?? false) ? 'yes' : 'no',
            ]);

            if ($hasCapacity) {
                $offering->increment('current_enrollment');
                $offering->refresh();
                if ((int) $offering->current_enrollment >= (int) $offering->max_capacity) {
                    $offering->update(['enrollment_status' => 'closed']);
                }
            }

            return $registration;
        });
    }

    public static function assertOfferingIsEnrollable(CourseOffering $offering, bool $forceRegistration): void
    {
        if (! $offering->is_active || in_array($offering->course_status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'course_offering_id' => 'The selected course offering is not active.',
            ]);
        }

        if (! $forceRegistration && (! $offering->canEnroll())) {
            $message = $offering->isFull()
                ? 'The selected course offering is full.'
                : 'The selected course offering is not open for registration.';
            throw ValidationException::withMessages(['course_offering_id' => $message]);
        }
    }
}
