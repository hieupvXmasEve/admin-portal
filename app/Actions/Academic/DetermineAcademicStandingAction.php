<?php

declare(strict_types=1);

namespace App\Actions\Academic;

class DetermineAcademicStandingAction
{
    /**
     * Determine academic standing based on GPA.
     */
    public function execute(float $gpa): string
    {
        if ($gpa >= 2.0) {
            return 'normal';
        }

        return 'warning';
    }
}
