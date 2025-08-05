<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\Semester;
use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GPACalculationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Calculates and stores GPA for FALL2024 semester
     */
    public function run(): void
    {
        $this->command->info('📊 Calculating GPAs for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'LIKE', '%Fall%2024%')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Get unique students who have academic records for this semester
        $students = Student::whereHas('academicRecords', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id)
                ->where('grade_status', 'final')
                ->where('excluded_from_gpa', false);
        })->distinct()->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students with academic records found for FALL2024.');
        }

        // Check if GPA calculations already exist for this semester
        $existingCalculations = GpaCalculation::where('semester_id', $semester->id)->count();
        if ($existingCalculations > 0) {
            $this->command->info("✅ GPA calculations already exist for FALL2024 ({$existingCalculations} found). Skipping creation.");
            return;
        }

        $calculationCount = 0;
        $processedStudentIds = [];

        foreach ($students as $student) {
            // Check for duplicates
            if (in_array($student->id, $processedStudentIds)) {
                $this->command->warn("  Skipping duplicate student ID: {$student->id}");
                continue;
            }

            $processedStudentIds[] = $student->id;

            try {
                $this->calculateStudentGPA($student, $semester);
                $calculationCount++;

                if ($calculationCount % 20 === 0) {
                    $this->command->info("  Calculated GPA for {$calculationCount} students...");
                }
            } catch (\Exception $e) {
                $this->command->error("  Failed to calculate GPA for student {$student->id}: " . $e->getMessage());
                throw $e; // Re-throw to stop execution
            }
        }

        $this->command->info("✅ Calculated GPAs for {$calculationCount} students in FALL2024!");
    }

    private function calculateStudentGPA(Student $student, Semester $semester): void
    {
        $this->command->info("    Processing student {$student->id}...");

        // Use database transaction to ensure atomicity
        DB::transaction(function () use ($student, $semester) {
            // Get all academic records for this student up to this semester
            $allRecords = AcademicRecord::where('student_code', $student->id)
                ->where('grade_status', 'final')
                ->where('excluded_from_gpa', false)
                ->orderBy('semester_id')
                ->get();

            // Get records for current semester only
            $semesterRecords = $allRecords->where('semester_id', $semester->id);

            if ($semesterRecords->isEmpty()) {
                $this->command->info("      No academic records found for student {$student->id}");
                return;
            }

            $this->command->info("      Found {$semesterRecords->count()} semester records for student {$student->id}");

            // Calculate semester GPA
            $semesterGPA = $this->calculateGPA($semesterRecords);

            // Calculate cumulative GPA (all semesters up to current)
            $cumulativeGPA = $this->calculateGPA($allRecords);

            // Calculate additional metrics
            $metrics = $this->calculateAdditionalMetrics($allRecords, $semesterRecords);

            // Check if semester GPA already exists
            $existingSemester = GpaCalculation::where([
                'student_code' => $student->id,
                'semester_id' => $semester->id,
                'calculation_type' => 'semester',
            ])->first();

            $this->command->info("      Existing semester GPA record: " . ($existingSemester ? 'YES (ID: ' . $existingSemester->id . ')' : 'NO'));

            try {
                // Check for existing record first
                $semesterRecord = GpaCalculation::where([
                    'student_code' => $student->id,
                    'semester_id' => $semester->id,
                    'calculation_type' => 'semester',
                ])->first();

                $semesterData = [
                    'student_code' => $student->id,
                    'semester_id' => $semester->id,
                    'calculation_type' => 'semester',
                    'program_id' => $student->program_id,
                    'gpa' => $semesterGPA['gpa'],
                    'quality_points' => $semesterGPA['quality_points'],
                    'credit_hours_attempted' => $semesterGPA['credit_hours_attempted'],
                    'credit_hours_earned' => $semesterGPA['credit_hours_earned'],
                    'credit_hours_gpa' => $semesterGPA['credit_hours_gpa'],
                    'total_courses' => $semesterRecords->count(),
                    'completed_courses' => $semesterRecords->where('completion_status', 'completed')->count(),
                    'failed_courses' => $semesterRecords->where('completion_status', 'failed')->count(),
                    'academic_standing' => $this->determineAcademicStanding($cumulativeGPA['gpa']),
                    'academic_year' => $this->getAcademicYear($semester),
                    'semester_type' => $this->getSemesterType($semester),
                    'required_gpa' => 2.0,
                    'meets_gpa_requirement' => $cumulativeGPA['gpa'] >= 2.0,
                    'dean_list_eligible' => $semesterGPA['gpa'] >= 3.5,
                    'honors_eligible' => $cumulativeGPA['gpa'] >= 3.8,
                    'graduation_honors_eligible' => $cumulativeGPA['gpa'] >= 3.9,
                    'calculated_at' => now(),
                    'is_current' => true,
                ] + $metrics;

                if ($semesterRecord) {
                    $semesterRecord->update($semesterData);
                    $this->command->info("      Updated existing semester GPA record ID: {$semesterRecord->id}");
                } else {
                    try {
                        $semesterRecord = GpaCalculation::create($semesterData);
                        $this->command->info("      Created new semester GPA record ID: {$semesterRecord->id}");
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        // If we get a duplicate key error, try to find the existing record again
                        $this->command->warn("      Duplicate key for semester GPA, retrying...");
                        $semesterRecord = GpaCalculation::where([
                            'student_code' => $student->id,
                            'semester_id' => $semester->id,
                            'calculation_type' => 'semester',
                        ])->first();
                        if ($semesterRecord) {
                            $semesterRecord->update($semesterData);
                            $this->command->info("      Updated existing semester GPA record on retry ID: {$semesterRecord->id}");
                        } else {
                            $this->command->error("      Could not create or find semester GPA record for student {$student->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("      Failed to create semester GPA for student {$student->id}: " . $e->getMessage());
                // Don't throw - continue with other students
                return;
            }

            // Check if cumulative GPA already exists
            $existingCumulative = GpaCalculation::where([
                'student_code' => $student->id,
                'semester_id' => $semester->id,
                'calculation_type' => 'cumulative',
            ])->first();

            $this->command->info("      Existing cumulative GPA record: " . ($existingCumulative ? 'YES (ID: ' . $existingCumulative->id . ')' : 'NO'));

            try {
                // Check for existing record first
                $cumulativeRecord = GpaCalculation::where([
                    'student_code' => $student->id,
                    'semester_id' => $semester->id,
                    'calculation_type' => 'cumulative',
                ])->first();

                $cumulativeData = [
                    'student_code' => $student->id,
                    'semester_id' => $semester->id,
                    'calculation_type' => 'cumulative',
                    'program_id' => $student->program_id,
                    'gpa' => $cumulativeGPA['gpa'],
                    'quality_points' => $cumulativeGPA['quality_points'],
                    'credit_hours_attempted' => $cumulativeGPA['credit_hours_attempted'],
                    'credit_hours_earned' => $cumulativeGPA['credit_hours_earned'],
                    'credit_hours_gpa' => $cumulativeGPA['credit_hours_gpa'],
                    'total_courses' => $allRecords->count(),
                    'completed_courses' => $allRecords->where('completion_status', 'completed')->count(),
                    'failed_courses' => $allRecords->where('completion_status', 'failed')->count(),
                    'academic_standing' => $this->determineAcademicStanding($cumulativeGPA['gpa']),
                    'academic_year' => $this->getAcademicYear($semester),
                    'semester_type' => $this->getSemesterType($semester),
                    'required_gpa' => 2.0,
                    'meets_gpa_requirement' => $cumulativeGPA['gpa'] >= 2.0,
                    'dean_list_eligible' => $semesterGPA['gpa'] >= 3.5,
                    'honors_eligible' => $cumulativeGPA['gpa'] >= 3.8,
                    'graduation_honors_eligible' => $cumulativeGPA['gpa'] >= 3.9,
                    'calculated_at' => now(),
                    'is_current' => true,
                ] + $this->calculateAdditionalMetrics($allRecords, $allRecords);

                if ($cumulativeRecord) {
                    $cumulativeRecord->update($cumulativeData);
                    $this->command->info("      Updated existing cumulative GPA record ID: {$cumulativeRecord->id}");
                } else {
                    try {
                        $cumulativeRecord = GpaCalculation::create($cumulativeData);
                        $this->command->info("      Created new cumulative GPA record ID: {$cumulativeRecord->id}");
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        // If we get a duplicate key error, try to find the existing record again
                        $this->command->warn("      Duplicate key for cumulative GPA, retrying...");
                        $cumulativeRecord = GpaCalculation::where([
                            'student_code' => $student->id,
                            'semester_id' => $semester->id,
                            'calculation_type' => 'cumulative',
                        ])->first();
                        if ($cumulativeRecord) {
                            $cumulativeRecord->update($cumulativeData);
                            $this->command->info("      Updated existing cumulative GPA record on retry ID: {$cumulativeRecord->id}");
                        } else {
                            $this->command->error("      Could not create or find cumulative GPA record for student {$student->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("      Failed to create cumulative GPA for student {$student->id}: " . $e->getMessage());
                // Don't throw - continue with other students
                return;
            }
        });
    }

    private function calculateGPA($records): array
    {
        if ($records->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_hours_attempted' => 0,
                'credit_hours_earned' => 0,
                'credit_hours_gpa' => 0,
            ];
        }

        $totalQualityPoints = $records->sum('quality_points');
        $totalCreditHours = $records->sum('credit_hours');
        $earnedCreditHours = $records->where('completion_status', 'completed')->sum('credit_hours');

        $gpa = $totalCreditHours > 0 ? $totalQualityPoints / $totalCreditHours : 0.0;

        return [
            'gpa' => round($gpa, 2),
            'quality_points' => round($totalQualityPoints, 2),
            'credit_hours_attempted' => $totalCreditHours,
            'credit_hours_earned' => $earnedCreditHours,
            'credit_hours_gpa' => $totalCreditHours, // For GPA calculation
        ];
    }

    private function calculateAdditionalMetrics($allRecords, $semesterRecords): array
    {
        // Grade distribution
        $gradeDistribution = $semesterRecords->groupBy('final_letter_grade')->map->count();

        return [
            'a_grades' => $gradeDistribution->get('HD', 0),
            'b_grades' => $gradeDistribution->get('D', 0),
            'c_grades' => $gradeDistribution->get('C', 0),
            'd_grades' => $gradeDistribution->get('P', 0),
            'f_grades' => $gradeDistribution->get('N', 0),
            'withdrawn_courses' => 0, // Withdrawals
            'incomplete_courses' => 0, // Incompletes
        ];
    }

    private function determineAcademicStanding(float $gpa): string
    {
        if ($gpa >= 3.8) {
            return 'excellent';
        } elseif ($gpa >= 3.5) {
            return 'excellent';
        } elseif ($gpa >= 2.0) {
            return 'good';
        } elseif ($gpa >= 1.5) {
            return 'warning';
        } else {
            return 'probation';
        }
    }

    private function getAcademicYear(Semester $semester): string
    {
        // Extract year from semester code (e.g., FALL2024 -> 2024)
        preg_match('/(\d{4})/', $semester->code, $matches);
        return $matches[1] ?? '2024';
    }

    private function getSemesterType(Semester $semester): string
    {
        $code = strtoupper($semester->code);

        if (str_contains($code, 'FALL')) {
            return 'fall';
        } elseif (str_contains($code, 'SPRING')) {
            return 'spring';
        } elseif (str_contains($code, 'SUMMER')) {
            return 'summer';
        } else {
            return 'other';
        }
    }
}
