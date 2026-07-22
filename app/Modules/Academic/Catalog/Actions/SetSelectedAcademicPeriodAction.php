<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Support\SemesterContextResolver;

class SetSelectedAcademicPeriodAction
{
    public static function run(?int $academicPeriodId): void
    {
        session([
            SemesterContextResolver::SessionKey => $academicPeriodId ?? SemesterContextResolver::AllSemesters,
        ]);
    }
}
