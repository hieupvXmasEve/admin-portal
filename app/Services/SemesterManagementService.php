<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SemesterManagementService
{
    /**
     * Create a new semester for a campus
     */
    public function createSemester(Campus $campus, array $data): Semester
    {
        return DB::transaction(function () use ($campus, $data) {
            // Validate dates
            $this->validateSemesterDates($data);

            // Generate academic year if not provided
            if (! isset($data['year'])) {
                $data['year'] = $this->generateAcademicYear($data['start_date']);
            }

            $semester = Semester::create([
                'campus_id' => $campus->id,
                'code' => $data['code'] ?? null,
                'name' => $data['name'],
                'semester_type' => $data['semester_type'],
                'year' => $data['year'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'enrollment_start_date' => $data['enrollment_start_date'] ?? null,
                'enrollment_end_date' => $data['enrollment_end_date'] ?? null,
                'is_active' => $data['is_active'] ?? false,
                'is_archived' => $data['is_archived'] ?? false,
                'enrollment_start_date' => $data['enrollment_start_date'] ?? null,
                'enrollment_end_date' => $data['enrollment_end_date'] ?? null,
                'add_drop_deadline' => $data['add_drop_deadline'] ?? null,
                'withdrawal_deadline' => $data['withdrawal_deadline'] ?? null,
                'final_exam_start' => $data['final_exam_start'] ?? null,
                'final_exam_end' => $data['final_exam_end'] ?? null,
                'locked_status' => $data['locked_status'] ?? 'unlocked',
                'is_current' => $data['is_current'] ?? false,
                'is_registration_open' => $data['is_registration_open'] ?? false,
                'max_credit_load' => $data['max_credit_load'] ?? 18.00,
                'min_credit_load' => $data['min_credit_load'] ?? 12.00,
                'is_attendance_locked' => $data['is_attendance_locked'] ?? false,
                'is_certificate_locked' => $data['is_certificate_locked'] ?? false,
                'has_tuition_fee' => $data['has_tuition_fee'] ?? false,
                'has_gc_fee' => $data['has_gc_fee'] ?? false,
            ]);

            // Auto-generate code if not provided
            if (! $semester->code) {
                $semester->update(['code' => $semester->generateCode()]);
            }

            Log::info("Created semester: {$semester->name} for campus: {$campus->name}");

            return $semester;
        });
    }

    /**
     * Set a semester as current for a campus
     */
    public function setCurrentSemester(Semester $semester): bool
    {
        return DB::transaction(function () use ($semester) {
            // Unset all other current semesters for this campus
            Semester::where('campus_id', $semester->campus_id)
                ->where('id', '!=', $semester->id)
                ->update(['is_current' => false]);

            // Set this semester as current
            $semester->update(['is_current' => true]);

            Log::info("Set semester {$semester->name} as current for campus {$semester->campus->name}");

            return true;
        });
    }

    /**
     * Activate a semester with business logic validation
     */
    public function activateSemester(Semester $semester): array
    {
        // Auto-deactivate expired semesters first
        $deactivatedCount = Semester::deactivateExpiredSemesters();

        if ($deactivatedCount > 0) {
            Log::info("Auto-deactivated {$deactivatedCount} expired semester(s)");
        }

        // Check if can change active status
        if (! $semester->canChangeActiveStatus()) {
            return [
                'success' => false,
                'message' => $semester->getActiveStatusChangeError(),
            ];
        }

        // Check if semester can be activated
        if (! $semester->canBeActivated()) {
            return [
                'success' => false,
                'message' => $semester->getActivationError(),
            ];
        }

        // Activate the semester
        if ($semester->activate()) {
            Log::info("Activated semester: {$semester->name}");

            return [
                'success' => true,
                'message' => 'Semester activated successfully!',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to activate semester due to an unknown error.',
        ];
    }

    /**
     * Deactivate a semester
     */
    public function deactivateSemester(Semester $semester): array
    {
        // Check if can change active status
        if (! $semester->canChangeActiveStatus()) {
            return [
                'success' => false,
                'message' => $semester->getActiveStatusChangeError(),
            ];
        }

        if ($semester->deactivate()) {
            Log::info("Deactivated semester: {$semester->name}");

            return [
                'success' => true,
                'message' => 'Semester deactivated successfully!',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to deactivate semester.',
        ];
    }

    /**
     * Get the next semester that can be activated
     */
    public function getNextActivatableSemester(): ?Semester
    {
        return Semester::getNextActiveSemester();
    }

    /**
     * Get activation status for all semesters
     */
    public function getSemesterActivationStatuses(): array
    {
        $semesters = Semester::orderBy('start_date', 'asc')->get();
        $now = Carbon::now();
        $activeSemester = Semester::getActiveSemester();
        $nextSemester = Semester::getNextActiveSemester();

        return $semesters->map(function ($semester) use ($now, $activeSemester, $nextSemester) {
            $status = 'inactive';
            $canActivate = false;
            $reason = '';

            if ($semester->is_active) {
                $status = 'active';
                if ($semester->shouldBeDeactivated()) {
                    $reason = 'Expired - will be deactivated automatically';
                }
            } elseif ($semester->is_archived) {
                $status = 'archived';
                $reason = 'Cannot activate archived semester';
            } elseif ($semester->start_date && $semester->start_date->lte($now)) {
                $status = 'started';
                $reason = 'Cannot activate semester that has already started';
            } elseif ($nextSemester && $nextSemester->id === $semester->id) {
                $status = 'next';
                $canActivate = true;
                $reason = 'This is the next semester that can be activated';
            } else {
                $status = 'future';
                $reason = 'Can only activate the next upcoming semester';
            }

            return [
                'semester' => $semester,
                'status' => $status,
                'can_activate' => $canActivate,
                'reason' => $reason,
                'is_current_active' => $activeSemester && $activeSemester->id === $semester->id,
                'is_next_available' => $nextSemester && $nextSemester->id === $semester->id,
            ];
        })->all();
    }

    /**
     * Open enrollment for a semester
     */
    public function openEnrollment(Semester $semester): bool
    {
        if ($semester->isLocked()) {
            throw new \Exception('Cannot open enrollment for a locked semester');
        }

        $semester->update(['is_registration_open' => true]);

        Log::info("Opened enrollment for semester: {$semester->name}");

        return true;
    }

    /**
     * Close enrollment for a semester
     */
    public function closeEnrollment(Semester $semester): bool
    {
        $semester->update(['is_registration_open' => false]);

        Log::info("Closed enrollment for semester: {$semester->name}");

        return true;
    }

    /**
     * Create unit offerings for a semester
     */
    public function createCourseOfferings(Semester $semester, array $offerings): Collection
    {
        $createdOfferings = collect();

        DB::transaction(function () use ($semester, $offerings, &$createdOfferings) {
            foreach ($offerings as $offeringData) {
                $offering = CourseOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $offeringData['unit_id'],
                    'lecture_id' => $offeringData['lecture_id'] ?? null,
                    'section_code' => $offeringData['section_code'] ?? null,
                    'max_capacity' => $offeringData['max_capacity'] ?? 30,
                    'waitlist_capacity' => $offeringData['waitlist_capacity'] ?? 10,
                    'delivery_mode' => $offeringData['delivery_mode'] ?? 'in_person',
                    'schedule_days' => $offeringData['schedule_days'] ?? null,
                    'schedule_time_start' => $offeringData['schedule_time_start'] ?? null,
                    'schedule_time_end' => $offeringData['schedule_time_end'] ?? null,
                    'location' => $offeringData['location'] ?? null,
                    'special_requirements' => $offeringData['special_requirements'] ?? null,
                ]);

                $createdOfferings->push($offering);
            }
        });

        Log::info("Created {$createdOfferings->count()} course offerings for semester: {$semester->name}");

        return $createdOfferings;
    }

    // Note: Student enrollment is now handled through CourseRegistration

    /**
     * Get semester statistics for a campus
     */
    public function getSemesterStatistics(Semester $semester): array
    {
        $totalRegistrations = $semester->courseRegistrations()->count();
        $activeRegistrations = $semester->courseRegistrations()->active()->count();

        // Calculate full-time vs part-time based on credit hours
        $registrations = $semester->courseRegistrations()
            ->active()
            ->with('courseOffering.unit')
            ->get()
            ->groupBy('student_id');

        $fullTimeStudents = 0;
        $partTimeStudents = 0;

        foreach ($registrations as $studentRegistrations) {
            $totalCredits = $studentRegistrations->sum('credit_hours');
            if ($totalCredits >= 12) {
                $fullTimeStudents++;
            } else {
                $partTimeStudents++;
            }
        }

        $courseOfferings = $semester->courseOfferings()->count();
        $activeOfferings = $semester->courseOfferings()->active()->count();

        $totalCapacity = $semester->courseOfferings()->sum('max_capacity');
        $totalEnrolled = $semester->courseOfferings()->sum('current_enrollment');
        $utilizationRate = $totalCapacity > 0 ? ($totalEnrolled / $totalCapacity) * 100 : 0;

        return [
            'enrollments' => [
                'total' => $totalRegistrations,
                'active' => $activeRegistrations,
                'full_time' => $fullTimeStudents,
                'part_time' => $partTimeStudents,
            ],
            'offerings' => [
                'total' => $courseOfferings,
                'active' => $activeOfferings,
                'capacity_utilization' => round($utilizationRate, 2),
            ],
            'capacity' => [
                'total_capacity' => $totalCapacity,
                'total_enrolled' => $totalEnrolled,
                'available_spots' => $totalCapacity - $totalEnrolled,
            ],
        ];
    }

    /**
     * Get academic calendar for a campus
     */
    public function getAcademicCalendar(Campus $campus, ?string $academicYear = null): Collection
    {
        $query = $campus->semesters()->orderBy('start_date');

        if ($academicYear) {
            $query->byAcademicYear($academicYear);
        }

        return $query->get()->map(function ($semester) {
            return [
                'semester' => $semester,
                'events' => $this->getSemesterEvents($semester),
                'statistics' => $this->getSemesterStatistics($semester),
            ];
        });
    }

    /**
     * Get important dates/events for a semester
     */
    public function getSemesterEvents(Semester $semester): array
    {
        $events = [];

        $events[] = [
            'type' => 'semester_start',
            'date' => $semester->start_date,
            'title' => 'Semester Begins',
        ];

        if ($semester->enrollment_start_date) {
            $events[] = [
                'type' => 'enrollment_start',
                'date' => $semester->enrollment_start_date,
                'title' => 'Enrollment Opens',
            ];
        }

        if ($semester->enrollment_end_date) {
            $events[] = [
                'type' => 'enrollment_end',
                'date' => $semester->enrollment_end_date,
                'title' => 'Enrollment Closes',
            ];
        }

        if ($semester->add_drop_deadline) {
            $events[] = [
                'type' => 'add_drop_deadline',
                'date' => $semester->add_drop_deadline,
                'title' => 'Add/Drop Deadline',
            ];
        }

        if ($semester->withdrawal_deadline) {
            $events[] = [
                'type' => 'withdrawal_deadline',
                'date' => $semester->withdrawal_deadline,
                'title' => 'Withdrawal Deadline',
            ];
        }

        if ($semester->final_exam_start) {
            $events[] = [
                'type' => 'finals_start',
                'date' => $semester->final_exam_start,
                'title' => 'Final Exams Begin',
            ];
        }

        if ($semester->final_exam_end) {
            $events[] = [
                'type' => 'finals_end',
                'date' => $semester->final_exam_end,
                'title' => 'Final Exams End',
            ];
        }

        $events[] = [
            'type' => 'semester_end',
            'date' => $semester->end_date,
            'title' => 'Semester Ends',
        ];

        // Sort events by date
        usort($events, function ($a, $b) {
            return $a['date']->compare($b['date']);
        });

        return $events;
    }

    /**
     * Validate semester dates
     */
    private function validateSemesterDates(array $data): void
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($endDate->lte($startDate)) {
            throw new \Exception('End date must be after start date');
        }

        if (isset($data['enrollment_start_date']) && isset($data['enrollment_end_date'])) {
            $enrollStart = Carbon::parse($data['enrollment_start_date']);
            $enrollEnd = Carbon::parse($data['enrollment_end_date']);

            if ($enrollEnd->lte($enrollStart)) {
                throw new \Exception('Enrollment end date must be after enrollment start date');
            }

            if ($enrollStart->gte($startDate)) {
                throw new \Exception('Enrollment should start before semester begins');
            }
        }
    }

    /**
     * Generate academic year string from start date
     */
    private function generateAcademicYear(string $startDate): string
    {
        $date = Carbon::parse($startDate);
        $year = $date->year;

        // If semester starts in fall (Aug-Dec), academic year is current-next
        // If semester starts in spring/summer (Jan-Jul), academic year is previous-current
        if ($date->month >= 8) {
            return "{$year}-" . ($year + 1);
        } else {
            return ($year - 1) . "-{$year}";
        }
    }

    /**
     * Copy course offerings from previous semester
     */
    public function copyOfferingsFromPreviousSemester(Semester $targetSemester, Semester $sourceSemester): Collection
    {
        $sourceOfferings = $sourceSemester->courseOfferings()->with('unit')->get();
        $copiedOfferings = collect();

        DB::transaction(function () use ($targetSemester, $sourceOfferings, &$copiedOfferings) {
            foreach ($sourceOfferings as $sourceOffering) {
                $newOffering = CourseOffering::create([
                    'semester_id' => $targetSemester->id,
                    'unit_id' => $sourceOffering->unit_id,
                    'lecture_id' => $sourceOffering->lecture_id,
                    'section_code' => $sourceOffering->section_code,
                    'max_capacity' => $sourceOffering->max_capacity,
                    'waitlist_capacity' => $sourceOffering->waitlist_capacity,
                    'delivery_mode' => $sourceOffering->delivery_mode,
                    'schedule_days' => $sourceOffering->schedule_days,
                    'schedule_time_start' => $sourceOffering->schedule_time_start,
                    'schedule_time_end' => $sourceOffering->schedule_time_end,
                    'location' => $sourceOffering->location,
                    'special_requirements' => $sourceOffering->special_requirements,
                ]);

                $copiedOfferings->push($newOffering);
            }
        });

        Log::info("Copied {$copiedOfferings->count()} offerings from {$sourceSemester->name} to {$targetSemester->name}");

        return $copiedOfferings;
    }
}
