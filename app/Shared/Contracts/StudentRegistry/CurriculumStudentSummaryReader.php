<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

interface CurriculumStudentSummaryReader
{
    /**
     * @return array{counts: array<string, int>, total: int, enrollment_trends: list<array{period: string, count: int}>, graduation_projections: array<int, int>, statistics: array{active_percentage: float, graduation_rate: float, retention_rate: float}}
     */
    public function summaryForCurriculumVersion(int $curriculumVersionId, int $campusId): array;
}
