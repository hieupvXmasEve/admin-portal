<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CourseOfferingFinalizer
{
    /** @param array<int, float> $finalPercentageOverrides @return array<string, mixed> */
    public function finalize(int $courseOfferingId, bool $recalculate, bool $dryRun, array $finalPercentageOverrides): array;
}
