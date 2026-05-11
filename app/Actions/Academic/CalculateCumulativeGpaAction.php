<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Semester;
use App\Models\Student;

class CalculateCumulativeGpaAction
{
    /**
     * Calculate cumulative GPA for a student up to (and including) a specific semester.
     *
     * Every final, credit-bearing attempt within the window contributes — including
     * failed attempts and retakes. credit_points_earned only counts passing attempts.
     * All values use the 100-point scale.
     */
    public function execute(Student $student, int|string|null $upToSemesterId = null): array
    {
        $query = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0)
            ->where('grade_status', 'final');

        if ($upToSemesterId !== null) {
            $target = Semester::find($upToSemesterId);
            if ($target && $target->start_date) {
                $query->whereHas('semester', function ($q) use ($target) {
                    $q->where('start_date', '<=', $target->start_date);
                });
            } else {
                $query->where('semester_id', '<=', $upToSemesterId);
            }
        }

        $records = $query->get();

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
