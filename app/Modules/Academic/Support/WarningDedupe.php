<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

final class WarningDedupe
{
    public static function academicStanding(int $studentId, int $gpaCalculationId): string
    {
        return hash('sha256', "academic_standing_warning:{$studentId}:{$gpaCalculationId}");
    }

    public static function attendance(string $warningType, int $studentId, int $courseOfferingId, int $classSessionId, int $absenceCount): string
    {
        return hash('sha256', "{$warningType}:{$studentId}:{$courseOfferingId}:{$classSessionId}:{$absenceCount}");
    }
}
