<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CheckCourseRegistrationEligibilityQuery
{
    public function __construct(private StudentReferenceReader $students) {}

    /** @return array{eligible: bool, reasons: list<string>, can_override: true} */
    public function handle(int $studentId, int $courseOfferingId, int $campusId): array
    {
        $student = $this->students->find($studentId);
        if ($student === null) {
            throw new \RuntimeException('Student not found.');
        }
        if ($student->campusId !== $campusId) {
            throw new NotFoundHttpException;
        }

        $courseOffering = CourseOffering::query()
            ->where('campus_id', $campusId)
            ->findOrFail($courseOfferingId);
        $reasons = [];

        if (in_array($student->status, ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'], true)) {
            $reasons[] = 'Student is not active';
        }

        $existingRegistration = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->where('course_offering_id', $courseOffering->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->exists();
        if ($existingRegistration) {
            $reasons[] = 'Already registered for this course';
        }

        if (! $courseOffering->isAvailableForRegistration()) {
            $reasons[] = 'Course is not available for registration';
        }

        if ($courseOffering->current_enrollment >= $courseOffering->max_enrollment) {
            $reasons[] = 'Course is at full capacity (admin can override)';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons, 'can_override' => true];
    }
}
