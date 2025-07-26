<?php

declare(strict_types=1);

namespace App\Services\V1\Lecturer;

use App\Models\Lecture;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LecturerTimetableService
{
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

        $cacheKey = "lecturer-timetable:{$lecturer->id}:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}:{$view}";
        
        return Cache::remember($cacheKey, 300, function () use ($lecturer, $startDate, $endDate, $view) {
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
            ];
        });
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
        
        $sessions = $lecturer->classSessions()
            ->with(['courseOffering.curriculumUnit', 'room'])
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
            $courseOffering = $lecturer->courseOfferings()
                ->where('id', $sessionData['course_offering_id'])
                ->first();

            if (!$courseOffering) {
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

            if (!empty($conflicts)) {
                throw new \Exception('Scheduling conflict detected: ' . implode(', ', $conflicts));
            }

            // Create the session
            $session = ClassSession::create([
                'course_offering_id' => $sessionData['course_offering_id'],
                'lecture_id' => $lecturer->id,
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
                'expected_attendees' => $courseOffering->current_enrollment,
                'learning_objectives' => $sessionData['learning_objectives'] ?? null,
                'topics_covered' => $sessionData['topics_covered'] ?? null,
                'required_materials' => $sessionData['required_materials'] ?? null,
                'preparation_notes' => $sessionData['preparation_notes'] ?? null,
            ]);

            // Clear relevant caches
            $this->clearTimetableCaches($lecturer);

            return [
                'session' => $this->formatSessionDetails($session->load(['courseOffering.curriculumUnit', 'room'])),
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
            $session = $lecturer->classSessions()
                ->where('id', $sessionId)
                ->first();

            if (!$session) {
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

                if (!empty($conflicts)) {
                    throw new \Exception('Scheduling conflict detected: ' . implode(', ', $conflicts));
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
                'session' => $this->formatSessionDetails($session->load(['courseOffering.curriculumUnit', 'room'])),
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
        string $reason = null
    ): array {
        return DB::transaction(function () use ($lecturer, $sessionId, $reason) {
            $session = $lecturer->classSessions()
                ->where('id', $sessionId)
                ->first();

            if (!$session) {
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
     * Get available rooms for a time slot
     */
    public function getAvailableRooms(
        string $date, 
        string $startTime, 
        string $endTime, 
        ?int $excludeSessionId = null
    ): array {
        $conflictingRoomIds = ClassSession::where('session_date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($excludeSessionId, function ($query, $excludeSessionId) {
                return $query->where('id', '!=', $excludeSessionId);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    // Session starts during the requested time
                    $q->where('start_time', '>=', $startTime)
                      ->where('start_time', '<', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Session ends during the requested time
                    $q->where('end_time', '>', $startTime)
                      ->where('end_time', '<=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Session encompasses the requested time
                    $q->where('start_time', '<=', $startTime)
                      ->where('end_time', '>=', $endTime);
                });
            })
            ->whereNotNull('room_id')
            ->pluck('room_id')
            ->toArray();

        return Room::whereNotIn('id', $conflictingRoomIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'building' => $room->building,
                    'floor' => $room->floor,
                    'capacity' => $room->capacity,
                    'room_type' => $room->room_type,
                    'equipment' => $room->equipment,
                ];
            })
            ->toArray();
    }

    /**
     * Get sessions in a specific period
     */
    protected function getSessionsInPeriod(
        Lecture $lecturer, 
        Carbon $startDate, 
        Carbon $endDate
    ): \Illuminate\Database\Eloquent\Collection {
        return $lecturer->classSessions()
            ->with(['courseOffering.curriculumUnit', 'room'])
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
                'unit_code' => $session->courseOffering->curriculumUnit->unit_code,
                'unit_name' => $session->courseOffering->curriculumUnit->unit_name,
                'section_code' => $session->courseOffering->section_code,
            ],
            'room' => $session->room ? [
                'id' => $session->room->id,
                'name' => $session->room->name,
                'building' => $session->room->building,
                'capacity' => $session->room->capacity,
            ] : null,
            'attendance_marked' => $session->attendance_marked,
            'expected_attendees' => $session->expected_attendees,
            'learning_objectives' => $session->learning_objectives,
            'topics_covered' => $session->topics_covered,
        ];
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
        $lecturerConflicts = $lecturer->classSessions()
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
     * Clear timetable-related caches
     */
    protected function clearTimetableCaches(Lecture $lecturer): void
    {
        Cache::forget("lecturer-dashboard:{$lecturer->id}:*");
        // Clear other relevant caches
    }
}
