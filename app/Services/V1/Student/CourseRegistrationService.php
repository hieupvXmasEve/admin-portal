<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Exceptions\BusinessLogicException;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseRegistrationService
{
    public function __construct(
        protected ConflictDetectionService $conflictDetectionService,
        protected EnrollmentCapacityService $capacityService,
        protected PrerequisiteValidationService $prerequisiteService
    ) {}

    /**
     * Get available courses for registration
     */
    public function getAvailableCourses(Student $student, array $filters = []): Collection
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            throw new BusinessLogicException('No active semester for registration');
        }

        // B1: Lấy course_offering (1 section mỗi môn)
        $courseOfferings = CourseOffering::where('semester_id', $currentSemester->id)
            ->where('is_active', true)
            ->with([
                'curriculumUnit.unit',
                'lecture',
                'classSessions.room',
                'courseRegistrations' => function ($q) {
                    $q->where('registration_status', 'registered');
                },
            ])
            ->get()
            ->unique('curriculum_unit_id') // giữ lại 1 section/môn
            ->values();
        Log::info('Course offerings: '.json_encode($courseOfferings));
        // B2: Lấy danh sách curriculum_unit_id mà sinh viên đã đăng ký (không lấy course_offering_id nữa)
        $registeredCurriculumUnitIds = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->whereIn('registration_status', ['registered', 'pending', 'confirmed'])
            ->with('courseOffering') // để truy cập curriculum_unit_id
            ->get()
            ->pluck('courseOffering.curriculum_unit_id')
            ->filter() // loại null (phòng trường hợp course_offering bị xóa)
            ->unique()
            ->toArray();
        Log::info('Registered curriculum unit IDs: '.json_encode($registeredCurriculumUnitIds));

        // B3: Loại những môn đã đăng ký (dù khác section)
        return $courseOfferings->reject(function ($offering) use ($registeredCurriculumUnitIds) {
            return in_array($offering->curriculum_unit_id, $registeredCurriculumUnitIds);
        })->map(function ($offering) use ($student) {
            return $this->formatCourseOfferingForRegistration($offering, $student);
        });
    }

    /**
     * Register student for a course
     */
    public function registerForCourse(Student $student, int $courseOfferingId): CourseRegistration
    {
        $courseOffering = CourseOffering::with([
            'curriculumUnit.unit',
            'classSessions',
            'courseRegistrations',
        ])->findOrFail($courseOfferingId);

        // Validate registration
        $this->validateRegistration($student, $courseOffering);

        return DB::transaction(function () use ($student, $courseOffering) {
            // Create registration record
            $registration = CourseRegistration::create([
                'student_code' => $student->id,
                'course_offering_id' => $courseOffering->id,
                'semester_id' => $courseOffering->semester_id,
                'registration_status' => 'registered',
                'registration_date' => now(),
                'credit_hours' => (float) $courseOffering->curriculumUnit->unit->credit_points,
                'registration_method' => 'online',
            ]);

            // Update enrollment capacity
            $this->capacityService->updateEnrollmentCount($courseOffering);

            return $registration;
        });
    }

    /**
     * Drop a course registration
     */
    public function dropCourse(Student $student, CourseRegistration $registration): bool
    {
        if ($registration->student_code !== $student->id) {
            throw new BusinessLogicException('You can only drop your own registrations');
        }

        if (! in_array($registration->registration_status, ['registered', 'pending'])) {
            throw new BusinessLogicException('Cannot drop course with status: '.$registration->registration_status);
        }

        // Check drop deadline
        $currentSemester = Semester::where('is_active', true)->first();
        if ($currentSemester && $currentSemester->drop_deadline && now()->isAfter($currentSemester->drop_deadline)) {
            throw new BusinessLogicException('Drop deadline has passed');
        }

        return DB::transaction(function () use ($registration) {
            $courseOffering = $registration->courseOffering;

            // Update registration status
            $registration->update([
                'registration_status' => 'dropped',
                'drop_date' => now(),
            ]);

            // Update enrollment capacity
            $this->capacityService->updateEnrollmentCount($courseOffering);

            return true;
        });
    }

    /**
     * Get student's current registrations
     */
    public function getStudentRegistrations(Student $student, ?int $semesterId = null): Collection
    {
        $query = $student->courseRegistrations()
            ->with([
                'courseOffering.curriculumUnit.unit',
                'courseOffering.lecture',
                'courseOffering.classSessions.room',
                'semester',
            ]);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        } else {
            // Get current semester registrations
            $currentSemester = Semester::where('is_active', true)->first();
            if ($currentSemester) {
                $query->where('semester_id', $currentSemester->id);
            }
        }

        return $query->get()->map(function ($registration) {
            return $this->formatRegistrationForStudent($registration);
        });
    }

    /**
     * Validate course registration
     */
    protected function validateRegistration(Student $student, CourseOffering $courseOffering): void
    {
        // Check if registration is open
        $currentSemester = Semester::where('is_active', true)->first();
        if (! $currentSemester || ! $currentSemester->isRegistrationOpen()) {
            throw new BusinessLogicException('Registration is not currently open');
        }

        // Check if course is active
        if (! $courseOffering->is_active) {
            throw new BusinessLogicException('This course is not available for registration');
        }

        // Check enrollment capacity
        if (! $this->capacityService->hasAvailableCapacity($courseOffering)) {
            throw new BusinessLogicException('This course is full');
        }

        // Check prerequisites
        if (! $this->prerequisiteService->hasMetPrerequisites($student, $courseOffering)) {
            throw new BusinessLogicException('Prerequisites not met for this course');
        }

        // Check for schedule conflicts
        $conflicts = $this->conflictDetectionService->detectConflicts($student, $courseOffering);
        if (! $conflicts->isEmpty()) {
            throw new BusinessLogicException('Schedule conflict detected with existing registrations');
        }

        // Check if already registered
        $existingRegistration = $student->courseRegistrations()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'pending'])
            ->exists();

        if ($existingRegistration) {
            throw new BusinessLogicException('Already registered for this course');
        }

        // Check credit hour limits
        $this->validateCreditHourLimits($student, $courseOffering);
    }

    /**
     * Validate credit hour limits
     */
    protected function validateCreditHourLimits(Student $student, CourseOffering $courseOffering): void
    {
        $currentSemester = Semester::where('is_active', true)->first();

        $currentCredits = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->sum('credit_hours');

        $newTotalCredits = $currentCredits + $courseOffering->curriculumUnit->unit->credit_points;

        // Standard limits (can be made configurable)
        $maxCredits = 100; // Maximum credits per semester
        // $minCredits = 12; // Minimum for full-time status

        if ($newTotalCredits > $maxCredits) {
            throw new BusinessLogicException("Registration would exceed maximum credit limit of {$maxCredits}");
        }
    }

    /**
     * Format course offering for registration display
     */
    protected function formatCourseOfferingForRegistration(CourseOffering $offering, Student $student): array
    {
        $enrolledCount = $offering->courseRegistrations->count();
        $availableSpots = max(0, $offering->max_capacity - $enrolledCount);

        return [
            'id' => $offering->id,
            'unit' => [
                'code' => $offering->curriculumUnit->unit->code,
                'name' => $offering->curriculumUnit->unit->name,
                'description' => $offering->syllabus->description ?? $offering->curriculumUnit->unit->name,
                'credit_hours' => (float) $offering->curriculumUnit->unit->credit_points,
            ],
            'lecturer' => [
                'id' => $offering->lecture?->id,
                'name' => $offering->lecture?->display_name ?? $offering->lecture?->full_name ?? null,
                'email' => $offering->lecture?->email,
            ],
            'registration_eligibility' => [
                'can_register' => $this->canRegisterForCourse($student, $offering),
                'prerequisites_met' => $this->prerequisiteService->hasMetPrerequisites($student, $offering),
                'has_conflicts' => ! $this->conflictDetectionService->detectConflicts($student, $offering)->isEmpty(),
                'capacity_available' => $availableSpots > 0,
            ],
        ];
    }

    /**
     * Format registration for student display
     */
    public function formatRegistrationForStudent(CourseRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'status' => $registration->registration_status,
            'registration_date' => $registration->registration_date?->toDateString(),
            'drop_date' => $registration->drop_date?->toDateString(),
            'unit' => [
                'code' => $registration->courseOffering->curriculumUnit->unit->code,
                'name' => $registration->courseOffering->curriculumUnit->unit->name,
                'credit_hours' => (float) $registration->credit_hours,
            ],
            'lecturer' => [
                'name' => $registration->courseOffering->lecture?->display_name ?? $registration->courseOffering->lecture?->full_name ?? null,
                'email' => $registration->courseOffering->lecture?->email,
            ],
            'semester' => [
                'name' => $registration->semester->name,
                'code' => $registration->semester->code,
            ],
        ];
    }

    /**
     * Check if student can register for course
     */
    protected function canRegisterForCourse(Student $student, CourseOffering $offering): bool
    {
        try {
            $this->validateRegistration($student, $offering);

            return true;
        } catch (BusinessLogicException $e) {
            return false;
        }
    }
}
