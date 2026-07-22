<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\AcademicRecord;
use App\Shared\Contracts\Academic\CourseOfferingAttemptWriter;

final class EloquentCourseOfferingAttemptWriter implements CourseOfferingAttemptWriter
{
    public function moveToOffering(
        int $studentId,
        int $sourceCourseOfferingId,
        int $targetCourseOfferingId,
        ?int $instructorId,
    ): void {
        AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $sourceCourseOfferingId)
            ->update([
                'course_offering_id' => $targetCourseOfferingId,
                'instructor_id' => $instructorId,
            ]);
    }

    public function removeForOffering(int $studentId, int $courseOfferingId): void
    {
        AcademicRecord::withTrashed()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->get()
            ->each->forceDelete();
    }
}
