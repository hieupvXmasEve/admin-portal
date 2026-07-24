<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentAcademicRecords;

interface StudentAcademicRecordsReader
{
    public function forStudent(int $studentId): StudentAcademicRecords;
}
