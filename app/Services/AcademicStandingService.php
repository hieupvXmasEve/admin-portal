<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicStanding;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicStandingService
{
    /**
     * Calculate and update academic standing for a student
     */
    public function calculateAndUpdateStanding(Student $student, Semester $semester, ?User $creator = null): AcademicStanding
    {
        return DB::transaction(function () use ($student, $semester, $creator) {
            $semesterGPA = $this->calculateSemesterGPA($student, $semester);
            $cumulativeGPA = $this->calculateCumulativeGPA($student);
            $totalCredits = $this->getTotalCreditsCompleted($student);

            $standing = $this->determineStanding($cumulativeGPA, $totalCredits);

            // Deactivate previous standings for this semester
            AcademicStanding::where('student_id', $student->id)
                ->where('semester_id', $semester->id)
                ->update(['is_active' => false]);

            $academicStanding = AcademicStanding::create([
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'standing' => $standing,
                'gpa' => $semesterGPA,
                'cumulative_gpa' => $cumulativeGPA,
                'total_credits_completed' => $totalCredits,
                'effective_date' => now(),
                'created_by' => $creator?->id,
                'is_active' => true,
            ]);

            Log::info('Academic standing updated', [
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'standing' => $standing,
                'gpa' => $semesterGPA,
                'cumulative_gpa' => $cumulativeGPA,
            ]);

            return $academicStanding;
        });
    }

    /**
     * Get academic standing history for a student
     */
    public function getStandingHistory(Student $student): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $student->academicStandings()
            ->with(['semester', 'createdBy'])
            ->orderBy('effective_date', 'desc')
            ->paginate(15);
    }

    /**
     * Bulk update standings for all students in a semester
     */
    public function bulkUpdateStandings(Semester $semester, User $creator): array
    {
        $students = Student::academicallyActive()->get();
        $updated = 0;
        $errors = [];

        foreach ($students as $student) {
            try {
                $this->calculateAndUpdateStanding($student, $semester, $creator);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Student {$student->id}: " . $e->getMessage();
            }
        }

        return [
            'updated_count' => $updated,
            'total_students' => $students->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Determine academic standing based on GPA and credits
     */
    private function determineStanding(float $cumulativeGPA, int $totalCredits): string
    {
        if ($cumulativeGPA >= 3.5) {
            return 'honors';
        } elseif ($cumulativeGPA >= 2.0) {
            return 'good';
        } elseif ($cumulativeGPA >= 1.5) {
            return 'probation';
        } else {
            return 'suspension';
        }
    }

    private function calculateSemesterGPA(Student $student, Semester $semester): float
    {
        // Implementation similar to AcademicRecordService
        return app(AcademicRecordService::class)->calculateSemesterGPA($student, $semester->id);
    }

    private function calculateCumulativeGPA(Student $student): float
    {
        return app(AcademicRecordService::class)->calculateCumulativeGPA($student);
    }

    private function getTotalCreditsCompleted(Student $student): int
    {
        return $student->academicRecords()
            ->whereNotNull('final_grade')
            ->join('units', 'academic_records.unit_id', '=', 'units.id')
            ->sum('units.credit_points');
    }
}
