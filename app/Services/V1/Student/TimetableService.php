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
use Illuminate\Support\Facades\Log;

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
            return $this->getEmptyTimetable('No active semester found');
        }

        $cacheKey = "timetable:student:{$student->id}:semester:{$semester->id}:" . md5(serialize($filters));

        $classSessions = $this->classSessionRepository->getStudentClassSessions($student, $semester, $filters);
        Log::debug('$classSessions', [$classSessions]);
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
            'generated_at' => now()->toISOString(),
        ];
        // return Cache::remember($cacheKey, 300, function () use ($student, $semester, $filters) {
        //     $classSessions = $this->classSessionRepository->getStudentClassSessions($student, $semester, $filters);

        //     return [
        //         'semester' => [
        //             'id' => $semester->id,
        //             'name' => $semester->name,
        //             'code' => $semester->code,
        //             'start_date' => $semester->start_date->toDateString(),
        //             'end_date' => $semester->end_date->toDateString(),
        //         ],
        //         'weekly_schedule' => $this->generateWeeklySchedule($classSessions),
        //         'schedule_summary' => $this->generateScheduleSummary($classSessions),
        //         'time_blocks' => $this->generateTimeBlocks($classSessions),
        //         'filters_applied' => $filters,
        //     ];
        // });
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

        $sessionDateString = $classSession->session_date instanceof Carbon
            ? $classSession->session_date->toDateString()
            : (string) $classSession->session_date;

        return [
            'session' => [
                'id' => $classSession->id,
                'session_date' => $sessionDateString,
                'day_of_week' => strtolower(Carbon::parse($sessionDateString)->format('l')),
                'start_time' => $classSession->start_time,
                'end_time' => $classSession->end_time,
                'session_type' => $classSession->session_type,
                'duration_minutes' => $this->calculateDuration((string) $classSession->start_time, (string) $classSession->end_time),
            ],
            'course' => [
                'code' => $classSession->courseOffering->unit->code,
                'name' => $classSession->courseOffering->unit->name,
                'credit_hours' => $classSession->courseOffering->credit_hours,
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
            return [
                'days_of_week' => [],
                'session_types' => [],
                'lecturers' => [],
                'buildings' => [],
                'time_slots' => [],
                'message' => 'No active semester found'
            ];
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
            $dayMap = [
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7,
            ];

            $schedule[$day] = [
                'day_name' => ucfirst($day),
                'day_abbreviation' => strtoupper(substr($day, 0, 3)),
                'day' => Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($dayMap[$day])->day,
                'sessions' => $classSessions
                    ->filter(function ($session) use ($dayMap, $day) {
                        return Carbon::parse($session->session_date)->dayOfWeek === $dayMap[$day];
                    })
                    ->sortBy('start_time')
                    ->map(function ($session) {
                        return $this->formatSessionForSchedule($session);
                    })
                    ->values()
                    ->toArray(),
            ];

            // Add session count and total duration
            $sessions = $schedule[$day]['sessions'];
            $schedule[$day]['session_count'] = count($sessions);
            $schedule[$day]['total_duration'] = [
                'total_minutes' => collect($sessions)->sum('duration_minutes'),
                'display' => $this->formatDuration(collect($sessions)->sum('duration_minutes'))
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
        $uniqueCourses = $classSessions->pluck('courseOffering.unit.code')->unique()->count();
        $totalMinutes = $classSessions->sum(function ($session) {
            return $this->calculateDuration($session->start_time, $session->end_time);
        });
        $totalHours = $totalMinutes / 60.0;

        // Build distributions as plain arrays to satisfy static analysis
        $byDay = $classSessions->reduce(function (array $acc, $session) {
            $day = strtolower(Carbon::parse((string) $session->session_date)->format('l'));
            $acc[$day] = ($acc[$day] ?? 0) + 1;
            return $acc;
        }, []);

        $bySessionType = $classSessions->reduce(function (array $acc, $session) {
            $type = (string) $session->session_type;
            $acc[$type] = ($acc[$type] ?? 0) + 1;
            return $acc;
        }, []);

        // Determine busiest day
        $busiestDay = null;
        if (! empty($byDay)) {
            $busiestDay = array_keys($byDay, max($byDay))[0];
        }

        return [
            'overview' => [
                'total_sessions_per_week' => $totalSessions,
                'unique_courses' => $uniqueCourses,
                'total_hours_per_week' => round($totalHours, 1),
                'average_hours_per_day' => $totalSessions > 0 ? (float) round($totalHours / 7, 1) : 0,
            ],
            'schedule_pattern' => [
                'busiest_day' => $busiestDay,
                'earliest_start' => $classSessions->min('start_time'),
                'latest_end' => $classSessions->max('end_time'),
                'earliest_start_display' => ($min = $classSessions->min('start_time')) ? Carbon::createFromTimeString((string) $min)->format('g:i A') : null,
                'latest_end_display' => ($max = $classSessions->max('end_time')) ? Carbon::createFromTimeString((string) $max)->format('g:i A') : null,
            ],
            'distribution' => [
                'by_day' => $byDay,
                'by_session_type' => $bySessionType,
            ],
        ];
    }

    /**
     * Generate time blocks for visual representation
     */
    protected function generateTimeBlocks(Collection $classSessions): array
    {
        $timeSlots = [];
        $startHour = 6; // 8 AM
        $endHour = 22; // 10 PM

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $timeSlot = sprintf('%02d:00', $hour);
            $timeSlots[$timeSlot] = [
                'time' => $timeSlot,
                'display_time' => Carbon::createFromTimeString($timeSlot)->format('g:i A'),
                'sessions' => [],
            ];

            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $dayMap = [
                    'sunday' => 0,
                    'monday' => 1,
                    'tuesday' => 2,
                    'wednesday' => 3,
                    'thursday' => 4,
                    'friday' => 5,
                    'saturday' => 6,
                ];

                $sessionsInSlot = $classSessions->filter(function ($session) use ($dayMap, $day, $hour) {
                    $startHour = $session->start_time instanceof Carbon ? $session->start_time->hour : Carbon::createFromTimeString($session->start_time)->hour;
                    $endHour = $session->end_time instanceof Carbon ? $session->end_time->hour : Carbon::createFromTimeString($session->end_time)->hour;
                    $sessionDay = Carbon::parse($session->session_date)->dayOfWeek;

                    return $sessionDay === $dayMap[$day] && $hour >= $startHour && $hour < $endHour;
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
        $roomBuilding = null;
        if ($session->room && $session->room->building) {
            if (is_object($session->room->building)) {
                $roomBuilding = $session->room->building->name ?? null;
            } else {
                $roomBuilding = $session->room->building;
            }
        }

        return [
            'id' => $session->id,
            'course_code' => $session->courseOffering->unit->code,
            'course_name' => $session->courseOffering->unit->name,
            'session_type' => $session->session_type,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'duration_minutes' => $this->calculateDuration((string) $session->start_time, (string) $session->end_time),
            'lecturer' => $session->courseOffering->lecture?->full_name,
            'room' => [
                'code' => $session->room?->code,
                'name' => $session->room?->name,
                'building' => $roomBuilding,
            ],
            'color' => $this->generateSessionColor($session->courseOffering->unit->code),
        ];
    }

    /**
     * Format session for time block display
     */
    protected function formatSessionForTimeBlock(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'course_code' => $session->courseOffering->unit->code,
            'session_type' => $session->session_type,
            'room' => $session->room?->code,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'color' => $this->generateSessionColor($session->courseOffering->unit->code),
        ];
    }

    /**
     * Calculate duration between two times in minutes
     */
    protected function calculateDuration(string|Carbon $startTime, string|Carbon $endTime): int
    {
        $start = $startTime instanceof Carbon ? $startTime : Carbon::createFromTimeString($startTime);
        $end = $endTime instanceof Carbon ? $endTime : Carbon::createFromTimeString($endTime);

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Generate consistent color for course
     */
    protected function generateSessionColor(string $courseCode): string
    {
        $colors = [
            '#3B82F6',
            '#EF4444',
            '#10B981',
            '#F59E0B',
            '#8B5CF6',
            '#06B6D4',
            '#F97316',
            '#84CC16',
            '#EC4899',
            '#6366F1',
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

        // First try to get the manually marked active semester
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            return $activeSemester;
        }

        // If no active semester, try to find current semester based on date
        $now = now();
        $currentSemester = Semester::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->first();

        if ($currentSemester) {
            Log::warning('No active semester found, using current semester based on dates', [
                'semester_id' => $currentSemester->id,
                'semester_name' => $currentSemester->name,
                'current_date' => $now->toDateString()
            ]);
            return $currentSemester;
        }

        // If still no semester, try to get the most recent non-archived semester
        $recentSemester = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->first();

        if ($recentSemester) {
            Log::warning('No current semester found, using most recent semester', [
                'semester_id' => $recentSemester->id,
                'semester_name' => $recentSemester->name,
                'current_date' => $now->toDateString()
            ]);
            return $recentSemester;
        }

        Log::error('No semester available for timetable', [
            'current_date' => $now->toDateString(),
            'total_semesters' => Semester::count()
        ]);
        return null;
    }

    /**
     * Get empty timetable structure
     */
    protected function getEmptyTimetable(?string $reason = null): array
    {
        $emptyDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $emptySchedule = [];

        foreach ($emptyDays as $day) {
            $emptySchedule[$day] = [
                'day_name' => ucfirst($day),
                'day_abbreviation' => strtoupper(substr($day, 0, 3)),
                'session_count' => 0,
                'sessions' => [],
                'total_duration' => [
                    'total_minutes' => 0,
                    'display' => '0m'
                ],
            ];
        }

        return [
            'semester' => null,
            'message' => $reason ?? 'No timetable data available',
            'weekly_schedule' => $emptySchedule,
            'schedule_summary' => [
                'overview' => [
                    'total_sessions_per_week' => 0,
                    'unique_courses' => 0,
                    'total_hours_per_week' => 0,
                    'average_hours_per_day' => 0,
                ],
                'schedule_pattern' => [
                    'busiest_day' => null,
                    'earliest_start' => null,
                    'latest_end' => null,
                    'earliest_start_display' => null,
                    'latest_end_display' => null,
                ],
                'distribution' => [
                    'by_day' => [],
                    'by_session_type' => [],
                ],
            ],
            'time_blocks' => [],
            'filters_applied' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes === 0) {
            return '0m';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$remainingMinutes}m";
        }
    }

    /**
     * Get available days from class sessions
     */
    protected function getAvailableDays(Collection $classSessions): array
    {
        $days = $classSessions->map(function ($session) {
            $dayOfWeek = Carbon::parse($session->session_date)->dayOfWeek;
            $dayMap = [
                1 => 'sunday',
                2 => 'monday',
                3 => 'tuesday',
                4 => 'wednesday',
                5 => 'thursday',
                6 => 'friday',
                7 => 'saturday'
            ];
            return $dayMap[$dayOfWeek] ?? 'unknown';
        })->unique()->sort()->values()->toArray();

        return $days;
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
            ->map(function ($building) {
                if (is_object($building)) {
                    return $building->name ?? null;
                }
                return $building;
            })
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
                'display' => Carbon::createFromTimeString($session->start_time)->format('g:i A') .
                    ' - ' .
                    Carbon::createFromTimeString($session->end_time)->format('g:i A'),
            ];
        }

        return collect($timeSlots)->unique(function ($item) {
            return $item['start'] . '-' . $item['end'];
        })->sortBy('start')->values()->toArray();
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
