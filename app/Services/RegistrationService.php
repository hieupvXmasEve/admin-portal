<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\AcademicHold;
use Illuminate\Support\Facades\DB;
use Exception;

class RegistrationService
{
    /**
     * Register student for a course
     */
    public function registerForCourse(Student $student, int $courseOfferingId, string $method = 'online'): CourseRegistration
    {
        return DB::transaction(function () use ($student, $courseOfferingId, $method) {
            $courseOffering = CourseOffering::findOrFail($courseOfferingId);

            // Validate registration eligibility
            $this->validateRegistrationEligibility($student, $courseOffering);

            // Check for existing registration
            $existingRegistration = CourseRegistration::where('student_id', $student->id)
                ->where('course_offering_id', $courseOfferingId)
                ->where('semester_id', $courseOffering->semester_id)
                ->first();

            if ($existingRegistration) {
                throw new Exception('Student is already registered for this course');
            }

            // Create registration
            $registration = CourseRegistration::create([
                'student_id' => $student->id,
                'course_offering_id' => $courseOfferingId,
                'semester_id' => $courseOffering->semester_id,
                'registration_status' => 'registered',
                'registration_date' => now(),
                'registration_method' => $method,
                'credit_hours' => $courseOffering->credit_hours,

            ]);

            // Update course offering enrollment count
            $courseOffering->incrementEnrollment();
            $courseOffering->updateStatus();

            return $registration->fresh(['student', 'courseOffering', 'semester']);
        });
    }

    /**
     * Drop a course registration
     */
    public function dropCourse(CourseRegistration $registration): bool
    {
        return DB::transaction(function () use ($registration) {
            // Check if drop is allowed
            if (!$registration->canDrop()) {
                throw new Exception('Course cannot be dropped at this time');
            }

            // Update registration status
            $registration->update([
                'registration_status' => 'dropped',
                'drop_date' => now(),
            ]);

            // Update course offering enrollment count
            $registration->courseOffering->decrementEnrollment();
            $registration->courseOffering->updateStatus();

            return true;
        });
    }

    /**
     * Withdraw from a course
     */
    public function withdrawFromCourse(CourseRegistration $registration): bool
    {
        return DB::transaction(function () use ($registration) {
            // Check if withdrawal is allowed
            if (!$registration->canWithdraw()) {
                throw new Exception('Course cannot be withdrawn at this time');
            }

            // Update registration status
            $registration->update([
                'registration_status' => 'withdrawn',
                'withdrawal_date' => now(),
            ]);

            // Update course offering enrollment count
            $registration->courseOffering->decrementEnrollment();
            $registration->courseOffering->updateStatus();

            return true;
        });
    }

    /**
     * Get available courses for student registration
     */
    public function getAvailableCoursesForStudent(Student $student, int $semesterId): array
    {
        $semester = Semester::findOrFail($semesterId);

        // Get course offerings for the semester and campus
        $courseOfferings = CourseOffering::with(['unit', 'lecture'])
            ->forSemester($semesterId)
            ->forCampus($student->campus_id)
            ->availableForRegistration()
            ->get();

        // Filter out courses already registered for
        $registeredCourseIds = CourseRegistration::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->pluck('course_offering_id')
            ->toArray();

        $availableCourses = $courseOfferings->reject(function ($offering) use ($registeredCourseIds) {
            return in_array($offering->id, $registeredCourseIds);
        });

        // Check prerequisites and add eligibility information
        return $availableCourses->map(function ($offering) use ($student) {
            $eligibility = $this->checkCourseEligibility($student, $offering);

            return [
                'offering' => $offering,
                'eligible' => $eligibility['eligible'],
                'reasons' => $eligibility['reasons'],
                'available_spots' => $offering->getAvailableSpots(),
                'total_cost' => $offering->getTotalTuition(),
            ];
        })->values()->toArray();
    }

