<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicHold;
use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

class AcademicHoldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates academic holds for students based on their performance in FALL2024
     */
    public function run(): void
    {
        $this->command->info('⚠️ Creating academic holds for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'LIKE', '%Fall%2024%')->first();

        if (! $semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Get students who have academic records for this semester (more reliable than GPA calculations)
        $students = Student::whereHas('academicRecords', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id)
                ->where('grade_status', 'final');
        })->with(['gpaCalculations' => function ($query) use ($semester) {
            $query->where('semester_id', $semester->id)
                ->where('calculation_type', 'cumulative');
        }])->with(['academicRecords' => function ($query) use ($semester) {
            $query->where('semester_id', $semester->id);
        }])->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students with academic records found for FALL2024.');
        }

        $this->command->info("  Found {$students->count()} students with academic records...");

        // Check if academic holds already exist for this semester
        $existingHolds = AcademicHold::whereHas('student', function ($query) use ($students) {
            $query->whereIn('id', $students->pluck('id'));
        })->where('placed_date', '>=', $semester->start_date)->count();

        if ($existingHolds > 0) {
            $this->command->info("✅ Academic holds already exist for FALL2024 ({$existingHolds} found). Skipping creation.");

            return;
        }

        $holdCount = 0;

        foreach ($students as $student) {
            $holds = $this->createHoldsForStudent($student, $semester);
            $holdCount += count($holds);
        }

        $this->command->info("✅ Created {$holdCount} academic holds for FALL2024!");
    }

    private function createHoldsForStudent(Student $student, Semester $semester): array
    {
        $holds = [];

        // Get student's cumulative GPA calculation (may not exist due to our duplicate key issue)
        $gpaCalculation = $student->gpaCalculations
            ->where('semester_id', $semester->id)
            ->where('calculation_type', 'cumulative')
            ->first();

        // Check for academic performance holds (only if GPA calculation exists)
        if ($gpaCalculation) {
            $holds = array_merge($holds, $this->checkAcademicPerformanceHolds($student, $gpaCalculation, $semester));
        } else {
            // Fallback: create holds based on academic records directly
            $holds = array_merge($holds, $this->checkAcademicPerformanceHoldsFromRecords($student, $semester));
        }

        // Check for attendance holds
        $holds = array_merge($holds, $this->checkAttendanceHolds($student, $semester));

        // Check for financial holds (random for simulation)
        $holds = array_merge($holds, $this->checkFinancialHolds($student, $semester));

        // Check for prerequisite violations (for future semesters)
        $holds = array_merge($holds, $this->checkPrerequisiteHolds($student, $semester));

        return $holds;
    }

    private function checkAcademicPerformanceHolds(Student $student, GpaCalculation $gpaCalculation, Semester $semester): array
    {
        $holds = [];

        // Academic probation hold (GPA < 2.0)
        if ($gpaCalculation->gpa < 2.0) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Academic Probation Hold',
                'description' => 'Student placed on academic probation due to cumulative GPA below 2.0. Must meet with academic advisor before next enrollment.',
                'amount' => null,
                'priority' => 'high',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(7),
                'due_date' => $semester->end_date->addDays(30),
                'resolved_date' => null,
                'placed_by_user_id' => 1, // System admin
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        // Academic warning (GPA between 1.5 and 2.0)
        if ($gpaCalculation->gpa >= 1.5 && $gpaCalculation->gpa < 2.0) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Academic Warning Hold',
                'description' => 'Student issued academic warning. Mandatory academic counseling session required.',
                'amount' => null,
                'priority' => 'medium',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(5),
                'due_date' => $semester->end_date->addDays(21),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        // Excessive failures (more than 2 failed courses)
        if ($gpaCalculation->failed_courses > 2) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Excessive Course Failures',
                'description' => 'Student has failed multiple courses this semester. Academic intervention required.',
                'amount' => null,
                'priority' => 'high',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(3),
                'due_date' => $semester->end_date->addDays(14),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        return $holds;
    }

    private function checkAcademicPerformanceHoldsFromRecords(Student $student, Semester $semester): array
    {
        $holds = [];

        // Get academic records for this student and semester
        $records = $student->academicRecords->where('semester_id', $semester->id);

        if ($records->isEmpty()) {
            return $holds;
        }

        // Calculate basic GPA manually from academic records
        $totalGradePoints = 0;
        $totalCreditHours = 0;
        $failedCourses = 0;

        foreach ($records as $record) {
            if ($record->excluded_from_gpa) {
                continue;
            }

            $gradePoints = $this->getGradePoints($record->final_grade);
            $creditHours = floatval($record->courseOffering->unit->credit_points ?? 12.5);

            $totalGradePoints += $gradePoints * $creditHours;
            $totalCreditHours += $creditHours;

            if (in_array($record->final_grade, ['F', 'WF'])) {
                $failedCourses++;
            }
        }

        $gpa = $totalCreditHours > 0 ? $totalGradePoints / $totalCreditHours : 0;

        // Academic probation hold (GPA < 2.0)
        if ($gpa < 2.0) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Academic Probation Hold',
                'description' => 'Student placed on academic probation due to cumulative GPA below 2.0. Must meet with academic advisor before next enrollment.',
                'amount' => null,
                'priority' => 'high',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(7),
                'due_date' => $semester->end_date->addDays(30),
                'resolved_date' => null,
                'placed_by_user_id' => 1, // System admin
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        // Academic warning (GPA between 1.5 and 2.0)
        if ($gpa >= 1.5 && $gpa < 2.0) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Academic Warning Hold',
                'description' => 'Student issued academic warning. Mandatory academic counseling session required.',
                'amount' => null,
                'priority' => 'medium',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(5),
                'due_date' => $semester->end_date->addDays(21),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        // Excessive failures (more than 2 failed courses)
        if ($failedCourses > 2) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Excessive Course Failures',
                'description' => 'Student has failed multiple courses this semester. Academic intervention required.',
                'amount' => null,
                'priority' => 'high',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(3),
                'due_date' => $semester->end_date->addDays(14),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        return $holds;
    }

    private function getGradePoints(?string $grade): float
    {
        if ($grade === null) {
            return 0.0;
        }

        $gradeMap = [
            'A+' => 4.0,
            'A' => 4.0,
            'A-' => 3.7,
            'B+' => 3.3,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.3,
            'C' => 2.0,
            'C-' => 1.7,
            'D+' => 1.3,
            'D' => 1.0,
            'D-' => 0.7,
            'F' => 0.0,
            'WF' => 0.0,
            'I' => 0.0,
        ];

        return $gradeMap[$grade] ?? 0.0;
    }

    private function checkAttendanceHolds(Student $student, Semester $semester): array
    {
        $holds = [];

        // Get academic records with poor attendance
        $poorAttendanceRecords = AcademicRecord::where('student_code', $student->id)
            ->where('semester_id', $semester->id)
            ->where('attendance_percentage', '<', 75)
            ->get();

        if ($poorAttendanceRecords->count() > 0) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Attendance Requirement Violation',
                'description' => 'Student has not met minimum attendance requirements (75%) in one or more courses.',
                'amount' => null,
                'priority' => 'medium',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(2),
                'due_date' => $semester->end_date->addDays(14),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        return $holds;
    }

    private function checkFinancialHolds(Student $student, Semester $semester): array
    {
        $holds = [];

        // Simulate financial holds for some students (15% chance)
        if (rand(1, 100) <= 15) {
            $holdTypes = [
                [
                    'category' => 'registration',
                    'title' => 'Outstanding Tuition Fees',
                    'description' => 'Student has outstanding tuition fees that must be paid before next enrollment.',
                    'amount' => rand(500, 3000),
                    'priority' => 'high',
                ],
                [
                    'category' => 'registration',
                    'title' => 'Library Fines',
                    'description' => 'Student has outstanding library fines for overdue or damaged materials.',
                    'amount' => rand(25, 150),
                    'priority' => 'low',
                ],
                [
                    'category' => 'registration',
                    'title' => 'Parking Violations',
                    'description' => 'Student has unpaid parking violation fines.',
                    'amount' => rand(50, 200),
                    'priority' => 'low',
                ],
            ];

            $holdType = $holdTypes[array_rand($holdTypes)];

            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'financial',
                'hold_category' => $holdType['category'],
                'title' => $holdType['title'],
                'description' => $holdType['description'],
                'amount' => $holdType['amount'],
                'priority' => $holdType['priority'],
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(rand(1, 10)),
                'due_date' => $semester->end_date->addDays(rand(14, 45)),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        return $holds;
    }

    private function checkPrerequisiteHolds(Student $student, Semester $semester): array
    {
        $holds = [];

        // Simulate prerequisite violations for students who failed courses (5% chance)
        $failedRecords = AcademicRecord::where('student_code', $student->id)
            ->where('semester_id', $semester->id)
            ->where('completion_status', 'failed')
            ->get();

        if ($failedRecords->count() > 0 && rand(1, 100) <= 5) {
            $holds[] = AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'academic',
                'hold_category' => 'registration',
                'title' => 'Prerequisite Requirements Not Met',
                'description' => 'Student has not met prerequisite requirements for advanced courses due to failed foundational units.',
                'amount' => null,
                'priority' => 'medium',
                'status' => 'active',
                'placed_date' => $semester->end_date->addDays(14),
                'due_date' => null, // No specific due date
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }

        return $holds;
    }
}
