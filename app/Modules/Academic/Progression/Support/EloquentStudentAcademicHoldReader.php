<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\AcademicHold;
use App\Shared\Contracts\Academic\StudentAcademicHoldReader;

final class EloquentStudentAcademicHoldReader implements StudentAcademicHoldReader
{
    public function activeCountForStudent(int $studentId): int
    {
        return AcademicHold::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->count();
    }
}
