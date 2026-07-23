<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use Illuminate\Support\Facades\Cache;

class DeactivateAcademicPeriodAction
{
    /** @return array{success: bool, message: string} */
    public static function run(Semester $academicPeriod): array
    {
        if (! $academicPeriod->canChangeActiveStatus()) {
            return ['success' => false, 'message' => (string) $academicPeriod->getActiveStatusChangeError()];
        }

        if (! $academicPeriod->deactivate()) {
            return ['success' => false, 'message' => 'Failed to deactivate semester.'];
        }

        Cache::tags(['semesters'])->flush();

        return ['success' => true, 'message' => 'Semester deactivated successfully!'];
    }
}
