<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery;
use App\Shared\Contracts\Academic\AiAcademicMetricReader as AiAcademicMetricReaderContract;
use Illuminate\Support\Collection;

class AiAcademicMetricReader implements AiAcademicMetricReaderContract
{
    public function __construct(
        private readonly GetStudentStatusBySemesterQuery $studentStatusQuery,
    ) {}

    public function studentStatusBySemesterExport(
        int $selectedSemesterId,
        ?int $campusId = null,
        ?string $currentStatus = null,
    ): Collection {
        return $this->studentStatusQuery->handleExport($selectedSemesterId, $campusId, $currentStatus);
    }
}
