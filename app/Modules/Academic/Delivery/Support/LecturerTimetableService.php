<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Shared\Contracts\Identity\LecturerTeachingActor as Lecture;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LecturerTimetableService
{
    public function __construct(
        protected InvigilationDutyQuery $invigilationDutyQuery
    ) {}

    /**
     * Get lecturer's timetable for a specific period
     */
    public function getTimetable(
        Lecture $lecturer,
        array $filters = []
    ): array {
        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])
            : now()->startOfWeek();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])
            : $startDate->copy()->endOfWeek();

        $view = $filters['view'] ?? 'week'; // week, month, day

        $cacheKey = "lecturer-timetable:{$lecturer->lecturerId()}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}:{$view}";

        // return Cache::remember($cacheKey, 300, function () use ($lecturer, $startDate, $endDate, $view) {
        $sessions = $this->getSessionsInPeriod($lecturer, $startDate, $endDate);

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'view' => $view,
            ],
            'sessions' => $this->formatSessionsForTimetable($sessions, $view),
            'summary' => $this->getTimetableSummary($sessions, $startDate, $endDate),
            'conflicts' => $this->detectScheduleConflicts($sessions),
            'availability' => $this->getAvailabilitySlots($lecturer, $startDate, $endDate),
            'invigilation_duties' => $this->invigilationDutyQuery->handle($lecturer, $startDate, $endDate),
        ];
        // });
    }

    /**
     * Get lecturer's schedule for a specific date range
     */
    public function getSchedule(
        Lecture $lecturer,
        array $filters = []
    ): array {
        $startDate = isset($filters['start'])
            ? Carbon::parse($filters['start'])
            : now()->startOfMonth();

        $endDate = isset($filters['end'])
            ? Carbon::parse($filters['end'])
            : now()->endOfMonth();

        $cacheKey = "lecturer-schedule:{$lecturer->lecturerId()}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";

        // return Cache::remember($cacheKey, 300, function () use ($lecturer, $startDate, $endDate, $filters) {
        // Get sessions in the specified period
        $query = ClassSession::query()
            ->where('lecture_id', $lecturer->lecturerId())
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room.building', 'attendances'])
            ->whereBetween('session_date', [$startDate, $endDate]);

        // Apply filters
        if (isset($filters['course_offering_id'])) {
            $query->where('course_offering_id', $filters['course_offering_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! ($filters['include_cancelled'] ?? false)) {
            $query->where('status', '!=', 'cancelled');
        }

        $sessions = $query->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $startDate->diffInDays($endDate) + 1,
            ],
            'sessions' => $this->formatScheduleSessions($sessions),
            'summary' => $this->getScheduleSummary($sessions, $startDate, $endDate),
            'generated_at' => now()->toISOString(),
        ];
        // });
    }

    /**
     * Get upcoming sessions for lecturer
     */
    public function getUpcomingSessions(
        Lecture $lecturer,
        int $days = 7,
        int $limit = 20
    ): array {
        $endDate = now()->addDays($days);

        $sessions = ClassSession::query()
            ->where('lecture_id', $lecturer->lecturerId())
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room', 'attendances'])
            ->where('session_date', '>=', now())
            ->where('session_date', '<=', $endDate)
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();

        return $sessions->map(function ($session) {
            return $this->formatSessionDetails($session);
        })->toArray();
    }

    /**
     * Create a new session
     */
    public function createSession(
        Lecture $lecturer,
        array $sessionData
    ): array {
        return DB::transaction(function () use ($lecturer, $sessionData) {
            // Validate lecturer has access to the course
            $courseOffering = CourseOffering::query()
                ->where('lecture_id', $lecturer->lecturerId())
                ->where('id', $sessionData['course_offering_id'])
                ->first();

            if (! $courseOffering) {
                throw new \Exception('Course offering not found or access denied');
            }

            // Check for scheduling conflicts
            $conflicts = $this->checkSessionConflicts(
                $lecturer,
                $sessionData['session_date'],
                $sessionData['start_time'],
                $sessionData['end_time'],
                $sessionData['room_id'] ?? null
            );

            if (! empty($conflicts)) {
                throw new \Exception('Scheduling conflict detected: '.implode(', ', $conflicts));
            }

            // Create the session
            $session = ClassSession::create([
                'course_offering_id' => $sessionData['course_offering_id'],
                'lecture_id' => $lecturer->lecturerId(),
                'session_title' => $sessionData['session_title'],
                'session_description' => $sessionData['session_description'] ?? null,
                'session_date' => $sessionData['session_date'],
                'start_time' => $sessionData['start_time'],
                'end_time' => $sessionData['end_time'],
                'duration_minutes' => $this->calculateDuration(
                    $sessionData['start_time'],
                    $sessionData['end_time']
                ),
                'session_type' => $sessionData['session_type'] ?? 'lecture',
                'delivery_mode' => $sessionData['delivery_mode'] ?? $courseOffering->delivery_mode,
                'room_id' => $sessionData['room_id'] ?? null,
                'status' => 'scheduled',
                'expected_attendees' => $courseOffering->activeClassRosterEnrollmentCount(),
                'learning_objectives' => $sessionData['learning_objectives'] ?? null,
                'topics_covered' => $sessionData['topics_covered'] ?? null,
                'required_materials' => $sessionData['required_materials'] ?? null,
                'preparation_notes' => $sessionData['preparation_notes'] ?? null,
            ]);

            // Clear relevant caches
            $this->clearTimetableCaches($lecturer);

            return [
                'session' => $this->formatSessionDetails($session->load(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room', 'attendances'])),
                'message' => 'Session created successfully',
            ];
        });
    }

    /**
     * Update an existing session
     */
    public function updateSession(
        Lecture $lecturer,
        int $sessionId,
        array $updateData
    ): array {
        return DB::transaction(function () use ($lecturer, $sessionId, $updateData) {
            $session = ClassSession::query()
                ->where('lecture_id', $lecturer->lecturerId())
                ->where('id', $sessionId)
                ->first();

            if (! $session) {
                throw new \Exception('Session not found or access denied');
            }

            // Check if session can be updated
            if ($session->status === 'completed') {
                throw new \Exception('Cannot update completed session');
            }

            // Check for conflicts if time/date is being changed
            if (isset($updateData['session_date']) || isset($updateData['start_time']) || isset($updateData['end_time'])) {
                $conflicts = $this->checkSessionConflicts(
                    $lecturer,
                    $updateData['session_date'] ?? $session->session_date,
                    $updateData['start_time'] ?? $session->start_time,
                    $updateData['end_time'] ?? $session->end_time,
                    $updateData['room_id'] ?? $session->room_id,
                    $sessionId // Exclude current session from conflict check
                );

                if (! empty($conflicts)) {
                    throw new \Exception('Scheduling conflict detected: '.implode(', ', $conflicts));
                }
            }

            // Update duration if times changed
            if (isset($updateData['start_time']) || isset($updateData['end_time'])) {
                $updateData['duration_minutes'] = $this->calculateDuration(
                    $updateData['start_time'] ?? $session->start_time,
                    $updateData['end_time'] ?? $session->end_time
                );
            }

            $session->update($updateData);

            // Clear relevant caches
            $this->clearTimetableCaches($lecturer);

            return [
                'session' => $this->formatSessionDetails($session->load(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room', 'attendances'])),
                'message' => 'Session updated successfully',
            ];
        });
    }

    /**
     * Cancel a session
     */
    public function cancelSession(
        Lecture $lecturer,
        int $sessionId,
        ?string $reason = null
    ): array {
        return DB::transaction(function () use ($lecturer, $sessionId, $reason) {
            $session = ClassSession::query()
                ->where('lecture_id', $lecturer->lecturerId())
                ->where('id', $sessionId)
                ->first();

            if (! $session) {
                throw new \Exception('Session not found or access denied');
            }

            if ($session->status === 'completed') {
                throw new \Exception('Cannot cancel completed session');
            }

            $session->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            // Clear relevant caches
            $this->clearTimetableCaches($lecturer);

            return [
                'session' => $this->formatSessionDetails($session),
                'message' => 'Session cancelled successfully',
            ];
        });
    }

    /**
     * Get sessions in a specific period
     */
    protected function getSessionsInPeriod(
        Lecture $lecturer,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        return ClassSession::query()
            ->where('lecture_id', $lecturer->lecturerId())
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room', 'attendances'])
            ->whereBetween('session_date', [$startDate, $endDate])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Format sessions for timetable display
     */
    protected function formatSessionsForTimetable($sessions, string $view): array
    {
        return $sessions->map(function ($session) {
            return $this->formatSessionDetails($session);
        })->groupBy(function ($session) use ($view) {
            $date = Carbon::parse($session['date']);

            return match ($view) {
                'day' => $session['date'],
                'week' => $date->format('Y-m-d'),
                'month' => $date->format('Y-m-d'),
                default => $session['date'],
            };
        })->toArray();
    }

    /**
     * Format session details
     */
    protected function formatSessionDetails(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->session_title,
            'description' => $session->session_description,
            'date' => $session->session_date->format('Y-m-d'),
            'start_time' => $session->start_time->format('H:i'),
            'end_time' => $session->end_time->format('H:i'),
            'duration_minutes' => $session->duration_minutes,
            'session_type' => $session->session_type,
            'delivery_mode' => $session->delivery_mode,
            'status' => $session->status,
            'course' => [
                'id' => $session->courseOffering->id,
                'unit_code' => $session->courseOffering->unit->code,
                'unit_name' => $session->courseOffering->unit->name,
                'section_code' => $session->courseOffering->section_code,
            ],
            'room' => $session->room ? [
                'id' => $session->room->id,
                'name' => $session->room->name,
                'building' => $session->room->building,
                'capacity' => $session->room->capacity,
            ] : null,
            'attendance_marked' => $this->sessionHasActiveRosterAttendance($session),
            'expected_attendees' => $this->expectedAttendeesForSession($session),
            'learning_objectives' => $session->learning_objectives,
            'topics_covered' => $session->topics_covered,
        ];
    }

    protected function expectedAttendeesForSession(ClassSession $session): ?int
    {
        return $session->courseOffering?->activeClassRosterEnrollmentCount()
            ?? $session->expected_attendees;
    }

    protected function sessionHasActiveRosterAttendance(ClassSession $session): bool
    {
        if (! $session->relationLoaded('attendances') || ! $session->courseOffering) {
            return $session->attendance_marked;
        }

        return $session->attendances
            ->whereIn('student_id', $session->courseOffering->activeClassRosterStudentIds())
            ->isNotEmpty();
    }

    /**
     * Get timetable summary
     */
    protected function getTimetableSummary($sessions, Carbon $startDate, Carbon $endDate): array
    {
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $upcomingSessions = $sessions->where('status', 'scheduled')->count();
        $cancelledSessions = $sessions->where('status', 'cancelled')->count();

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'upcoming_sessions' => $upcomingSessions,
            'cancelled_sessions' => $cancelledSessions,
            'total_teaching_hours' => round($sessions->sum('duration_minutes') / 60, 1),
            'period_days' => $startDate->diffInDays($endDate) + 1,
        ];
    }

    /**
     * Detect schedule conflicts
     */
    protected function detectScheduleConflicts($sessions): array
    {
        $conflicts = [];
        $sessionsByDate = $sessions->groupBy('session_date');

        foreach ($sessionsByDate as $date => $dateSessions) {
            $sortedSessions = $dateSessions->sortBy('start_time');

            for ($i = 0; $i < $sortedSessions->count() - 1; $i++) {
                $current = $sortedSessions->values()[$i];
                $next = $sortedSessions->values()[$i + 1];

                if ($current->end_time > $next->start_time) {
                    $conflicts[] = [
                        'type' => 'time_overlap',
                        'date' => $date,
                        'sessions' => [
                            $this->formatSessionDetails($current),
                            $this->formatSessionDetails($next),
                        ],
                        'message' => 'Time overlap detected',
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get availability slots
     */
    protected function getAvailabilitySlots(Lecture $lecturer, Carbon $startDate, Carbon $endDate): array
    {
        // This would calculate free time slots based on lecturer preferences and existing sessions
        // Simplified implementation
        return [
            'free_slots' => [],
            'busy_slots' => [],
            'preferred_slots' => [],
        ];
    }

    /**
     * Check for session conflicts
     */
    protected function checkSessionConflicts(
        Lecture $lecturer,
        string $date,
        string $startTime,
        string $endTime,
        ?int $roomId = null,
        ?int $excludeSessionId = null
    ): array {
        $conflicts = [];

        // Check lecturer conflicts
        $lecturerConflicts = ClassSession::query()
            ->where('lecture_id', $lecturer->lecturerId())
            ->where('session_date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($excludeSessionId, function ($query, $excludeSessionId) {
                return $query->where('id', '!=', $excludeSessionId);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($lecturerConflicts) {
            $conflicts[] = 'Lecturer has another session at this time';
        }

        // Check room conflicts if room is specified
        if ($roomId) {
            $roomConflicts = ClassSession::where('session_date', $date)
                ->where('room_id', $roomId)
                ->where('status', '!=', 'cancelled')
                ->when($excludeSessionId, function ($query, $excludeSessionId) {
                    return $query->where('id', '!=', $excludeSessionId);
                })
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($roomConflicts) {
                $conflicts[] = 'Room is already booked at this time';
            }
        }

        return $conflicts;
    }

    /**
     * Calculate duration between two times
     */
    protected function calculateDuration(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromFormat('H:i:s', $startTime);
        $end = Carbon::createFromFormat('H:i:s', $endTime);

        return $start->diffInMinutes($end);
    }

    /**
     * Format sessions for schedule display
     */
    protected function formatScheduleSessions($sessions)
    {
        if ($sessions->isEmpty()) {
            return (object) []; // Return empty object instead of empty array
        }

        return $sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'course_code' => $session->courseOffering->unit->code ?? null,
                'course_name' => $session->courseOffering->unit->name ?? null,
                'section_code' => $session->courseOffering->section_code,
                'session_title' => $session->session_title,
                'session_type' => $session->session_type,
                'session_type_display' => ucfirst(str_replace('_', ' ', $session->session_type)),
                'date' => $session->session_date->format('Y-m-d'),
                'day_of_week' => $session->session_date->format('l'),
                'day_abbreviation' => $session->session_date->format('D'),
                'time' => [
                    'start' => $session->start_time->format('H:i:s'),
                    'end' => $session->end_time->format('H:i:s'),
                    'start_display' => $session->start_time->format('g:i A'),
                    'end_display' => $session->end_time->format('g:i A'),
                    'display' => $session->start_time->format('g:i A').' - '.$session->end_time->format('g:i A'),
                    'duration_minutes' => $session->duration_minutes,
                    'duration_display' => $this->formatDuration($session->duration_minutes),
                ],
                'location' => $session->room ? [
                    'room_code' => $session->room->name,
                    'room_name' => $session->room->name,
                    'building' => $session->room->building->name ?? null,
                    'full_location' => ($session->room->building ? $session->room->building->name.' - ' : '').$session->room->name,
                ] : null,
                'delivery_mode' => $session->delivery_mode,
                'status' => $session->status,
                'status_display' => ucfirst(str_replace('_', ' ', $session->status)),
                'expected_attendees' => $this->expectedAttendeesForSession($session),
                'attendance_marked' => $this->sessionHasActiveRosterAttendance($session),
                'is_today' => $session->session_date->isToday(),
                'is_upcoming' => $session->session_date->isFuture(),
                'is_current' => $this->isSessionCurrent($session),
                'color' => $this->getSessionColor($session->session_type, $session->status),
            ];
        })->groupBy('date')->toArray();
    }

    /**
     * Get schedule summary statistics
     */
    protected function getScheduleSummary($sessions, Carbon $startDate, Carbon $endDate): array
    {
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $upcomingSessions = $sessions->where('status', 'scheduled')->count();
        $cancelledSessions = $sessions->where('status', 'cancelled')->count();
        $inProgressSessions = $sessions->where('status', 'in_progress')->count();

        $totalMinutes = $sessions->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 1);

        $uniqueCourses = $sessions->pluck('course_offering_id')->unique()->count();
        $sessionsByType = $sessions->groupBy('session_type')->map->count()->toArray();
        $sessionsByDay = $sessions->groupBy(function ($session) {
            return $session->session_date->format('l');
        })->map->count()->toArray();

        // Calculate busiest day
        $busiestDay = collect($sessionsByDay)->sortDesc()->keys()->first() ?? 'None';

        // Calculate average sessions per day (only counting days with sessions)
        $daysWithSessions = $sessions->pluck('session_date')->unique()->count();
        $avgSessionsPerDay = $daysWithSessions > 0 ? round($totalSessions / $daysWithSessions, 1) : 0;

        return [
            'overview' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'upcoming_sessions' => $upcomingSessions,
                'cancelled_sessions' => $cancelledSessions,
                'in_progress_sessions' => $inProgressSessions,
                'unique_courses' => $uniqueCourses,
                'total_teaching_hours' => $totalHours,
                'average_sessions_per_day' => $avgSessionsPerDay,
            ],
            'schedule_pattern' => [
                'busiest_day' => $busiestDay,
                'earliest_start' => $sessions->min('start_time')?->format('H:i:s'),
                'latest_end' => $sessions->max('end_time')?->format('H:i:s'),
                'earliest_start_display' => $sessions->min('start_time')?->format('g:i A'),
                'latest_end_display' => $sessions->max('end_time')?->format('g:i A'),
            ],
            'distribution' => [
                'by_day' => $sessionsByDay,
                'by_session_type' => $sessionsByType,
            ],
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remainingMinutes.'m';
    }

    /**
     * Check if session is currently happening
     */
    protected function isSessionCurrent(ClassSession $session): bool
    {
        if (! $session->session_date->isToday()) {
            return false;
        }

        $now = now();
        $sessionStart = $session->session_date->copy()->setTimeFrom($session->start_time);
        $sessionEnd = $session->session_date->copy()->setTimeFrom($session->end_time);

        return $now->between($sessionStart, $sessionEnd);
    }

    /**
     * Get session color based on type and status
     */
    protected function getSessionColor(string $sessionType, string $status): string
    {
        if ($status === 'cancelled') {
            return '#9CA3AF'; // gray
        }

        if ($status === 'completed') {
            return '#10B981'; // green
        }

        return match ($sessionType) {
            'lecture' => '#3B82F6', // blue
            'tutorial' => '#8B5CF6', // purple
            'lab' => '#F59E0B', // amber
            'seminar' => '#EF4444', // red
            'workshop' => '#06B6D4', // cyan
            default => '#6B7280', // gray
        };
    }

    /**
     * Clear timetable-related caches
     */
    protected function clearTimetableCaches(Lecture $lecturer): void
    {
        Cache::forget("lecturer-dashboard:{$lecturer->lecturerId()}:*");
        Cache::tags(['lecturer-schedule', "lecturer-{$lecturer->lecturerId()}"])->flush();
        // Clear other relevant caches
    }
}
