<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\Unit;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseRetakeService
{
    /**
     * Register student for course retake
     */
    public function registerRetake(Student $student, CourseOffering $offering, array $data): CourseRegistration
    {
        return DB::transaction(function () use ($student, $offering, $data) {
            $this->validateRetakeEligibility($student, $offering);

            $originalRegistration = $this->findOriginalRegistration($student, $offering->unit_id);
            $attemptNumber = $this->getNextAttemptNumber($student, $offering->unit_id);

            $retakeRegistration = CourseRegistration::create([
                'student_code' => $student->id,
                'course_offering_id' => $offering->id,
                'registration_date' => now(),
                'status' => 'registered',
                'is_retake' => true,
                'original_registration_id' => $originalRegistration?->id,
                'retake_reason' => $data['reason'] ?? 'Grade improvement',
                'attempt_number' => $attemptNumber,
            ]);

            Log::info('Course retake registered', [
                'student_code' => $student->id,
                'unit_id' => $offering->unit_id,
                'attempt_number' => $attemptNumber,
            ]);

            return $retakeRegistration->fresh(['courseOffering.curriculumUnit.unit', 'student']);
        });
    }

    /**
     * Get retake history for a student
     */
    public function getRetakeHistory(Student $student): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $student->courseRegistrations()
            ->where('is_retake', true)
            ->with(['courseOffering.curriculumUnit.unit', 'courseOffering.semester'])
            ->orderBy('registration_date', 'desc')
            ->paginate(15);
    }

    /**
     * Validate retake eligibility
     */
    private function validateRetakeEligibility(Student $student, CourseOffering $offering): void
    {
        if (! $student->canRetakeCourse()) {
            throw new Exception('Student is not eligible for course retakes');
        }

        $maxRetakes = 2; // Policy: maximum 2 retakes
        $currentRetakes = $student->courseRegistrations()
            ->where('is_retake', true)
            ->whereHas('courseOffering', function ($q) use ($offering) {
                $q->where('unit_id', $offering->unit_id);
            })
            ->count();

        if ($currentRetakes >= $maxRetakes) {
            throw new Exception('Maximum number of retakes exceeded for this unit');
        }

        if ($offering->enrollment_capacity <= $offering->current_enrollment) {
            throw new Exception('Course offering is at full capacity');
        }
    }

    /**
     * Find original registration for a unit
     */
    private function findOriginalRegistration(Student $student, int $unitId): ?CourseRegistration
    {
        return $student->courseRegistrations()
            ->where('is_retake', false)
            ->whereHas('courseOffering', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId);
            })
            ->first();
    }

    /**
     * Get next attempt number for a unit
     */
    private function getNextAttemptNumber(Student $student, int $unitId): int
    {
        $maxAttempt = $student->courseRegistrations()
            ->whereHas('courseOffering', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId);
            })
            ->max('attempt_number') ?? 0;

        return $maxAttempt + 1;
    }
}
