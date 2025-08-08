<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\ClassSession;
use App\Models\Semester;
use App\Models\Student;
use App\Repositories\V1\Student\ClassSessionRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TimetableService
{
    public function __construct(
        protected ClassSessionRepository $classSessionRepository
    ) {}

    /**
     * Get student's complete timetable
     */
    public function getStudentTimetable(Student $student, ?int $semesterId = null, array $filters = []): array
    {
        $semester = $this->resolveSemester($semesterId);

        if (! $semester) {
            return $this->getEmptyTimetable();
        }

        $cacheKey = "timetable:student:{$student->id}:semester:{$semester->id}:".md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($student, $semester, $filters) {
            $classSessions = $this->classSessionRepository->getStudentClassSessions($student, $semester, $filters);

            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date->toDateString(),
                    'end_date' => $semester->end_date->toDateString(),
                ],
                'weekly_schedule' => $this->generateWeeklySchedule($classSessions),
                'schedule_summary' => $this->generateScheduleSummary($classSessions),
                'time_blocks' => $this->generateTimeBlocks($classSessions),
                'filters_applied' => $filters,
            ];
        });
    }

    /**
     * Get weekly timetable view
     */
    public function getWeeklyTimetable(Student $student, ?int $semesterId = null, array $filters = []): array
    {
        $timetable = $this->getStudentTimetable($student, $semesterId, $filters);

        return [
            'semester' => $timetable['semester'],
            'weekly_schedule' => $timetable['weekly_schedule'],
            'schedule_summary' => $timetable['schedule_summary'],
        ];
    }

    /**
     * Get class session details
     */
    public function getClassSessionDetail(ClassSession $classSession, Student $student): array
    {
        // Verify student is enrolled in this class
        $isEnrolled = $this->classSessionRepository->isStudentEnrolledInSession($student, $classSession);

        if (! $isEnrolled) {
            throw new \Exception('Student is not enrolled in this class session');
        }

        return [
            'session' => [
                'id' => $classSession->id,
                'day_of_week' => $classSession->day_of_week,
                'start_time' => $classSession->start_time,
                'end_time' => $classSession->end_time,
                'session_type' => $classSession->session_type,
                'duration_minutes' => $this->calculateDuration($classSession->start_time, $classSession->end_time),
            ],
            'course' => [
                'code' => $classSession->courseOffering->curriculumUnit->unit->code,
                'name' => $classSession->courseOffering->curriculumUnit->unit->name,
                'credit_hours' => $classSession->courseOffering->curriculumUnit->credit_hours,
            ],
            'lecturer' => [
                'id' => $classSession->courseOffering->lecturer?->id,
                'name' => $classSession->courseOffering->lecturer?->full_name,
                'email' => $classSession->courseOffering->lecturer?->email,
                'phone' => $classSession->courseOffering->lecturer?->phone,
            ],
            'room' => [
                'id' => $classSession->room?->id,
                'code' => $classSession->room?->code,
                'name' => $classSession->room?->name,
                'building' => $classSession->room?->building,
                'capacity' => $classSession->room?->capacity,
                'facilities' => $classSession->room?->facilities,
            ],
            'attendance_info' => $this->getAttendanceInfo($student, $classSession),
            'upcoming_sessions' => $this->getUpcomingSessions($classSession, 5),
        ];
    }

    /**
     * Get available filter options
     */
    public function getFilterOptions(Student $student, ?int $semesterId = null): array
    {
        $semester = $this->resolveSemester($semesterId);

        if (! $semester) {
            return [];
        }

        $classSessions = $this->classSessionRepository->getStudentClassSessions($student, $semester);

        return [
            'days_of_week' => $this->getAvailableDays($classSessions),
            'session_types' => $this->getAvailableSessionTypes($classSessions),
            'lecturers' => $this->getAvailableLecturers($classSessions),
            'buildings' => $this->getAvailableBuildings($classSessions),
            'time_slots' => $this->getAvailableTimeSlots($classSessions),
        ];
    }

    /**
     * Generate weekly schedule structure
     */
    protected function generateWeeklySchedule(Collection $classSessions): array
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $schedule = [];

        foreach ($daysOfWeek as $day) {
            $schedule[$day] = [
                'day_name' => ucfirst($day),
                'sessions' => $classSessions
                    ->where('day_of_week', $day)
                    ->sortBy('start_time')
                    ->map(function ($session) {
                        return $this->formatSessionForSchedule($session);
                    })
                    ->values()
                    ->toArray(),
            ];
        }

        return $schedule;
    }

    /**
     * Generate schedule summary
     */
    protected function generateScheduleSummary(Collection $classSessions): array
    {
        $totalSessions = $classSessions->count();
        $uniqueCourses = $classSessions->pluck('courseOffering.curriculumUnit.unit.code')->unique()->count();
        $totalHours = $classSessions->sum(function ($session) {
            return $this->calculateDuration($session->start_time, $session->end_time) / 60;
        });

        $dayDistribution = $classSessions->groupBy('day_of_week')->map->count();
        $sessionTypeDistribution = $classSessions->groupBy('session_type')->map->count();

        return [
            'total_sessions_per_week' => $totalSessions,
            'unique_courses' => $uniqueCourses,
            'total_hours_per_week' => round($totalHours, 1),
            'busiest_day' => $dayDistribution->keys()->first(),
            'day_distribution' => $dayDistribution->toArray(),
            'session_type_distribution' => $sessionTypeDistribution->toArray(),
            'earliest_start' => $classSessions->min('start_time'),
            'latest_end' => $classSessions->max('end_time'),
        ];
    }

    /**
     * Generate time blocks for visual representation
     */
    protected function generateTimeBlocks(Collection $classSessions): array
    {
        $timeSlots = [];
        $startHour = 8; // 8 AM
        $endHour = 22; // 10 PM

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $timeSlot = sprintf('%02d:00', $hour);
            $timeSlots[$timeSlot] = [
                'time' => $timeSlot,
                'display_time' => Carbon::createFromTimeString($timeSlot)->format('g:i A'),
                'sessions' => [],
            ];

            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $sessionsInSlot = $classSessions->filter(function ($session) use ($day, $hour) {
                    $startHour = Carbon::createFromTimeString($session->start_time)->hour;
                    $endHour = Carbon::createFromTimeString($session->end_time)->hour;

                    return $session->day_of_week === $day && $hour >= $startHour && $hour < $endHour;
                });

                $timeSlots[$timeSlot]['sessions'][$day] = $sessionsInSlot->map(function ($session) {
                    return $this->formatSessionForTimeBlock($session);
                })->values()->toArray();
            }
        }

        return array_values($timeSlots);
    }

    /**
     * Format session for schedule display
     */
    protected function formatSessionForSchedule(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'course_code' => $session->courseOffering->curriculumUnit->unit->code,
            'course_name' => $session->courseOffering->curriculumUnit->unit->name,
            'session_type' => $session->session_type,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'duration_minutes' => $this->calculateDuration($session->start_time, $session->end_time),
            'lecturer' => $session->courseOffering->lecturer?->full_name,
            'room' => [
                'code' => $session->room?->code,
                'name' => $session->room?->name,
                'building' => $session->room?->building,
            ],
            'color' => $this->generateSessionColor($session->courseOffering->curriculumUnit->unit->code),
        ];
    }

    /**
     * Format session for time block display
     */
    protected function formatSessionForTimeBlock(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'course_code' => $session->courseOffering->curriculumUnit->unit->code,
            'session_type' => $session->session_type,
            'room' => $session->room?->code,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'color' => $this->generateSessionColor($session->courseOffering->curriculumUnit->unit->code),
        ];
    }

    /**
     * Calculate duration between two times in minutes
     */
    protected function calculateDuration(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromTimeString($startTime);
        $end = Carbon::createFromTimeString($endTime);

        return $start->diffInMinutes($end);
    }

    /**
     * Generate consistent color for course
     */
    protected function generateSessionColor(string $courseCode): string
    {
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
            '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#6366F1',
        ];

        $index = crc32($courseCode) % count($colors);

        return $colors[abs($index)];
    }

    /**
     * Resolve semester from ID or get current active semester
     */
    protected function resolveSemester(?int $semesterId): ?Semester
    {
        if ($semesterId) {
            return Semester::find($semesterId);
        }

        return Semester::where('is_active', true)->first();
    }

    /**
     * Get empty timetable structure
     */
    protected function getEmptyTimetable(): array
    {
        return [
            'semester' => null,
            'weekly_schedule' => [],
            'schedule_summary' => [
                'total_sessions_per_week' => 0,
                'unique_courses' => 0,
                'total_hours_per_week' => 0,
            ],
            'time_blocks' => [],
            'filters_applied' => [],
        ];
    }

    /**
     * Get available days from class sessions
     */
    protected function getAvailableDays(Collection $classSessions): array
    {
        return $classSessions->pluck('day_of_week')->unique()->sort()->values()->toArray();
    }

    /**
     * Get available session types
     */
    protected function getAvailableSessionTypes(Collection $classSessions): array
    {
        return $classSessions->pluck('session_type')->unique()->sort()->values()->toArray();
    }

    /**
     * Get available lecturers
     */
    protected function getAvailableLecturers(Collection $classSessions): array
    {
        return $classSessions->pluck('courseOffering.lecturer.full_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get available buildings
     */
    protected function getAvailableBuildings(Collection $classSessions): array
    {
        return $classSessions->pluck('room.building')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get available time slots
     */
    protected function getAvailableTimeSlots(Collection $classSessions): array
    {
        $timeSlots = [];

        foreach ($classSessions as $session) {
            $timeSlots[] = [
                'start' => $session->start_time,
                'end' => $session->end_time,
                'display' => Carbon::createFromTimeString($session->start_time)->format('g:i A').
                           ' - '.
                           Carbon::createFromTimeString($session->end_time)->format('g:i A'),
            ];
        }

        return collect($timeSlots)->unique('start')->sortBy('start')->values()->toArray();
    }

    /**
     * Get attendance info for student and session
     */
    protected function getAttendanceInfo(Student $student, ClassSession $classSession): array
    {
        // This would be implemented based on your attendance tracking system
        return [
            'total_sessions' => 0,
            'attended_sessions' => 0,
            'attendance_percentage' => 0,
            'recent_attendance' => [],
        ];
    }

    /**
     * Get upcoming sessions for a class
     */
    protected function getUpcomingSessions(ClassSession $classSession, int $limit = 5): array
    {
        // This would return upcoming occurrences of this class session
        return [];
    }
}
