<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CourseOfferingAttemptWriter
{
    public function moveToOffering(
        int $studentId,
        int $sourceCourseOfferingId,
        int $targetCourseOfferingId,
        ?int $instructorId,
    ): void;

    public function removeForOffering(int $studentId, int $courseOfferingId): void;
}
