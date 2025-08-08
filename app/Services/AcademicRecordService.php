<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicRecordService
{
    /**
     * Get academic records for a student with filtering
     */
    public function getStudentRecords(Student $student, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $student->academicRecords()
            ->with(['unit', 'semester'])
            ->orderBy('semester_id', 'desc');

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15);
    }

    /**
     * Create or update academic record
     */
    public function createOrUpdateRecord(Student $student, array $data): AcademicRecord
    {
        return DB::transaction(function () use ($student, $data) {
            $recordData = array_merge($data, ['student_code' => $student->id]);

            // Check if record already exists
            $existingRecord = AcademicRecord::where('student_code', $student->id)
                ->where('semester_id', $data['semester_id'])
                ->where('unit_id', $data['unit_id'])
                ->first();

            if ($existingRecord) {
                $existingRecord->update($recordData);
                $record = $existingRecord;
            } else {
                $record = AcademicRecord::create($recordData);
            }

            // Recalculate GPA for the semester
            $this->calculateSemesterGPA($student, $data['semester_id']);

            // Recalculate cumulative GPA
            $this->calculateCumulativeGPA($student);

            Log::info('Academic record updated', [
                'student_code' => $student->id,
                'unit_id' => $data['unit_id'],
                'semester_id' => $data['semester_id'],
                'final_grade' => $data['final_grade'] ?? null,
            ]);

            return $record->fresh(['unit', 'semester']);
        });
    }

    /**
     * Calculate GPA for a specific semester
     */
    public function calculateSemesterGPA(Student $student, int $semesterId): float
    {
        return DB::transaction(function () use ($student, $semesterId) {
            $records = $student->academicRecords()
                ->where('semester_id', $semesterId)
                ->whereNotNull('final_grade')
                ->with('unit')
                ->get();

            if ($records->isEmpty()) {
                return 0.0;
            }

            $totalPoints = 0;
            $totalCredits = 0;

            foreach ($records as $record) {
                $gradePoints = $this->convertGradeToPoints($record->final_grade);
                $credits = $record->unit->credit_points;

                $totalPoints += $gradePoints * $credits;
                $totalCredits += $credits;
            }

            $gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;

            // Store or update GPA calculation
            GpaCalculation::updateOrCreate(
                [
                    'student_code' => $student->id,
                    'semester_id' => $semesterId,
                ],
                [
                    'gpa' => $gpa,
                    'total_credits' => $totalCredits,
                    'total_grade_points' => $totalPoints,
                    'calculation_date' => now(),
                ]
            );

            return $gpa;
        });
    }

    /**
     * Calculate cumulative GPA for student
     */
    public function calculateCumulativeGPA(Student $student): float
    {
        return DB::transaction(function () use ($student) {
            $allRecords = $student->academicRecords()
                ->whereNotNull('final_grade')
                ->with('unit')
                ->get();

            if ($allRecords->isEmpty()) {
                return 0.0;
            }

            $totalPoints = 0;
            $totalCredits = 0;

            foreach ($allRecords as $record) {
                $gradePoints = $this->convertGradeToPoints($record->final_grade);
                $credits = $record->unit->credit_points;

                $totalPoints += $gradePoints * $credits;
                $totalCredits += $credits;
            }

            $cumulativeGPA = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;

            // Update student's cumulative GPA if needed
            // This could be stored in a separate field or calculated on-demand

            Log::info('Cumulative GPA calculated', [
                'student_code' => $student->id,
                'cumulative_gpa' => $cumulativeGPA,
                'total_credits' => $totalCredits,
            ]);

            return $cumulativeGPA;
        });
    }

    /**
     * Generate transcript data for student
     */
    public function generateTranscript(Student $student): array
    {
        $records = $student->academicRecords()
            ->with(['unit', 'semester'])
            ->orderBy('semester_id')
            ->get()
            ->groupBy('semester_id');

        $transcriptData = [];
        $cumulativeCredits = 0;
        $cumulativePoints = 0;

        foreach ($records as $semesterId => $semesterRecords) {
            $semester = $semesterRecords->first()->semester;
            $semesterCredits = 0;
            $semesterPoints = 0;

            $courses = [];
            foreach ($semesterRecords as $record) {
                $gradePoints = $this->convertGradeToPoints($record->final_grade);
                $credits = $record->unit->credit_points;

                $semesterCredits += $credits;
                $semesterPoints += $gradePoints * $credits;

                $courses[] = [
                    'unit_code' => $record->unit->unit_code,
                    'unit_name' => $record->unit->unit_name,
                    'credits' => $credits,
                    'grade' => $record->final_grade,
                    'grade_points' => $gradePoints,
                ];
            }

            $semesterGPA = $semesterCredits > 0 ? round($semesterPoints / $semesterCredits, 2) : 0.0;

            $cumulativeCredits += $semesterCredits;
            $cumulativePoints += $semesterPoints;
            $cumulativeGPA = $cumulativeCredits > 0 ? round($cumulativePoints / $cumulativeCredits, 2) : 0.0;

            $transcriptData[] = [
                'semester' => $semester,
                'courses' => $courses,
                'semester_credits' => $semesterCredits,
                'semester_gpa' => $semesterGPA,
                'cumulative_credits' => $cumulativeCredits,
                'cumulative_gpa' => $cumulativeGPA,
            ];
        }

        return [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'transcript_data' => $transcriptData,
            'summary' => [
                'total_credits' => $cumulativeCredits,
                'overall_gpa' => $cumulativeCredits > 0 ? round($cumulativePoints / $cumulativeCredits, 2) : 0.0,
                'generated_at' => now(),
            ],
        ];
    }

    /**
     * Get academic performance analytics for student
     */
    public function getPerformanceAnalytics(Student $student): array
    {
        $gpaCalculations = $student->gpaCalculations()
            ->with('semester')
            ->orderBy('semester_id')
            ->get();

        $trends = [];
        $averageGPA = 0;

        if ($gpaCalculations->isNotEmpty()) {
            foreach ($gpaCalculations as $calculation) {
                $trends[] = [
                    'semester' => $calculation->semester->name,
                    'gpa' => $calculation->gpa,
                    'credits' => $calculation->total_credits,
                ];
            }

            $averageGPA = round($gpaCalculations->avg('gpa'), 2);
        }

        $totalCreditsCompleted = $student->academicRecords()
            ->whereNotNull('final_grade')
            ->join('units', 'academic_records.unit_id', '=', 'units.id')
            ->sum('units.credit_points');

        return [
            'gpa_trends' => $trends,
            'average_gpa' => $averageGPA,
            'current_gpa' => $this->calculateCumulativeGPA($student),
            'total_credits_completed' => $totalCreditsCompleted,
            'academic_standing' => $this->determineAcademicStanding($student),
        ];
    }

    /**
     * Determine academic standing based on GPA
     */
    public function determineAcademicStanding(Student $student): string
    {
        $currentGPA = $this->calculateCumulativeGPA($student);

        if ($currentGPA >= 3.5) {
            return 'honors';
        } elseif ($currentGPA >= 2.0) {
            return 'good';
        } elseif ($currentGPA >= 1.5) {
            return 'probation';
        } else {
            return 'suspension';
        }
    }

    /**
     * Convert letter grade to grade points (4.0 scale)
     */
    private function convertGradeToPoints(string $grade): float
    {
        return match (strtoupper($grade)) {
            'A+', 'A' => 4.0,
            'A-' => 3.7,
            'B+' => 3.3,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.3,
            'C' => 2.0,
            'C-' => 1.7,
            'D+' => 1.3,
            'D' => 1.0,
            'F', 'E' => 0.0,
            default => 0.0,
        };
    }

    /**
     * Get grade distribution for a unit
     */
    public function getGradeDistribution(Unit $unit, ?Semester $semester = null): array
    {
        $query = AcademicRecord::where('unit_id', $unit->id)
            ->whereNotNull('final_grade');

        if ($semester) {
            $query->where('semester_id', $semester->id);
        }

        $records = $query->get();

        $distribution = [
            'A+' => 0,
            'A' => 0,
            'A-' => 0,
            'B+' => 0,
            'B' => 0,
            'B-' => 0,
            'C+' => 0,
            'C' => 0,
            'C-' => 0,
            'D+' => 0,
            'D' => 0,
            'F' => 0,
        ];

        foreach ($records as $record) {
            $grade = strtoupper($record->final_grade);
            if (isset($distribution[$grade])) {
                $distribution[$grade]++;
            }
        }

        $total = array_sum($distribution);
        $percentages = [];

        foreach ($distribution as $grade => $count) {
            $percentages[$grade] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        }

        return [
            'distribution' => $distribution,
            'percentages' => $percentages,
            'total_students' => $total,
            'average_gpa' => $this->calculateUnitAverageGPA($unit, $semester),
        ];
    }

    /**
     * Calculate average GPA for a unit
     */
    private function calculateUnitAverageGPA(Unit $unit, ?Semester $semester = null): float
    {
        $query = AcademicRecord::where('unit_id', $unit->id)
            ->whereNotNull('final_grade');

        if ($semester) {
            $query->where('semester_id', $semester->id);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return 0.0;
        }

        $totalPoints = 0;
        foreach ($records as $record) {
            $totalPoints += $this->convertGradeToPoints($record->final_grade);
        }

        return round($totalPoints / $records->count(), 2);
    }
}
