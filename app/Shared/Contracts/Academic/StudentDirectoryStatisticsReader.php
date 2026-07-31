<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentDirectoryStatistics;

interface StudentDirectoryStatisticsReader
{
    public function forCampus(int $campusId): StudentDirectoryStatistics;
}
