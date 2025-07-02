<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentLifecycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('assessment_component_detail_scores')->delete();
        DB::table('attendances')->delete();
        DB::table('academic_records')->delete();
        CourseRegistration::query()->delete();

        // Get required data
        $fallSemester = Semester::where('code', 'FALL2024')->first();
        $springSemester = Semester::where('code', 'SPRING2025')->first();
        $students = Student::all();

        if (!$fallSemester || !$springSemester || $students->isEmpty()) {
            throw new \Exception('Required data not found. Please run previous seeders first.');
        }

        // Divide students into groups for different scenarios
        $this->processStudentGroups($students, $fallSemester, $springSemester);

        $this->command->info('Created student lifecycle data with various academic scenarios');
    }

    private function processStudentGroups($students, $fallSemester, $springSemester): void
    {
        $totalStudents = $students->count();

        // Group 1: Excellent students (20 students - 1/3 of 60)
        $excellentStudents = $students->take(20);

        // Group 2: Students who failed due to low scores (10 students)
        $lowScoreFailedStudents = $students->skip(20)->take(10);

        // Group 3: Students who failed due to attendance (10 students)
        $attendanceFailedStudents = $students->skip(30)->take(10);

        // Group 4: Regular students with mixed results (20 students)
        $regularStudents = $students->skip(40)->take(20);

        // Process FALL2024 semester (past semester)
        $this->processPastSemester($excellentStudents, $fallSemester, 'excellent');
        $this->processPastSemester($lowScoreFailedStudents, $fallSemester, 'low_score_failed');
        $this->processPastSemester($attendanceFailedStudents, $fallSemester, 'attendance_failed');
        $this->processPastSemester($regularStudents, $fallSemester, 'regular');

        // Process SPRING2025 semester (current semester) with prerequisite checks
        $this->processCurrentSemester($excellentStudents, $springSemester, 'excellent');
        $this->processCurrentSemester($lowScoreFailedStudents, $springSemester, 'prerequisite_failed');
        $this->processCurrentSemester($attendanceFailedStudents, $springSemester, 'prerequisite_failed');
        $this->processCurrentSemester($regularStudents, $springSemester, 'regular');
    }

    private function processPastSemester($students, $fallSemester, string $studentType): void
    {
        $courseOfferings = CourseOffering::where('semester_id', $fallSemester->id)->get();

        foreach ($students as $student) {
            // Each student enrolls in 3-4 courses
            $courseCount = rand(3, 4);
            $selectedCourses = $courseOfferings->random($courseCount);

            foreach ($selectedCourses as $courseOffering) {
                $registration = $this->createCourseRegistration($student, $courseOffering, $fallSemester);

                // Create academic records, attendances, and scores based on student type
                $this->createStudentData($student, $registration, $courseOffering, $studentType, true);
            }
        }
    }

    private function processCurrentSemester($students, $springSemester, string $studentType): void
    {
        $courseOfferings = CourseOffering::where('semester_id', $springSemester->id)->get();

        foreach ($students as $student) {
            if ($studentType === 'prerequisite_failed') {
                // Try to register for advanced courses without prerequisites
                $this->createPrerequisiteViolationScenario($student, $springSemester);
            } else {
                // Regular enrollment for eligible students
                $courseCount = rand(2, 4);
                $selectedCourses = $courseOfferings->random($courseCount);

                foreach ($selectedCourses as $courseOffering) {
                    $registration = $this->createCourseRegistration($student, $courseOffering, $springSemester);

                    // Create partial data for current semester (no final grades yet)
                    $this->createStudentData($student, $registration, $courseOffering, $studentType, false);
                }
            }
        }

        // Create credit requirement scenarios
        $this->createCreditRequirementScenarios($students->take(10), $springSemester);
    }

    private function createCourseRegistration($student, $courseOffering, $semester): CourseRegistration
    {
        return CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $semester->id,
            'registration_status' => $semester->code === 'FALL2024' ? 'completed' : 'registered',
            'registration_date' => $semester->enrollment_start_date,
            'registration_method' => 'online',
            'credit_hours' => $courseOffering->unit->credit_points,
            'attempt_number' => 1,
            'is_retake' => false,
        ]);
    }

    private function createStudentData($student, $registration, $courseOffering, string $studentType, bool $isCompleted): void
    {
        if ($isCompleted) {
            // Create academic record for completed semester
            $this->createAcademicRecord($student, $registration, $courseOffering, $studentType);

            // Create attendance records
            $this->createAttendanceRecords($student, $registration, $studentType);

            // Create assessment scores
            $this->createAssessmentScores($student, $registration, $courseOffering, $studentType);
        } else {
            // For current semester, only create partial attendance
            $this->createPartialAttendanceRecords($student, $registration);
        }
    }

    private function createAcademicRecord($student, $registration, $courseOffering, string $studentType): void
    {
        $gradeData = $this->getGradeByStudentType($studentType);
        $attendanceData = $this->getAttendanceByStudentType($studentType);

        DB::table('academic_records')->insert([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $registration->semester_id,
            'unit_id' => $courseOffering->unit_id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,
            'final_percentage' => $gradeData['percentage'],
            'final_letter_grade' => $gradeData['letter'],
            'grade_points' => $gradeData['points'],
            'quality_points' => $gradeData['points'] * $courseOffering->unit->credit_points,
            'credit_hours' => $courseOffering->unit->credit_points,
            'credit_hours_earned' => $gradeData['passed'] ? $courseOffering->unit->credit_points : 0,
            'grade_status' => 'final',
            'completion_status' => $gradeData['passed'] ? 'completed' : 'failed',
            'enrollment_date' => $registration->registration_date,
            'completion_date' => '2024-12-15',
            'grade_submission_date' => '2024-12-20',
            'grade_finalized_date' => '2024-12-22',
            'attendance_percentage' => $attendanceData['percentage'],
            'total_absences' => $attendanceData['absences'],
            'total_class_sessions' => 24, // Typical semester
            'meets_attendance_requirement' => $attendanceData['meets_requirement'],
            'instructor_id' => $courseOffering->lecture_id,
            'grade_submitted_by_lecture_id' => $courseOffering->lecture_id,
            'grade_approved_by_lecture_id' => $courseOffering->lecture_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update course registration with final grade
        $registration->update([
            'final_grade' => $gradeData['letter'],
            'grade_points' => $gradeData['points'],
            'completion_date' => '2024-12-15',
        ]);
    }

    private function createAttendanceRecords($student, $registration, string $studentType): void
    {
        $totalSessions = 24; // Typical semester
        $attendanceData = $this->getAttendanceByStudentType($studentType);
        $attendedSessions = round($totalSessions * $attendanceData['percentage'] / 100);

        for ($session = 1; $session <= $totalSessions; $session++) {
            $status = $session <= $attendedSessions ? 'present' : 'absent';

            DB::table('attendances')->insert([
                'class_session_id' => $this->getOrCreateClassSession($registration->course_offering_id, $session),
                'student_id' => $student->id,
                'recorded_by_lecture_id' => $registration->courseOffering->lecture_id,
                'status' => $status,
                'check_in_time' => $status === 'present' ?
                    Carbon::parse('2024-08-01')->addWeeks($session)->setTime(8, rand(0, 30)) : null,
                'recording_method' => 'manual',
                'is_verified' => true,
                'affects_grade' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createAssessmentScores($student, $registration, $courseOffering, string $studentType): void
    {
        $components = AssessmentComponent::whereHas('syllabus', function ($query) use ($courseOffering) {
            $query->where('unit_id', $courseOffering->unit_id)
                ->where('semester_id', $courseOffering->semester_id);
        })->get();

        foreach ($components as $component) {
            $details = AssessmentComponentDetail::where('component_id', $component->id)->get();

            if ($details->isNotEmpty()) {
                foreach ($details as $detail) {
                    $this->createDetailScore($student, $detail, $courseOffering, $studentType);
                }
            } else {
                // Component without details, create direct score
                $this->createComponentScore($student, $component, $courseOffering, $studentType);
            }
        }
    }

    private function createDetailScore($student, $detail, $courseOffering, string $studentType): void
    {
        $scoreData = $this->getScoreByStudentType($studentType, $detail->name);

        DB::table('assessment_component_detail_scores')->insert([
            'assessment_component_detail_id' => $detail->id,
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'graded_by_lecture_id' => $courseOffering->lecture_id,
            'points_earned' => $scoreData['points'],
            'percentage_score' => $scoreData['percentage'],
            'letter_grade' => $scoreData['letter'],
            'gpa_points' => $scoreData['gpa_points'],
            'submitted_at' => Carbon::parse('2024-10-01')->addDays(rand(1, 30)),
            'graded_at' => Carbon::parse('2024-10-15')->addDays(rand(1, 30)),
            'status' => 'graded',
            'score_status' => 'final',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPrerequisiteViolationScenario($student, $springSemester): void
    {
        // Try to register for advanced units that require prerequisites
        $advancedUnits = Unit::whereIn('code', ['COS20007', 'COS30019', 'SWE30003'])->get();

        foreach ($advancedUnits->take(2) as $unit) {
            $courseOffering = CourseOffering::where('semester_id', $springSemester->id)
                ->where('unit_id', $unit->id)
                ->first();

            if ($courseOffering) {
                // This would normally be blocked by validation, but we create the scenario for testing
                $registration = CourseRegistration::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'semester_id' => $springSemester->id,
                    'registration_status' => 'registered', // Would be blocked in real system
                    'registration_date' => $springSemester->enrollment_start_date,
                    'registration_method' => 'online',
                    'credit_hours' => $courseOffering->unit->credit_points,
                    'notes' => 'Prerequisite check failed - for testing purposes',
                ]);
            }
        }
    }

    private function createCreditRequirementScenarios($students, $springSemester): void
    {
        $specialUnits = Unit::whereIn('code', ['CAPSTONE_PROJECT', 'INTERNSHIP', 'ADVANCED_RESEARCH'])->get();

        foreach ($students->take(3) as $index => $student) {
            $unit = $specialUnits->get($index);
            if (!$unit) continue;

            $courseOffering = CourseOffering::where('semester_id', $springSemester->id)
                ->where('unit_id', $unit->id)
                ->first();

            if ($courseOffering) {
                // This would be blocked by credit requirement validation
                CourseRegistration::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'semester_id' => $springSemester->id,
                    'registration_status' => 'registered',
                    'registration_date' => $springSemester->enrollment_start_date,
                    'registration_method' => 'online',
                    'credit_hours' => $courseOffering->unit->credit_points,
                    'notes' => "Credit requirement check failed for {$unit->code} - for testing purposes",
                ]);
            }
        }
    }

    private function getGradeByStudentType(string $studentType): array
    {
        return match ($studentType) {
            'excellent' => [
                'percentage' => rand(85, 100),
                'letter' => collect(['A+', 'A', 'A-'])->random(),
                'points' => rand(37, 40) / 10,
                'passed' => true
            ],
            'low_score_failed' => [
                'percentage' => rand(30, 49),
                'letter' => 'F',
                'points' => 0.0,
                'passed' => false
            ],
            'attendance_failed' => [
                'percentage' => rand(50, 70), // Would pass but failed due to attendance
                'letter' => 'F',
                'points' => 0.0,
                'passed' => false
            ],
            'regular' => [
                'percentage' => rand(60, 84),
                'letter' => collect(['B+', 'B', 'B-', 'C+', 'C', 'D+', 'D'])->random(),
                'points' => rand(20, 33) / 10,
                'passed' => true
            ],
            default => [
                'percentage' => rand(70, 85),
                'letter' => 'B',
                'points' => 3.0,
                'passed' => true
            ]
        };
    }

    private function getAttendanceByStudentType(string $studentType): array
    {
        return match ($studentType) {
            'excellent' => [
                'percentage' => rand(95, 100),
                'absences' => rand(0, 1),
                'meets_requirement' => true
            ],
            'attendance_failed' => [
                'percentage' => rand(50, 79), // Below 80% requirement
                'absences' => rand(5, 12),
                'meets_requirement' => false
            ],
            'low_score_failed' => [
                'percentage' => rand(85, 95),
                'absences' => rand(1, 3),
                'meets_requirement' => true
            ],
            'regular' => [
                'percentage' => rand(80, 94),
                'absences' => rand(1, 4),
                'meets_requirement' => true
            ],
            default => [
                'percentage' => rand(80, 90),
                'absences' => rand(2, 4),
                'meets_requirement' => true
            ]
        };
    }

    private function getScoreByStudentType(string $studentType, string $assessmentName): array
    {
        $baseScore = $this->getGradeByStudentType($studentType)['percentage'];
        $variation = rand(-5, 5); // Small variation per assessment
        $percentage = max(0, min(100, $baseScore + $variation));

        return [
            'points' => $percentage,
            'percentage' => $percentage,
            'letter' => $this->percentageToLetterGrade($percentage),
            'gpa_points' => $this->percentageToGPA($percentage)
        ];
    }

    private function percentageToLetterGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 97 => 'A+',
            $percentage >= 93 => 'A',
            $percentage >= 90 => 'A-',
            $percentage >= 87 => 'B+',
            $percentage >= 83 => 'B',
            $percentage >= 80 => 'B-',
            $percentage >= 77 => 'C+',
            $percentage >= 73 => 'C',
            $percentage >= 70 => 'C-',
            $percentage >= 67 => 'D+',
            $percentage >= 60 => 'D',
            default => 'F'
        };
    }

    private function percentageToGPA(float $percentage): float
    {
        return match (true) {
            $percentage >= 97 => 4.0,
            $percentage >= 93 => 4.0,
            $percentage >= 90 => 3.7,
            $percentage >= 87 => 3.3,
            $percentage >= 83 => 3.0,
            $percentage >= 80 => 2.7,
            $percentage >= 77 => 2.3,
            $percentage >= 73 => 2.0,
            $percentage >= 70 => 1.7,
            $percentage >= 67 => 1.3,
            $percentage >= 60 => 1.0,
            default => 0.0
        };
    }

    private function createPartialAttendanceRecords($student, $registration): void
    {
        // Create attendance for current semester (partial)
        $sessionsCompleted = rand(5, 10); // Out of expected ~15 sessions

        for ($session = 1; $session <= $sessionsCompleted; $session++) {
            DB::table('attendances')->insert([
                'class_session_id' => $this->getOrCreateClassSession($registration->course_offering_id, $session),
                'student_id' => $student->id,
                'recorded_by_lecture_id' => $registration->courseOffering->lecture_id,
                'status' => rand(0, 9) < 8 ? 'present' : 'absent', // 80% attendance
                'check_in_time' => Carbon::parse('2025-02-01')->addWeeks($session)->setTime(8, rand(0, 30)),
                'recording_method' => 'manual',
                'is_verified' => true,
                'affects_grade' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function getOrCreateClassSession($courseOfferingId, $sessionNumber): int
    {
        // Check if class session already exists
        $existingSession = DB::table('class_sessions')
            ->where('course_offering_id', $courseOfferingId)
            ->where('sequence_number', $sessionNumber)
            ->first();

        if ($existingSession) {
            return $existingSession->id;
        }

        // Create new class session
        $courseOffering = \App\Models\CourseOffering::find($courseOfferingId);
        $sessionDate = \Carbon\Carbon::parse('2024-08-01')->addWeeks($sessionNumber - 1);

        $startTime = $courseOffering->schedule_time_start ?? '08:00:00';
        $endTime = $courseOffering->schedule_time_end ?? '10:00:00';

        // Ensure end time is after start time
        if ($startTime >= $endTime) {
            $endTime = \Carbon\Carbon::parse($startTime)->addHours(2)->format('H:i:s');
        }

        return DB::table('class_sessions')->insertGetId([
            'course_offering_id' => $courseOfferingId,
            'sequence_number' => $sessionNumber,
            'session_title' => "Session {$sessionNumber}",
            'session_date' => $sessionDate->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_type' => 'lecture',
            'delivery_mode' => $courseOffering->delivery_mode ?? 'in_person',
            'status' => 'completed',
            'attendance_required' => true,
            'attendance_tracking_enabled' => true,
            'expected_attendees' => $courseOffering->max_capacity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createComponentScore($student, $component, $courseOffering, string $studentType): void
    {
        // Fallback for components without details
        $scoreData = $this->getScoreByStudentType($studentType, $component->name);

        // This would go to a direct component scores table if it existed
        // For now, we'll skip or adapt to the detail scores table structure
    }
}
