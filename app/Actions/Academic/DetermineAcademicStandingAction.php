<?php

declare(strict_types=1);

namespace App\Actions\Academic;

class DetermineAcademicStandingAction
{
    /**
     * Threshold uses the 100-point GPA scale (final_percentage * credit_points / credit_points).
     */
    public const NORMAL_STANDING_THRESHOLD = 50.0;

    /**
     * Determine academic standing based on GPA.
     */
    public function execute(float $gpa): string
    {
        if ($gpa >= self::NORMAL_STANDING_THRESHOLD) {
            return 'normal';
        }

        return 'warning';
    }
}
