<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Modules\Academic\Progression\Queries\Reporting\GetAcademicReportQuery;

class GetAcademicReportAction
{
    /**
     * Execute the academic report logic.
     */
    public function execute(array $filters): array
    {
        return app(GetAcademicReportQuery::class)->handle($filters);
    }
}
