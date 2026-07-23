<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeleteAcademicPeriodAction
{
    public static function run(Semester $academicPeriod): void
    {
        DB::transaction(function () use ($academicPeriod): void {
            if (! $academicPeriod->canDelete()) {
                throw new AcademicPeriodOperationException('Cannot delete this semester. It may be archived or currently active.');
            }

            if ($academicPeriod->enrollments()->exists()) {
                throw new AcademicPeriodOperationException('Cannot delete a semester with existing enrollments.');
            }

            $academicPeriod->delete();
        });

        Cache::tags(['semesters'])->flush();
    }
}