    /**
     * Get student's current registrations for a semester
     */
    public function getStudentRegistrations(Student $student, int $semesterId): array
    {
        return CourseRegistration::with(['courseOffering.curriculumUnit.unit', 'courseOffering.lecture'])
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->orderBy('registration_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Validate if student is eligible to register for a course
     */
    private function validateRegistrationEligibility(Student $student, CourseOffering $courseOffering): void
    {
        // Check student status
        if (!$student->canRegisterForCourses()) {
            throw new Exception('Student is not eligible for course registration');
        }

        // Check academic holds
        $holds = AcademicHold::forStudent($student->id)
            ->active()
            ->get();

        $registrationBlocks = $holds->filter(function ($hold) {
            return $hold->blocksRegistration();
        });

        if ($registrationBlocks->isNotEmpty()) {
            $holdTitles = $registrationBlocks->pluck('title')->implode(', ');
            throw new Exception("Registration blocked by holds: {$holdTitles}");
        }

        // Check course availability
        if (!$courseOffering->isAvailableForRegistration()) {
            throw new Exception('Course is not available for registration');
        }

        // Check prerequisites
        $eligibility = $this->checkCourseEligibility($student, $courseOffering);
        if (!$eligibility['eligible']) {
            throw new Exception('Prerequisites not met: ' . implode(', ', $eligibility['reasons']));
        }

        // Check schedule conflicts
        $conflicts = $this->checkScheduleConflicts($student, $courseOffering);
        if (!empty($conflicts)) {
            throw new Exception('Schedule conflict with: ' . implode(', ', $conflicts));
        }

        // Check credit load limits
        $this->validateCreditLoad($student, $courseOffering);
    }

    /**
     * Check if student meets course prerequisites
     */
    private function checkCourseEligibility(Student $student, CourseOffering $courseOffering): array
    {
        $eligible = true;
        $reasons = [];

        // Check prerequisites if defined
        if ($courseOffering->prerequisites) {
            $completedCourses = $this->getCompletedCourses($student);

            foreach ($courseOffering->prerequisites as $prerequisite) {
                if (!$this->hasCompletedPrerequisite($completedCourses, $prerequisite)) {
                    $eligible = false;
                    $reasons[] = "Missing prerequisite: {$prerequisite}";
                }
            }
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
        ];
    }

    /**
     * Check for schedule conflicts
     */
    private function checkScheduleConflicts(Student $student, CourseOffering $courseOffering): array
    {
        $conflicts = [];

        // Get student's current registrations for the semester
        $currentRegistrations = CourseRegistration::with('courseOffering')
            ->where('student_id', $student->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->get();

        // Check for time conflicts
        foreach ($currentRegistrations as $registration) {
            if ($this->hasTimeConflict($courseOffering->schedule, $registration->courseOffering->schedule)) {
                $conflicts[] = $registration->courseOffering->course_title;
            }
        }

        return $conflicts;
    }

    /**
     * Validate credit load limits
     */
    private function validateCreditLoad(Student $student, CourseOffering $courseOffering): void
    {
        $currentCredits = CourseRegistration::where('student_id', $student->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->sum('credit_hours');

        $newTotal = $currentCredits + $courseOffering->credit_hours;
        $maxCredits = 18; // Default maximum, could be configurable

        if ($newTotal > $maxCredits) {
            throw new Exception("Credit limit exceeded. Current: {$currentCredits}, Adding: {$courseOffering->credit_hours}, Max: {$maxCredits}");
        }
    }

    /**
     * Get completed courses for a student
     */
    private function getCompletedCourses(Student $student): array
    {
        return CourseRegistration::with('courseOffering.curriculumUnit.unit')
            ->where('student_id', $student->id)
            ->where('registration_status', 'completed')
            ->passing()
            ->get()
            ->pluck('courseOffering.unit.code')
            ->toArray();
    }

    /**
     * Check if student has completed a prerequisite
     */
    private function hasCompletedPrerequisite(array $completedCourses, string $prerequisite): bool
    {
        return in_array($prerequisite, $completedCourses);
    }

    /**
     * Check if two schedules have time conflicts
     */
    private function hasTimeConflict(array $schedule1 = null, array $schedule2 = null): bool
    {
        if (!$schedule1 || !$schedule2) {
            return false;
        }

        // TODO: Implement actual schedule conflict checking logic
        // This would compare time slots, days of week, etc.
        return false;
    }

    /**
     * Calculate student's semester GPA
     */
    public function calculateSemesterGPA(Student $student, int $semesterId): float
    {
        $registrations = CourseRegistration::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('registration_status', 'completed')
            ->whereNotNull('final_grade')
            ->get();

        if ($registrations->isEmpty()) {
            return 0.0;
        }

        $totalPoints = 0;
        $totalCredits = 0;

        foreach ($registrations as $registration) {
            $gradePoints = $registration->getGradePoints();
            $credits = $registration->credit_hours;

            $totalPoints += $gradePoints * $credits;
            $totalCredits += $credits;
        }

        return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
    }
}
