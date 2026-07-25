<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentDeferActionSummary;

interface StudentDeferLifecycleReader
{
    public function findDeferAction(int $actionId): ?StudentDeferActionSummary;

    /** @return list<StudentDeferActionSummary> */
    public function listDeferActions(?int $semesterId = null, ?int $campusId = null): array;
}
