<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Semester;

class CheckGpaFinalizationEligibilityAction
{
    /**
     * Check GPA finalization status for students in a semester.
     */
    public function execute(string|int $semesterId, int|string|null $campusId = null): array
    {
        // Total students who have valid GPA-contributing records in this semester AND have status = 'intake_course'
        $baseQuery = AcademicRecord::where('semester_id', $semesterId)
            ->where('excluded_from_gpa', false)
            ->where('credit_points', '>', 0)
            ->whereHas('student', function ($query) use ($campusId) {
                $query->where('status', 'intake_course');
                if ($campusId) {
                    $query->where('campus_id', $campusId);
                }
            });

        $studentsInSemester = (clone $baseQuery)->distinct('student_id')
            ->pluck('student_id');

        if ($studentsInSemester->isEmpty()) {
            return [
                'eligible' => false,
                'total_students' => 0,
                'eligible_count' => 0,
                'ineligible_count' => 0,
                'message' => 'No intake_course academic records found for this semester' . ($campusId ? ' in this campus.' : '.'),
            ];
        }

        // Students who have at least one record NOT 'final' among their GPA-contributing records
        $ineligibleStudentIds = (clone $baseQuery)->where('grade_status', '!=', 'final')
            ->distinct('student_id')
            ->pluck('student_id');

        $totalCount = $studentsInSemester->count();
        $ineligibleCount = $ineligibleStudentIds->count();
        $eligibleCount = $totalCount - $ineligibleCount;

        if ($ineligibleCount > 0) {
            return [
                'eligible' => true, // Still eligible to process the eligible ones
                'total_students' => $totalCount,
                'eligible_count' => $eligibleCount,
                'ineligible_count' => $ineligibleCount,
                'message' => "{$eligibleCount} students are eligible. {$ineligibleCount} students have pending grades and will be skipped.",
                'partial' => true,
            ];
        }

        return [
            'eligible' => true,
            'total_students' => $totalCount,
            'eligible_count' => $eligibleCount,
            'ineligible_count' => 0,
            'message' => "All {$totalCount} students in this semester are eligible for GPA finalization.",
            'partial' => false,
        ];
    }
}
