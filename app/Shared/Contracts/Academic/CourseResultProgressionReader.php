<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CourseResultProgressionReader
{
    public function hasActiveAttemptForStudentAndUnit(int $studentId, int $unitId): bool;
}
