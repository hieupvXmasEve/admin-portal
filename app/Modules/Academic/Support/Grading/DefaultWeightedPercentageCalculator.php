<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support\Grading;

use App\Models\AcademicRecord;
use App\Modules\Academic\Support\Grading\Contracts\GradingCalculator;

/**
 * Preserves the existing weighted-average model.
 *
 * Expects $componentScores to contain a special '__weighted_average__' key
 * with the pre-computed weighted final percentage (0-100). This key is set
 * by CourseCompletionService before dispatching to the calculator when no
 * custom scheme is active.
 */
class DefaultWeightedPercentageCalculator implements GradingCalculator
{
    public function calculate(array $componentScores, ?array $scheme): GradingResult
    {
        $finalPercentage = (float) ($componentScores['__weighted_average__'] ?? 0.0);
        $letterGrade = AcademicRecord::calculateLetterGrade($finalPercentage);
        $gradePoints = AcademicRecord::calculateGradePoints($finalPercentage);
        $passed = $finalPercentage >= ($scheme['passing_threshold'] ?? 60.0);

        return new GradingResult(
            finalPercentage: $finalPercentage,
            finalGrade: $letterGrade,
            gradePoints: $gradePoints,
            passed: $passed,
            gradeBreakdown: [
                'engine' => 'default_weighted_percentage',
                'final_percentage' => $finalPercentage,
                'final_letter_grade' => $letterGrade,
                'grade_points' => $gradePoints,
            ],
        );
    }
}
