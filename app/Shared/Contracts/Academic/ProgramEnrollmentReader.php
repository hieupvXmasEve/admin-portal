<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;

interface ProgramEnrollmentReader
{
    /**
     * Returns the Progression-owned enrollment projection for a Student ID.
     * The implementation retains a legacy adapter until the explicit backfill
     * has run in every environment.
     */
    public function forStudentId(int $studentId): ProgramEnrollmentSummary;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ProgramEnrollmentSummary>
     */
    public function forStudentIds(array $studentIds): array;
}
