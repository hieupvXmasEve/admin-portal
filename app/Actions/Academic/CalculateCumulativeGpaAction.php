<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;

class CalculateCumulativeGpaAction
{
    /**
     * Calculate cumulative GPA for a student up to (and including) a specific semester.
     * 
     * Logic:
     * - For finalized semesters: Uses snapshot from GpaCalculation (ensures consistency)
     * - For preview semester: Calculates from AcademicRecord (current data)
     * - If no semester provided: Calculates from all AcademicRecords
     * 
     * @param Student $student The student to calculate GPA for
     * @param int|string|null $upToSemesterId Optional semester ID to calculate up to (inclusive)
     * @param bool $useFinalizedSnapshots Whether to use finalized GPA snapshots for past semesters (default: true)
     */
    public function execute(Student $student, int|string|null $upToSemesterId = null, bool $useFinalizedSnapshots = true): array
    {
        // If using finalized snapshots and a semester is provided, combine finalized + current semester
        if ($useFinalizedSnapshots && $upToSemesterId !== null) {
            return $this->calculateWithFinalizedSnapshots($student, $upToSemesterId);
        }

        // Fallback: Calculate from all AcademicRecords (original logic)
        $query = AcademicRecord::where('student_id', $student->id)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0);

        // If a specific semester is provided, only include records from semesters up to and including that semester
        if ($upToSemesterId !== null) {
            $targetSemester = Semester::find($upToSemesterId);
            if ($targetSemester && $targetSemester->start_date) {
                // Include records from semesters with start_date <= target semester's start_date
                $query->whereHas('semester', function ($q) use ($targetSemester) {
                    $q->where('start_date', '<=', $targetSemester->start_date);
                });
            } else {
                // Fallback: if semester not found or no start_date, filter by semester_id directly
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

        // Weighted by credit_points (final_percentage * credit_points)
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

    /**
     * Calculate cumulative GPA using finalized snapshots for past semesters
     * and AcademicRecords for the current preview semester.
     * 
     * Strategy:
     * 1. Get the latest finalized snapshot BEFORE the target semester
     * 2. Use its cumulative values as base
     * 3. Add current semester's records to calculate new cumulative
     */
    protected function calculateWithFinalizedSnapshots(Student $student, int|string $upToSemesterId): array
    {
        $targetSemester = Semester::find($upToSemesterId);
        if (!$targetSemester || !$targetSemester->start_date) {
            // Fallback to original logic if semester not found
            return $this->execute($student, $upToSemesterId, false);
        }

        // Get the latest finalized snapshot BEFORE the target semester
        $latestFinalized = GpaCalculation::where('student_id', $student->id)
            ->where('is_finalized', true)
            ->whereHas('semester', function ($q) use ($targetSemester) {
                $q->where('start_date', '<', $targetSemester->start_date);
            })
            ->orderBy('semester_id', 'desc')
            ->first();

        // Get records for the target semester (current preview semester)
        $currentSemesterRecords = AcademicRecord::where('student_id', $student->id)
            ->where('semester_id', $upToSemesterId)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0)
            ->get();

        // Calculate quality points for current semester
        $currentQualityPoints = $currentSemesterRecords->sum(function ($record) {
            return (float) $record->final_percentage * (float) $record->credit_points;
        });
        $currentCreditPoints = (float) $currentSemesterRecords->sum('credit_points');
        $currentCreditPointsEarned = (float) $currentSemesterRecords->where('is_passed', true)->sum('credit_points');

        if ($latestFinalized) {
            // Use the latest finalized cumulative as base, then add current semester
            // Note: We use cumulative values from the snapshot, not semester values
            $baseQualityPoints = (float) $latestFinalized->cumulative_quality_points;
            $baseCreditPoints = (float) $latestFinalized->cumulative_credit_points;
            $baseCreditPointsEarned = (float) $latestFinalized->cumulative_credit_points_earned;

            // Add current semester
            $totalQualityPoints = $baseQualityPoints + $currentQualityPoints;
            $totalCreditPoints = $baseCreditPoints + $currentCreditPoints;
            $totalCreditPointsEarned = $baseCreditPointsEarned + $currentCreditPointsEarned;
        } else {
            // No finalized semesters before this, calculate from current semester only
            $totalQualityPoints = $currentQualityPoints;
            $totalCreditPoints = $currentCreditPoints;
            $totalCreditPointsEarned = $currentCreditPointsEarned;
        }

        $gpa = $totalCreditPoints > 0 ? $totalQualityPoints / $totalCreditPoints : 0.0;

        return [
            'gpa' => round((float) $gpa, 3),
            'quality_points' => round((float) $totalQualityPoints, 3),
            'credit_points' => $totalCreditPoints,
            'credit_points_earned' => $totalCreditPointsEarned,
        ];
    }
}
