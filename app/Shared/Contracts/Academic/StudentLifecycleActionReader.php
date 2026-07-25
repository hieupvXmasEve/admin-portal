<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentLifecycleActionSummary;

interface StudentLifecycleActionReader
{
    /** @param list<int> $studentIds @return array<int, StudentLifecycleActionSummary> */
    public function latestForStudentIds(array $studentIds): array;
}
