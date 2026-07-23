<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use Illuminate\Support\Facades\Cache;

class ActivateAcademicPeriodAction
{
    /** @return array{success: bool, message: string} */
    public static function run(Semester $academicPeriod): array
    {
        Semester::deactivateExpiredSemesters();

        if (! $academicPeriod->canChangeActiveStatus()) {
            return ['success' => false, 'message' => (string) $academicPeriod->getActiveStatusChangeError()];
        }

        if (! $academicPeriod->canBeActivated()) {
            return ['success' => false, 'message' => (string) $academicPeriod->getActivationError()];
        }

        if (! $academicPeriod->activate()) {
            return ['success' => false, 'message' => 'Failed to activate semester due to an unknown error.'];
        }

        Cache::tags(['semesters'])->flush();

        return ['success' => true, 'message' => 'Semester activated successfully!'];
    }
}
