<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class Spring2025RegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Registers eligible students for SPRING2025 courses
     */
    public function run(): void
    {
        $this->command->info('📝 Registering students for SPRING2025 courses...');

        // Get SPRING2025 semester
        $semester = Semester::where('code', 'SPRING2025')->first();

        if (! $semester) {
            throw new \Exception('SPRING2025 semester not found.');
        }

        // Get eligible students (active status, no blocking holds)
        // First try to get active students without major holds
        $eligibleStudents = Student::where('status', 'active')
            ->whereDoesntHave('academicHolds', function ($query) {
                $query->where('status', 'active')
                    ->whereIn('hold_category', ['academic_suspension', 'tuition_fees']);
            })
            ->get();

        // If no active students found, try to get all students who have completed FALL2024
        if ($eligibleStudents->isEmpty()) {
            $this->command->info('No active students without holds found. Checking for students who completed FALL2024...');

            $eligibleStudents = Student::whereHas('academicRecords', function ($query) {
                $query->whereHas('semester', function ($semesterQuery) {
                    $semesterQuery->where('code', 'FALL2024');
                });
            })
                ->whereNotIn('status', ['dropped', 'graduated'])
                ->get();
        }

        if ($eligibleStudents->isEmpty()) {
            throw new \Exception('No eligible students found for SPRING2025 registration.');
        }

        // Check if registrations already exist for this semester
        $existingRegistrations = CourseRegistration::where('semester_id', $semester->id)->count();
        if ($existingRegistrations > 0) {
            $this->command->info("✅ Course registrations already exist for SPRING2025 ({$existingRegistrations} found). Skipping creation.");

            return;
        }

        $registrationCount = 0;
        $retakeCount = 0;

        foreach ($eligibleStudents as $student) {
            $result = $this->registerStudentForCourses($student, $semester);
            $registrationCount += $result['regular_registrations'];
            $retakeCount += $result['retake_registrations'];

            if ($registrationCount % 20 === 0) {
                $this->command->info("  Registered {$registrationCount} students...");
            }
        }

        $this->command->info('✅ Successfully registered students for SPRING2025!');
        $this->command->info("  📚 Regular registrations: {$registrationCount}");
        $this->command->info("  🔄 Retake registrations: {$retakeCount}");
    }

    private function registerStudentForCourses(Student $student, Semester $semester): array
    {
        $regularRegistrations = 0;
        $retakeRegistrations = 0;

        // Register for second semester units
        $secondSemesterUnits = $this->getSecondSemesterUnits($student);

        foreach ($secondSemesterUnits as $curriculumUnit) {
            if ($this->canRegisterForUnit($student, $curriculumUnit->unit_id)) {
                $courseOffering = $this->findAvailableCourseOffering($curriculumUnit->unit_id, $semester->id);

                if ($courseOffering) {
                    $this->createCourseRegistration($student, $courseOffering, $semester, false);
                    $courseOffering->increment('current_enrollment');
                    $regularRegistrations++;
                }
            }
        }

        // Register for retakes if needed
        $retakeUnits = $this->getRetakeUnits($student);

        foreach ($retakeUnits as $unitId) {
            $retakeOffering = $this->findRetakeCourseOffering($unitId, $semester->id);

            if ($retakeOffering) {
                $this->createCourseRegistration($student, $retakeOffering, $semester, true);
                $retakeOffering->increment('current_enrollment');
                $retakeRegistrations++;
            }
        }

        // Create enrollment record for the semester
        $this->createEnrollmentRecord($student, $semester);

        return [
            'regular_registrations' => $regularRegistrations,
            'retake_registrations' => $retakeRegistrations,
        ];
    }

    private function getSecondSemesterUnits(Student $student)
    {
        // Get units for semester 2 from student's curriculum
        return CurriculumUnit::with('unit')
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('semester_number', 2)
            ->get();
    }

    private function getRetakeUnits(Student $student): array
    {
        // Get units that the student failed in previous semesters
        $failedUnits = AcademicRecord::where('student_id', $student->id)
            ->where('completion_status', 'failed')
            ->where('grade_status', 'final')
            ->pluck('unit_id')
            ->toArray();

        return $failedUnits;
    }

    private function canRegisterForUnit(Student $student, int $unitId): bool
    {
        // Check if student has already completed this unit
        $alreadyCompleted = AcademicRecord::where('student_id', $student->id)
            ->where('unit_id', $unitId)
            ->where('completion_status', 'completed')
            ->where('grade_status', 'final')
            ->exists();

        if ($alreadyCompleted) {
            return false;
        }

        // Check prerequisites (simplified - assume first year students can register for second semester)
        return true;
    }

    private function findAvailableCourseOffering(int $unitId, int $semesterId): ?CourseOffering
    {
        // Find regular course offering (not retake section)
        $offerings = CourseOffering::where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->where(function ($query) {
                $query->whereNull('section_code')
                    ->orWhere('section_code', 'not like', 'R%');
            })
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

    private function findRetakeCourseOffering(int $unitId, int $semesterId): ?CourseOffering
    {
        // Find retake course offering
        return CourseOffering::where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->where('section_code', 'like', 'R%')
            ->first();
    }

    private function createCourseRegistration(Student $student, CourseOffering $courseOffering, Semester $semester, bool $isRetake): void
    {
        // Determine registration status based on capacity
        $registrationStatus = 'registered';
        if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
            $registrationStatus = 'registered';
            $courseOffering->increment('current_waitlist');
        }

        // Calculate retake fee if applicable
        $retakeFee = $isRetake ? 500.00 : 0.00;

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $semester->id,
            'registration_status' => $registrationStatus,
            'registration_date' => $this->getRegistrationDate($semester),
            'registration_method' => 'online',
            'credit_hours' => $courseOffering->unit->credit_points,
            'final_grade' => null,
            'grade_points' => null,
            'attempt_number' => $isRetake ? 2 : 1,
            'is_retake' => $isRetake,
            'drop_date' => null,
            'withdrawal_date' => null,
            'completion_date' => null,
            'retake_fee' => $retakeFee,
            'is_retake_paid' => 'no', // Will be updated when payment is processed
            'notes' => $this->getRegistrationNotes($registrationStatus, $isRetake),
        ]);
    }

    private function createEnrollmentRecord(Student $student, Semester $semester): void
    {
        // Check if enrollment record already exists
        $existingEnrollment = \App\Models\Enrollment::where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->first();

        if (! $existingEnrollment) {
            \App\Models\Enrollment::create([
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'curriculum_version_id' => $student->curriculum_version_id,
                'semester_number' => 2, // Second semester
                'status' => 'in_progress',
                'notes' => 'Enrolled for second semester',
            ]);
        }
    }

    private function getRegistrationDate(Semester $semester): Carbon
    {
        // Registration happens during enrollment period
        $startDate = $semester->enrollment_start_date ?? Carbon::create(2025, 1, 15);
        $endDate = $semester->enrollment_end_date ?? Carbon::create(2025, 2, 15);

        // Random date within enrollment period
        $daysDiff = (int) $startDate->diffInDays($endDate);

        return $startDate->copy()->addDays(rand(0, $daysDiff));
    }

    private function getRegistrationNotes(string $status, bool $isRetake): ?string
    {
        $notes = [];

        if ($isRetake) {
            $notes[] = 'Retake registration';
        }

        if ($status === 'registered' && $isRetake) {
            $notes[] = 'Registration processed successfully';
        }

        return empty($notes) ? null : implode('. ', $notes);
    }
}
