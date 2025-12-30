<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\Semester;
use App\Models\Student;

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
        // Get all students who have academic records in this semester
        $studentsQuery = Student::where('status', 'intake_course');

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

            $cumulativeData = $this->calculateCumulativeGpa->execute($student);
            $standing = $this->determineStanding->execute($cumulativeData['gpa']);

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
            ];
        }

        return $previewData;
    }
}
