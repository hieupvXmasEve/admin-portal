<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use Illuminate\Support\Collection;

interface AiAcademicMetricReader
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function studentStatusBySemesterExport(
        int $selectedSemesterId,
        ?int $campusId = null,
        ?string $currentStatus = null,
    ): Collection;
}
