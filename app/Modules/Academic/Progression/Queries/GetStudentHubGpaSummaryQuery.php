<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;

/**
 * Compatibility projection for the Student Hub's current GPA card.
 *
 * The Hub historically displays an explicitly current calculation before it
 * is finalized; other Progression consumers continue to use the finalized
 * academic-records reader.
 */
final class GetStudentHubGpaSummaryQuery
{
    /** @return array{latest_semester_gpa: float, cumulative_gpa: float, academic_standing: string} */
    public function handle(int $studentId): array
    {
        $gpa = GpaCalculation::query()
            ->where('student_id', $studentId)
            ->where('is_current', true)
            ->first() ?? GpaCalculation::query()
            ->where('student_id', $studentId)
            ->latest()
            ->first();

        return [
            'latest_semester_gpa' => (float) ($gpa?->semester_gpa ?? 0.0),
            'cumulative_gpa' => (float) ($gpa?->cumulative_gpa ?? $gpa?->semester_gpa ?? 0.0),
            'academic_standing' => $gpa?->academic_standing ?? 'unknown',
        ];
    }
}
