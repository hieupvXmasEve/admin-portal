<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Semester;
use App\Models\Campus;
use App\Models\StudentEnrollment;
use App\Models\SemesterUnitOffering;
use App\Models\Unit;
use App\Models\User;
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
            if (!isset($data['year'])) {
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
            if (!$semester->code) {
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
    public function createUnitOfferings(Semester $semester, array $offerings): Collection
    {
        $createdOfferings = collect();

        DB::transaction(function () use ($semester, $offerings, &$createdOfferings) {
            foreach ($offerings as $offeringData) {
                $offering = SemesterUnitOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $offeringData['unit_id'],
                    'instructor_id' => $offeringData['instructor_id'] ?? null,
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

        Log::info("Created {$createdOfferings->count()} unit offerings for semester: {$semester->name}");

        return $createdOfferings;
    }

    /**
     * Enroll a student in a semester
     */
    public function enrollStudent(User $student, Semester $semester, array $data): StudentEnrollment
    {
        if (!$semester->isEnrollmentOpen()) {
            throw new \Exception('Enrollment is not open for this semester');
        }

        // Check if student is already enrolled
        $existingEnrollment = StudentEnrollment::where('user_id', $student->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existingEnrollment) {
            throw new \Exception('Student is already enrolled in this semester');
        }

        return DB::transaction(function () use ($student, $semester, $data) {
            $enrollment = StudentEnrollment::create([
                'user_id' => $student->id,
                'semester_id' => $semester->id,
                'program_id' => $data['program_id'],
                'specialization_id' => $data['specialization_id'] ?? null,
                'enrollment_date' => Carbon::now(),
                'is_full_time' => $data['is_full_time'] ?? true,
            ]);

            Log::info("Enrolled student {$student->email} in semester {$semester->name}");

            return $enrollment;
        });
    }

    /**
     * Get semester statistics for a campus
     */
    public function getSemesterStatistics(Semester $semester): array
    {
        $totalEnrollments = $semester->enrollments()->count();
        $activeEnrollments = $semester->enrollments()->active()->count();
        $fullTimeStudents = $semester->enrollments()->fullTime()->count();
        $partTimeStudents = $semester->enrollments()->partTime()->count();

        $unitOfferings = $semester->semesterOfferings()->count();
        $activeOfferings = $semester->semesterOfferings()->active()->count();

        $totalCapacity = $semester->semesterOfferings()->sum('max_capacity');
        $totalEnrolled = $semester->semesterOfferings()->sum('current_enrollment');
        $utilizationRate = $totalCapacity > 0 ? ($totalEnrolled / $totalCapacity) * 100 : 0;

        return [
            'enrollments' => [
                'total' => $totalEnrollments,
                'active' => $activeEnrollments,
                'full_time' => $fullTimeStudents,
                'part_time' => $partTimeStudents,
            ],
            'offerings' => [
                'total' => $unitOfferings,
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
     * Copy unit offerings from previous semester
     */
    public function copyOfferingsFromPreviousSemester(Semester $targetSemester, Semester $sourceSemester): Collection
    {
        $sourceOfferings = $sourceSemester->semesterOfferings()->with('unit')->get();
        $copiedOfferings = collect();

        DB::transaction(function () use ($targetSemester, $sourceOfferings, &$copiedOfferings) {
            foreach ($sourceOfferings as $sourceOffering) {
                $newOffering = SemesterUnitOffering::create([
                    'semester_id' => $targetSemester->id,
                    'unit_id' => $sourceOffering->unit_id,
                    'instructor_id' => $sourceOffering->instructor_id,
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
