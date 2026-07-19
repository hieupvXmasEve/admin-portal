<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AcademicRecord;
use App\Shared\Contracts\Academic\CourseResultProgressionReader;

final class EloquentCourseResultProgressionReader implements CourseResultProgressionReader
{
    public function hasActiveAttemptForStudentAndUnit(int $studentId, int $unitId): bool
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('unit_id', $unitId)
            ->whereIn('completion_status', ['enrolled', 'in_progress', 'completed'])
            ->exists();
    }
}
