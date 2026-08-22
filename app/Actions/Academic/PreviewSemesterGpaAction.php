<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Support\Academic\GpaValueComparator;

class PreviewSemesterGpaAction
{
    public function __construct(
        protected CalculateStudentSemesterGpaAction $calculateSemesterGpa,
        protected CalculateCumulativeGpaAction $calculateCumulativeGpa,
        protected DetermineAcademicStandingAction $determineStanding
    ) {}

    /**
     * Preview GPA calculations for all students in a semester.
     */
    public function execute(int|string $semesterId, int|string|null $campusId = null): array
    {
        // Get all students who have academic records in this semester.
        // Eligibility is decided by having a final, credit-bearing record for the
        // semester (below), not by the student's current status — a student who
        // completed the semester as intake_course and later deferred/withdrew
        // must still be finalizable for that past semester.
        $studentsQuery = Student::query();

        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
        }

        $students = $studentsQuery->whereHas('academicRecords', function ($query) use ($semesterId) {
            $query->where('semester_id', $semesterId)
                ->where('excluded_from_gpa', false)
                ->where('credit_points', '>', 0);
        })->with(['program', 'academicRecords' => function ($query) use ($semesterId) {
            $query->where('semester_id', $semesterId);
        }])->get();

        // One grouped lookup of the stored snapshots for every student in view,
        // so divergence is decided without a per-student query.
        $storedByStudent = GpaCalculation::whereIn('student_id', $students->pluck('id'))
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy('student_id');

        $previewData = [];

        foreach ($students as $student) {
            // A student is ineligible if they have any record in this semester that is NOT 'final'
            $ineligible = $student->academicRecords
                ->where('grade_status', '!=', 'final')
                ->isNotEmpty();

            $semesterData = $this->calculateSemesterGpa->execute($student, $semesterId);

            // If no credit records (as per Rule 2/3)
            if ($semesterData['credit_points'] <= 0) {
                continue;
            }

            // Calculate cumulative GPA up to and including the current semester
            $cumulativeData = $this->calculateCumulativeGpa->execute($student, $semesterId);
            $standing = $this->determineStanding->execute($cumulativeData['gpa']);

            // Divergence is Phase 1's skip guard inverted: a finalized row is
            // divergent exactly when re-running Finalize would change it. Same
            // GpaValueComparator the finalize guard and the audit use, so the
            // badge cannot disagree with what the button does.
            $stored = $storedByStudent->get($student->id);
            $recomputed = GpaValueComparator::recomputedRow($semesterData, $cumulativeData, $standing, $student->program_id);
            $isDivergent = $stored !== null
                && $stored->is_finalized
                && ! GpaValueComparator::matches($stored->getAttributes(), $recomputed);

            $previewData[] = [
                'id' => $student->id,
                'student_id_code' => $student->student_id,
                'full_name' => $student->full_name,
                'program' => $student->program?->name ?? 'N/A',
                'semester_gpa' => $semesterData['gpa'],
                'cumulative_gpa' => $cumulativeData['gpa'],
                'academic_standing' => $standing,
                'credit_points_attempted' => $semesterData['credit_points'],
                'credit_points_earned' => $semesterData['credit_points_earned'],
                'is_eligible' => !$ineligible,
                'reason' => $ineligible ? 'Has non-final grades' : null,
                // null when never finalized; the FE derives the three snapshot states from these.
                'stored_semester_gpa' => $stored?->is_finalized ? (float) $stored->semester_gpa : null,
                'stored_cumulative_gpa' => $stored?->is_finalized ? (float) $stored->cumulative_gpa : null,
                'is_divergent' => $isDivergent,
            ];
        }

        return $previewData;
    }
}
