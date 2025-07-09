<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\CurriculumUnit;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class InitialCourseRegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Registers students for their first semester courses (FALL2024)
     */
    public function run(): void
    {
        $this->command->info('📝 Registering students for FALL2024 courses...');

        // Get active students and FALL2024 semester
        $students = Student::where('status', 'active')->get();
        $semester = Semester::where('code', 'FALL2024')->first();

        if ($students->isEmpty()) {
            throw new \Exception('No active students found. Please run previous seeders first.');
        }

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Clean existing registrations for this semester
        CourseRegistration::where('semester_id', $semester->id)->delete();

        $registrationCount = 0;

        foreach ($students as $student) {
            $this->registerStudentForCourses($student, $semester);
            $registrationCount++;

            if ($registrationCount % 20 === 0) {
                $this->command->info("  Registered {$registrationCount} students...");
            }
        }

        $this->command->info("✅ Successfully registered {$registrationCount} students for FALL2024!");
    }

    private function registerStudentForCourses(Student $student, Semester $semester): void
    {
        // Get first semester units for this student's curriculum
        $firstSemesterUnits = $this->getFirstSemesterUnits($student);

        if ($firstSemesterUnits->isEmpty()) {
            $this->command->warn("No first semester units found for student {$student->student_id}");
            return;
        }

        foreach ($firstSemesterUnits as $curriculumUnit) {
            // Find available course offering for this unit
            $courseOffering = $this->findAvailableCourseOffering($curriculumUnit->unit_id, $semester->id);

            if (!$courseOffering) {
                $this->command->warn("No course offering found for unit {$curriculumUnit->unit->code}");
                continue;
            }

            // Register student for the course
            $this->createCourseRegistration($student, $courseOffering, $semester);

            // Update course offering enrollment count
            $courseOffering->increment('current_enrollment');
        }
    }

    private function getFirstSemesterUnits(Student $student)
    {
        // Get units for semester 1 from student's curriculum
        return CurriculumUnit::with('unit')
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('semester_number', 1)
            ->get();
    }

    private function findAvailableCourseOffering(int $unitId, int $semesterId): ?CourseOffering
    {
        // Find course offering with available capacity
        $offerings = CourseOffering::where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->get();

        if ($offerings->isEmpty()) {
            return null;
        }

        // Try to find offering with available capacity
        foreach ($offerings as $offering) {
            if ($offering->current_enrollment < $offering->max_capacity) {
                return $offering;
            }
        }

        // If all sections are full, return the first one (will go to waitlist)
        return $offerings->first();
    }

    private function createCourseRegistration(Student $student, CourseOffering $courseOffering, Semester $semester): void
    {
        // Determine registration status based on capacity
        $registrationStatus = 'registered';
        $notes = 'Initial registration for first semester';
        if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
            $registrationStatus = 'registered'; // Still registered, but we note the waitlist
            $courseOffering->increment('current_waitlist');
            $notes = 'Placed on waitlist due to capacity but still registered';
        }

        // Create registration record
        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $semester->id,
            'registration_status' => $registrationStatus,
            'registration_date' => $this->getRegistrationDate($semester),
            'registration_method' => 'online',
            'credit_hours' => $courseOffering->unit->credit_points,
            'final_grade' => null, // Will be set later
            'grade_points' => null, // Will be calculated later
            'attempt_number' => 1,
            'is_retake' => false,
            'drop_date' => null,
            'withdrawal_date' => null,
            'completion_date' => null,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
            'notes' => $notes,
        ]);
    }

    private function getRegistrationDate(Semester $semester): Carbon
    {
        // Registration happens during enrollment period
        $startDate = $semester->enrollment_start_date ?? Carbon::create(2024, 7, 1);
        $endDate = $semester->enrollment_end_date ?? Carbon::create(2024, 7, 31);

        // Random date within enrollment period
        $daysDiff = (int) $startDate->diffInDays($endDate);
        return $startDate->copy()->addDays(rand(0, $daysDiff));
    }
}
