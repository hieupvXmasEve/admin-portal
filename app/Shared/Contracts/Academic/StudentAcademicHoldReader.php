<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface StudentAcademicHoldReader
{
    public function activeCountForStudent(int $studentId): int;
}
