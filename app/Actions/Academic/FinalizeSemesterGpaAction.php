<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\AcademicRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinalizeSemesterGpaAction
{
    public function __construct(
        protected CalculateStudentSemesterGpaAction $calculateSemesterGpa,
        protected CalculateCumulativeGpaAction $calculateCumulativeGpa,
        protected DetermineAcademicStandingAction $determineStanding
    ) {}

    /**
     * Finalize GPA for all students in a semester.
     */
    public function execute(int|string $semesterId, int $adminId, int|string|null $campusId = null): array
    {
        $semester = Semester::findOrFail($semesterId);
        
        // Get all students who have academic records in this semester
        $studentsQuery = Student::where('status', 'intake_course');

        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
        }

        $students = $studentsQuery->whereHas('academicRecords', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)
                    ->where('excluded_from_gpa', false)
                    ->where('credit_points', '>', 0);
            })->with(['academicRecords' => function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            }])->get();

        $count = 0;
        $skipped = 0;

        DB::transaction(function () use ($students, $semesterId, $adminId, &$count, &$skipped) {
            foreach ($students as $student) {
                // SKIP if student has any record that is NOT 'final'
                $hasNonFinal = $student->academicRecords->where('grade_status', '!=', 'final')->isNotEmpty();
                if ($hasNonFinal) {
                    $skipped++;
                    continue;
                }

                $semesterData = $this->calculateSemesterGpa->execute($student, $semesterId);
                
                // Rule 2 & 3: Skip if no credit points (no actual subjects enrolled/passed with credits)
                if ($semesterData['credit_points'] <= 0) {
                    $skipped++;
                    continue;
                }

                // Calculate cumulative GPA up to and including the current semester
                $cumulativeData = $this->calculateCumulativeGpa->execute($student, $semesterId);
                $standing = $this->determineStanding->execute($cumulativeData['gpa']);

                // Mark previous records for this student as not current
                GpaCalculation::where('student_id', $student->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                // Create or update snapshot
                GpaCalculation::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'program_id' => $student->program_id,
                        'semester_gpa' => $semesterData['gpa'],
                        'cumulative_gpa' => $cumulativeData['gpa'],
                        'semester_quality_points' => $semesterData['quality_points'],
                        'cumulative_quality_points' => $cumulativeData['quality_points'],
                        'semester_credit_points' => $semesterData['credit_points'],
                        'cumulative_credit_points' => $cumulativeData['credit_points'],
                        'semester_credit_points_earned' => $semesterData['credit_points_earned'],
                        'cumulative_credit_points_earned' => $cumulativeData['credit_points_earned'],
                        'academic_standing' => $standing,
                        'is_finalized' => true,
                        'finalized_at' => Carbon::now(),
                        'finalized_by_id' => $adminId,
                        'is_current' => true,
                    ]
                );

                $count++;
            }
        });

        return [
            'success' => true,
            'processed_students' => $count,
            'skipped_students' => $skipped,
            'message' => "Successfully finalized GPA for {$count} students. {$skipped} students were skipped (pending grades or no records).",
        ];
    }
}
