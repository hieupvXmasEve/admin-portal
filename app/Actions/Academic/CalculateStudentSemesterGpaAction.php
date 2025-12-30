<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Student;

class CalculateStudentSemesterGpaAction
{
    /**
     * Calculate GPA for a student in a specific semester.
     */
    public function execute(Student $student, int|string $semesterId): array
    {
        $baseQuery = AcademicRecord::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0);

        $records = $baseQuery->get();

        if ($records->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_points' => 0.0,
                'credit_points_earned' => 0.0,
            ];
        }

        // Weight = credit_points
        // Quality Points should technically be Grade points * credit_points if we use points for weighting GPA
        // If we still use quality_points from record, we must ensure it was calculated using credit_points
        // For now, let's calculate them dynamically to be safe
        $totalQualityPoints = $records->sum(function ($record) {
            return (float) $record->grade_points * (float) $record->credit_points;
        });
        
        $totalCreditPoints = (float) $records->sum('credit_points');
        
        // Credits earned are from passed records
        $creditPointsEarned = (float) $records->where('is_passed', true)->sum('credit_points');

        $gpa = $totalCreditPoints > 0 ? $totalQualityPoints / $totalCreditPoints : 0.0;

        return [
            'gpa' => round((float) $gpa, 3),
            'quality_points' => round((float) $totalQualityPoints, 3),
            'credit_points' => $totalCreditPoints,
            'credit_points_earned' => $creditPointsEarned,
        ];
    }
}
