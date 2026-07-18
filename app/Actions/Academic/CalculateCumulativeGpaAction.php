<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\Student;
use App\Shared\Contracts\Academic\TranscriptEntryGpaReader;

class CalculateCumulativeGpaAction
{
    public function __construct(private readonly TranscriptEntryGpaReader $transcriptEntries) {}

    /**
     * Calculate cumulative GPA for a student up to (and including) a specific semester.
     *
     * Every final, credit-bearing attempt within the window contributes — including
     * failed attempts and retakes. credit_points_earned only counts passing attempts.
     * All values use the 100-point scale.
     */
    public function execute(Student $student, int|string|null $upToSemesterId = null): array
    {
        $records = collect($this->transcriptEntries->throughSemester($student->id, $upToSemesterId));

        if ($records->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_points' => 0.0,
                'credit_points_earned' => 0.0,
            ];
        }

        $totalQualityPoints = $records->sum(function ($record) {
            return $record->finalPercentage * $record->creditPoints;
        });
        $totalCreditPoints = (float) $records->sum('creditPoints');
        $creditPointsEarned = (float) $records->where('isPassed', true)->sum('creditPoints');

        $gpa = $totalCreditPoints > 0 ? $totalQualityPoints / $totalCreditPoints : 0.0;

        return [
            'gpa' => round((float) $gpa, 3),
            'quality_points' => round((float) $totalQualityPoints, 3),
            'credit_points' => $totalCreditPoints,
            'credit_points_earned' => $creditPointsEarned,
        ];
    }
}
