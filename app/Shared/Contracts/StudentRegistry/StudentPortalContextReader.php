<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

interface StudentPortalContextReader
{
    /** @return array<string, mixed> */
    public function forStudent(int $studentId): array;
}
