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

        // Get students (active or suspended) and FALL2024 semester
        $students = Student::whereIn('status', ['active', 'suspended'])->get();
        $semester = Semester::where('code', 'FALL2024')->first();

        if ($students->isEmpty()) {
            throw new \Exception('No students found. Please run previous seeders first.');
        }

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Check if registrations already exist for this semester
        $existingRegistrations = CourseRegistration::where('semester_id', $semester->id)->count();
        if ($existingRegistrations > 0) {
            $this->command->info("✅ Course registrations already exist for FALL2024 ({$existingRegistrations} found). Skipping creation.");
            return;
        }

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
        $eligibleUnits = $this->getFirstSemesterUnits($student);

        foreach ($eligibleUnits as $unitId) {
            // Check prerequisite requirements before registration
            if (!$this->checkPrerequisiteRequirements($student, $unitId)) {
                $this->command->warn("  Student {$student->student_id} does not meet prerequisites for unit {$unitId}");
                continue;
            }

            // Check if student has active academic holds that restrict registration
            if (!$this->checkAcademicHoldRestrictions($student, $unitId)) {
                $this->command->warn("  Student {$student->student_id} has academic holds restricting registration for unit {$unitId}");
                continue;
            }

            $courseOffering = $this->findAvailableCourseOffering($unitId, $semester->id);

            if ($courseOffering && ($courseOffering->max_capacity - $courseOffering->current_enrollment) > 0) {
                $this->createCourseRegistration($student, $courseOffering, $semester);
                $this->command->info("  ✓ Registered {$student->student_id} for {$courseOffering->unit->unit_code}");
            } else {
                $this->command->warn("  No available spots for unit {$unitId}");
            }
        }
    }

    private function getFirstSemesterUnits(Student $student): array
    {
        // Get unit IDs for semester 1 from student's curriculum
        return CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('semester_number', 1)
            ->pluck('unit_id')
            ->toArray();
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

    /**
     * Check if student meets prerequisite requirements for a unit
     */
    private function checkPrerequisiteRequirements(Student $student, int $unitId): bool
    {
        // Get all prerequisite groups for this unit
        $prerequisiteGroups = \App\Models\UnitPrerequisiteGroup::where('unit_id', $unitId)->get();

        if ($prerequisiteGroups->isEmpty()) {
            return true; // No prerequisites required
        }

        foreach ($prerequisiteGroups as $group) {
            $groupSatisfied = $this->checkPrerequisiteGroup($student, $group);

            // If any group is satisfied, student can register
            if ($groupSatisfied) {
                return true;
            }
        }

        return false; // No prerequisite groups satisfied
    }

    /**
     * Check if a prerequisite group is satisfied
     */
    private function checkPrerequisiteGroup(Student $student, $group): bool
    {
        $conditions = $group->conditions;
        $satisfiedConditions = 0;
        $totalConditions = $conditions->count();

        foreach ($conditions as $condition) {
            if ($this->checkPrerequisiteCondition($student, $condition)) {
                $satisfiedConditions++;
            }
        }

        // Check based on logic operator
        if ($group->logic_operator === 'AND') {
            return $satisfiedConditions === $totalConditions;
        } else { // OR
            return $satisfiedConditions > 0;
        }
    }

    /**
     * Check individual prerequisite condition
     */
    private function checkPrerequisiteCondition(Student $student, $condition): bool
    {
        switch ($condition->type) {
            case 'prerequisite':
                // Check if student has passed the required unit
                return $this->hasPassedUnit($student, $condition->required_unit_id);

            case 'co_requisite':
                // Check if student is currently enrolled or has passed
                return $this->hasPassedOrEnrolledUnit($student, $condition->required_unit_id);

            case 'credit_requirement':
                // Check if student has minimum credits
                return $this->hasMinimumCredits($student, $condition->required_credits);

            case 'anti_requisite':
                // Check if student has NOT taken this unit
                return !$this->hasAttemptedUnit($student, $condition->required_unit_id);

            case 'assumed_knowledge':
            case 'textual':
                // For first semester, assume these are satisfied
                return true;

            default:
                return false;
        }
    }

    /**
     * Check if student has active holds that restrict course registration
     */
    private function checkAcademicHoldRestrictions(Student $student, int $unitId): bool
    {
        $activeHolds = $student->academicHolds()->where('status', 'active')->get();

        foreach ($activeHolds as $hold) {
            // Check hold type and restrictions
            switch ($hold->hold_type) {
                case 'academic_probation':
                    // Allow registration but with restrictions on advanced courses
                    $unit = \App\Models\Unit::find($unitId);
                    if ($unit && $this->isAdvancedUnit($unit)) {
                        return false; // Block advanced units for probation students
                    }
                    break;

                case 'financial_hold':
                case 'disciplinary_hold':
                    return false; // Block all registrations

                case 'prerequisite_violation':
                    // Only block if this specific unit has unmet prerequisites
                    return $this->checkPrerequisiteRequirements($student, $unitId);
            }
        }

        return true; // No restricting holds
    }

    /**
     * Helper method to check if unit is advanced level
     */
    private function isAdvancedUnit($unit): bool
    {
        // Check if unit code indicates advanced level (e.g., 300+ level)
        if (preg_match('/(\d{3})/', $unit->unit_code, $matches)) {
            $level = (int) $matches[1];
            return $level >= 300; // 300+ level courses are advanced
        }

        return false;
    }

    /**
     * Check if student has passed a specific unit
     */
    private function hasPassedUnit(Student $student, int $unitId): bool
    {
        return $student->academicRecords()
            ->where('unit_id', $unitId)
            ->where('grade_status', 'final')
            ->where('final_letter_grade', '!=', 'F')
            ->where('final_letter_grade', '!=', 'WF')
            ->exists();
    }

    /**
     * Check if student has passed or is currently enrolled in a unit
     */
    private function hasPassedOrEnrolledUnit(Student $student, int $unitId): bool
    {
        // Check if passed
        if ($this->hasPassedUnit($student, $unitId)) {
            return true;
        }

        // Check if currently enrolled
        return $student->courseRegistrations()
            ->whereHas('courseOffering', function ($query) use ($unitId) {
                $query->where('unit_id', $unitId);
            })
            ->where('registration_status', 'registered')
            ->exists();
    }

    /**
     * Check if student has minimum credit hours
     */
    private function hasMinimumCredits(Student $student, int $requiredCredits): bool
    {
        $earnedCredits = $student->academicRecords()
            ->where('grade_status', 'final')
            ->where('final_letter_grade', '!=', 'F')
            ->where('final_letter_grade', '!=', 'WF')
            ->sum('credit_hours_earned');

        return $earnedCredits >= $requiredCredits;
    }

    /**
     * Check if student has attempted a unit (regardless of outcome)
     */
    private function hasAttemptedUnit(Student $student, int $unitId): bool
    {
        return $student->academicRecords()
            ->where('unit_id', $unitId)
            ->exists();
    }
}
