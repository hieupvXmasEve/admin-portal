<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentGpaTrend;

interface StudentGpaTrendReader
{
    public function forStudent(int $studentId, int $semesterCount): StudentGpaTrend;
}
