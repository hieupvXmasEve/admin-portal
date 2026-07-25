<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\ReadSurfaceMetadata;

interface StudentDashboardReader extends ReadSurfaceMetadata
{
    /** @return array<string, mixed> */
    public function dashboardForStudent(int $studentId): array;

    /** @return array<string, mixed> */
    public function gpaForStudent(int $studentId): array;

    /** @return array<string, mixed> */
    public function creditProgressForStudent(int $studentId): array;

    /** @return array<string, mixed> */
    public function academicHoldsForStudent(int $studentId): array;

    /** @return array<string, mixed> */
    public function upcomingAssessmentsForStudent(int $studentId): array;
}
