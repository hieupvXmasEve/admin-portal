<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Student;

class CalculateCumulativeGpaAction
{
    /**
     * Calculate cumulative GPA for a student up to (and including) a specific semester.
     * Note: In a real system, you might only sum up to a specific date or semester sequence.
     * For simplicity in Phase 1, we calculate based on all records marked for GPA calculation.
     */
    public function execute(Student $student): array
    {
        $records = AcademicRecord::where('student_id', $student->id)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0)
            ->get();

        if ($records->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_points' => 0.0,
                'credit_points_earned' => 0.0,
            ];
        }

        // Weighted by credit_points
        $totalQualityPoints = $records->sum(function ($record) {
            return (float) $record->grade_points * (float) $record->credit_points;
        });

        $totalCreditPoints = (float) $records->sum('credit_points');
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
