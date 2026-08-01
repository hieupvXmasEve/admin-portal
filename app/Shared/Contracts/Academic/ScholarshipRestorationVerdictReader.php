<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

/**
 * Academic verdict on whether a student's target-semester results clear a
 * carried-forward scholarship reduction for restoration. Finance (P5
 * evaluation command) consumes this through the Shared contract only —
 * never App\Models\AcademicRecord directly.
 */
interface ScholarshipRestorationVerdictReader
{
    public const CLEAN = 'clean';

    public const STILL_FAILING = 'still_failing';

    public const NOT_FINALIZED = 'not_finalized';

    /** @return self::CLEAN|self::STILL_FAILING|self::NOT_FINALIZED */
    public function verdict(int $studentId, int $semesterId): string;
}
