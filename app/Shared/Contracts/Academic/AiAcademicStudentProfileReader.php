<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface AiAcademicStudentProfileReader
{
    /**
     * @return array<string, mixed>|null
     */
    public function identity(int $studentId): ?array;

    /**
     * @return array<string, mixed>
     */
    public function academicSummary(int $studentId): array;

    /**
     * @return array<string, mixed>
     */
    public function enrollments(int $studentId, int $limit): array;

    /**
     * @return array<string, mixed>
     */
    public function attendanceSummary(int $studentId, int $limit): array;

    /**
     * @return array<string, mixed>
     */
    public function lifecycleActions(int $studentId, int $limit): array;
}
