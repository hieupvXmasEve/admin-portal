<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\CourseRegistration;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Semester;
use App\Models\Syllabus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates academic records based on assessment scores for FALL2024
     */
    public function run(): void
    {
        $this->command->info('📋 Creating academic records for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'FALL2024')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Get all course registrations for FALL2024
        $registrations = CourseRegistration::where('semester_id', $semester->id)
            ->where('registration_status', 'registered')
            ->with(['student', 'courseOffering.curriculumUnit.unit'])
            ->get();

        if ($registrations->isEmpty()) {
            throw new \Exception('No course registrations found for FALL2024.');
        }

        // Check if academic records already exist for this semester
        $existingRecords = AcademicRecord::where('semester_id', $semester->id)->count();
        if ($existingRecords > 0) {
            $this->command->info("✅ Academic records already exist for FALL2024 ({$existingRecords} found). Skipping creation.");
            return;
        }

        $recordCount = 0;

        foreach ($registrations as $registration) {
            $this->createAcademicRecord($registration, $semester);
            $recordCount++;

            if ($recordCount % 20 === 0) {
                $this->command->info("  Created {$recordCount} academic records...");
            }
        }

        $this->command->info("✅ Created {$recordCount} academic records for FALL2024!");
    }

    private function createAcademicRecord(CourseRegistration $registration, Semester $semester): void
    {
        $unit = $registration->courseOffering->unit;
        $student = $registration->student;

        // Calculate final grade from assessment scores
        $finalGrade = $this->calculateFinalGrade($registration);

        // Determine completion status
        $completionStatus = $finalGrade['percentage'] >= 50 ? 'completed' : 'failed';

        // Calculate attendance percentage
        $attendancePercentage = $this->calculateAttendancePercentage($registration);

        // Create academic record
        AcademicRecord::create([
            'student_id' => $student->id,
            'course_offering_id' => $registration->course_offering_id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id ?? 1, // Default to first campus if not set

            // Grade information
            'final_percentage' => $finalGrade['percentage'],
            'final_letter_grade' => $finalGrade['letter_grade'],
            'grade_points' => $finalGrade['grade_points'],
            'quality_points' => $finalGrade['quality_points'],
            'credit_hours' => $unit->credit_points,
            'credit_hours_earned' => $completionStatus === 'completed' ? $unit->credit_points : 0,
            'grade_status' => 'final',

            // Completion information
            'completion_status' => $completionStatus,
            'enrollment_date' => $registration->registration_date,
            'completion_date' => $completionStatus === 'completed' ? $semester->end_date : null,
            'grade_submission_date' => now()->subDays(rand(1, 7)),
            'grade_finalized_date' => now()->subDays(rand(0, 3)),

            // Attendance information
            'attendance_percentage' => $attendancePercentage,
            'total_absences' => $this->calculateTotalAbsences($registration),
            'total_class_sessions' => $this->getTotalClassSessions($registration),
            'meets_attendance_requirement' => $attendancePercentage >= 75,

            // Academic standing
            'is_repeat_course' => false,
            'attempt_number' => 1,
            'is_transfer_credit' => false,
            'excluded_from_gpa' => false,

            // Additional information
            'instructor_id' => $registration->courseOffering->lecture_id,
            'administrative_notes' => $this->generateRecordNotes($finalGrade['percentage'], $attendancePercentage),
        ]);

        // Update course registration with final grade
        $registration->update([
            'final_grade' => $finalGrade['letter_grade'],
            'completion_date' => $completionStatus === 'completed' ? $semester->end_date : null,
        ]);
    }

    private function calculateFinalGrade(CourseRegistration $registration): array
    {
        // Manually fetch syllabus for this course offering
        $syllabus = Syllabus::whereHas('curriculumUnit', function ($query) use ($registration) {
            $query->where('unit_id', $registration->courseOffering->unit_id);
        })
            ->where('is_active', true)
            ->with('assessmentComponents.details.scores')
            ->first();

        if (!$syllabus) {
            // If no syllabus found, return default passing grade
            return [
                'percentage' => 75.0,
                'letter_grade' => 'C',
                'grade_points' => 2.0,
                'quality_points' => 2.0 * (float) $registration->courseOffering->unit->credit_points,
            ];
        }

        $totalWeightedScore = 0;
        $totalWeight = 0;

        // Calculate weighted average from all assessment components
        foreach ($syllabus->assessmentComponents as $component) {
            $componentScore = 0;
            $componentWeight = 0;

            foreach ($component->details as $detail) {
                $score = $detail->scores->where('student_id', $registration->student_id)->first();

                if ($score) {
                    $componentScore += $score->percentage * $detail->weight;
                    $componentWeight += $detail->weight;
                }
            }

            if ($componentWeight > 0) {
                $componentAverage = $componentScore / $componentWeight;
                $totalWeightedScore += $componentAverage * $component->weight;
                $totalWeight += $component->weight;
            }
        }

        $finalPercentage = $totalWeight > 0 ? $totalWeightedScore / $totalWeight : 0;

        return [
            'percentage' => round($finalPercentage, 2),
            'letter_grade' => $this->calculateLetterGrade($finalPercentage),
            'grade_points' => $this->calculateGradePoints($finalPercentage),
            'quality_points' => $this->calculateQualityPoints($finalPercentage, (float) $registration->courseOffering->unit->credit_points),
        ];
    }

    private function calculateLetterGrade(float $percentage): string
    {
        if ($percentage >= 80) return 'HD'; // High Distinction
        if ($percentage >= 70) return 'D';  // Distinction
        if ($percentage >= 60) return 'C';  // Credit
        if ($percentage >= 50) return 'P';  // Pass
        return 'N'; // Fail
    }

    private function calculateGradePoints(float $percentage): float
    {
        if ($percentage >= 80) return 4.0;
        if ($percentage >= 70) return 3.0;
        if ($percentage >= 60) return 2.0;
        if ($percentage >= 50) return 1.0;
        return 0.0;
    }

    private function calculateQualityPoints(float $percentage, float $creditHours): float
    {
        return $this->calculateGradePoints($percentage) * $creditHours;
    }

    private function calculateAttendancePercentage(CourseRegistration $registration): float
    {
        $totalSessions = DB::table('class_sessions')
            ->where('course_offering_id', $registration->course_offering_id)
            ->count();

        if ($totalSessions === 0) {
            return 100.0; // No sessions recorded yet
        }

        $attendedSessions = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $registration->course_offering_id)
            ->where('attendances.student_id', $registration->student_id)
            ->whereIn('attendances.status', ['present', 'late', 'partial'])
            ->count();

        return round(($attendedSessions / $totalSessions) * 100, 2);
    }

    private function calculateTotalAbsences(CourseRegistration $registration): int
    {
        return DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $registration->course_offering_id)
            ->where('attendances.student_id', $registration->student_id)
            ->whereIn('attendances.status', ['absent'])
            ->count();
    }

    private function getTotalClassSessions(CourseRegistration $registration): int
    {
        return DB::table('class_sessions')
            ->where('course_offering_id', $registration->course_offering_id)
            ->count();
    }

    private function getHonorsDesignation(float $percentage): ?string
    {
        if ($percentage >= 90) return 'summa_cum_laude';
        if ($percentage >= 85) return 'magna_cum_laude';
        if ($percentage >= 80) return 'cum_laude';
        return null;
    }

    private function generateRecordNotes(float $percentage, float $attendancePercentage): ?string
    {
        $notes = [];

        if ($percentage >= 85) {
            $notes[] = 'Excellent academic performance';
        } elseif ($percentage < 50) {
            $notes[] = 'Failed to meet minimum requirements';
        }

        if ($attendancePercentage < 75) {
            $notes[] = 'Poor attendance record';
        } elseif ($attendancePercentage >= 95) {
            $notes[] = 'Excellent attendance';
        }

        return empty($notes) ? null : implode('. ', $notes);
    }
}
