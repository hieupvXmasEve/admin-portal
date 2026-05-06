<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnrollmentCapacityService
{
    /**
     * Check if course offering has available capacity
     */
    public function hasAvailableCapacity(CourseOffering $courseOffering): bool
    {
        $currentEnrollment = $this->getCurrentEnrollmentCount($courseOffering);

        return $currentEnrollment < $courseOffering->max_enrollment;
    }

    /**
     * Get current enrollment count for a course offering
     */
    public function getCurrentEnrollmentCount(CourseOffering $courseOffering): int
    {
        $cacheKey = "enrollment_count:course_offering:{$courseOffering->id}";

        return Cache::remember($cacheKey, 300, function () use ($courseOffering) {
            return CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('registration_status', 'registered')
                ->count();
        });
    }

    /**
     * Get available spots for a course offering
     */
    public function getAvailableSpots(CourseOffering $courseOffering): int
    {
        $currentEnrollment = $this->getCurrentEnrollmentCount($courseOffering);

        return max(0, $courseOffering->max_enrollment - $currentEnrollment);
    }

    /**
     * Get enrollment statistics for a course offering
     */
    public function getEnrollmentStatistics(CourseOffering $courseOffering): array
    {
        $currentEnrollment = $this->getCurrentEnrollmentCount($courseOffering);
        $maxEnrollment = $courseOffering->max_enrollment;
        $availableSpots = max(0, $maxEnrollment - $currentEnrollment);
        $enrollmentPercentage = $maxEnrollment > 0 ? round(($currentEnrollment / $maxEnrollment) * 100, 1) : 0;

        // Get waitlist count if applicable
        $waitlistCount = 0;
        if ($courseOffering->allow_waitlist) {
            $waitlistCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('registration_status', 'waitlisted')
                ->count();
        }

        return [
            'current_enrollment' => $currentEnrollment,
            'max_enrollment' => $maxEnrollment,
            'available_spots' => $availableSpots,
            'enrollment_percentage' => $enrollmentPercentage,
            'is_full' => $availableSpots === 0,
            'waitlist_count' => $waitlistCount,
            'waitlist_available' => $courseOffering->allow_waitlist && $availableSpots === 0,
            'enrollment_status' => $this->getEnrollmentStatus($enrollmentPercentage, $availableSpots),
        ];
    }

    /**
     * Update enrollment count cache
     */
    public function updateEnrollmentCount(CourseOffering $courseOffering): void
    {
        $cacheKey = "enrollment_count:course_offering:{$courseOffering->id}";
        Cache::forget($cacheKey);

        // Refresh the cache
        $this->getCurrentEnrollmentCount($courseOffering);
    }

    /**
     * Check if student can be added to waitlist
     */
    public function canAddToWaitlist(CourseOffering $courseOffering, Student $student): bool
    {
        if (! $courseOffering->allow_waitlist) {
            return false;
        }

        if ($this->hasAvailableCapacity($courseOffering)) {
            return false; // Should register normally, not waitlist
        }

        // Check if student is already waitlisted
        $existingWaitlist = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('student_id', $student->id)
            ->where('registration_status', 'waitlisted')
            ->exists();

        if ($existingWaitlist) {
            return false;
        }

        // Check waitlist capacity if there's a limit
        if ($courseOffering->max_waitlist > 0) {
            $currentWaitlistCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('registration_status', 'waitlisted')
                ->count();

            return $currentWaitlistCount < $courseOffering->max_waitlist;
        }

        return true;
    }

    /**
     * Add student to waitlist
     */
    public function addToWaitlist(CourseOffering $courseOffering, Student $student): CourseRegistration
    {
        if (! $this->canAddToWaitlist($courseOffering, $student)) {
            throw new \Exception('Cannot add student to waitlist');
        }

        // Get next waitlist position
        $waitlistPosition = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('registration_status', 'waitlisted')
            ->max('waitlist_position') + 1;

        return CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $courseOffering->semester_id,
            'registration_status' => 'waitlisted',
            'registration_date' => now(),
            'credit_hours' => $courseOffering->unit->credit_points,
            'registration_type' => 'waitlist',
            'waitlist_position' => $waitlistPosition,
        ]);
    }

    /**
     * Process waitlist when a spot becomes available
     */
    public function processWaitlist(CourseOffering $courseOffering): ?CourseRegistration
    {
        if (! $this->hasAvailableCapacity($courseOffering)) {
            return null;
        }

        // Get next student from waitlist
        $nextWaitlistRegistration = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('registration_status', 'waitlisted')
            ->orderBy('waitlist_position')
            ->first();

        if (! $nextWaitlistRegistration) {
            return null;
        }

        return DB::transaction(function () use ($nextWaitlistRegistration, $courseOffering) {
            // Update registration status
            $nextWaitlistRegistration->update([
                'registration_status' => 'registered',
                'registration_date' => now(),
                'waitlist_position' => null,
            ]);

            // Update remaining waitlist positions
            CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('registration_status', 'waitlisted')
                ->where('waitlist_position', '>', $nextWaitlistRegistration->waitlist_position)
                ->decrement('waitlist_position');

            // Update enrollment count cache
            $this->updateEnrollmentCount($courseOffering);

            return $nextWaitlistRegistration;
        });
    }

    /**
     * Get waitlist information for a student
     */
    public function getWaitlistInfo(CourseOffering $courseOffering, Student $student): ?array
    {
        $waitlistRegistration = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('student_id', $student->id)
            ->where('registration_status', 'waitlisted')
            ->first();

        if (! $waitlistRegistration) {
            return null;
        }

        $totalWaitlisted = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('registration_status', 'waitlisted')
            ->count();

        return [
            'position' => $waitlistRegistration->waitlist_position,
            'total_waitlisted' => $totalWaitlisted,
            'estimated_probability' => $this->estimateWaitlistProbability($courseOffering, $waitlistRegistration->waitlist_position),
            'waitlisted_date' => $waitlistRegistration->registration_date->toDateString(),
        ];
    }

    /**
     * Get enrollment status description
     */
    protected function getEnrollmentStatus(float $enrollmentPercentage, int $availableSpots): string
    {
        return match (true) {
            $availableSpots === 0 => 'full',
            $enrollmentPercentage >= 90 => 'nearly_full',
            $enrollmentPercentage >= 75 => 'filling_up',
            $enrollmentPercentage >= 50 => 'half_full',
            $enrollmentPercentage >= 25 => 'available',
            default => 'open',
        };
    }

    /**
     * Estimate probability of getting off waitlist
     */
    protected function estimateWaitlistProbability(CourseOffering $courseOffering, int $position): float
    {
        // Simple estimation based on historical data
        // This could be enhanced with actual historical drop rates
        $historicalDropRate = 0.15; // Assume 15% drop rate
        $expectedDrops = $courseOffering->max_enrollment * $historicalDropRate;

        if ($position <= $expectedDrops) {
            return min(100, (($expectedDrops - $position + 1) / $expectedDrops) * 100);
        }

        return max(5, 100 - ($position * 10)); // Minimum 5% chance
    }

    /**
     * Get enrollment trends for a course offering
     */
    public function getEnrollmentTrends(CourseOffering $courseOffering, int $days = 30): array
    {
        $registrations = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->where('registration_status', 'registered')
            ->where('registration_date', '>=', now()->subDays($days))
            ->selectRaw('DATE(registration_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $trends = [];
        $cumulativeCount = 0;

        foreach ($registrations as $registration) {
            $cumulativeCount += $registration->count;
            $trends[] = [
                'date' => $registration->date,
                'daily_registrations' => $registration->count,
                'cumulative_registrations' => $cumulativeCount,
            ];
        }

        return [
            'trends' => $trends,
            'total_registrations_period' => $cumulativeCount,
            'average_daily_registrations' => count($trends) > 0 ? round($cumulativeCount / count($trends), 1) : 0,
        ];
    }

    /**
     * Bulk update enrollment counts for multiple course offerings
     */
    public function bulkUpdateEnrollmentCounts(array $courseOfferingIds): void
    {
        foreach ($courseOfferingIds as $id) {
            $cacheKey = "enrollment_count:course_offering:{$id}";
            Cache::forget($cacheKey);
        }
    }
}
