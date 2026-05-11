<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Student;

class CalculateStudentSemesterGpaAction
{
    /**
     * Calculate GPA for a student in a specific semester (100-point scale).
     * Every final, credit-bearing attempt counts — including failed attempts
     * and any retake within the semester. credit_points_earned only counts
     * passing attempts.
     */
    public function execute(Student $student, int|string $semesterId): array
    {
        $records = AcademicRecord::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0)
            ->where('grade_status', 'final')
            ->get();

        if ($records->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_points' => 0.0,
                'credit_points_earned' => 0.0,
            ];
        }

        $totalQualityPoints = $records->sum(function ($record) {
            return (float) $record->final_percentage * (float) $record->credit_points;
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
