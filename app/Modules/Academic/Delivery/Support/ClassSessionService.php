<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\SyllabusTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassSessionService
{
    /**
     * Auto-generate class sessions based on syllabus data and weekly schedule
     */
    public function generateClassSessions(
        CourseOffering $courseOffering,
        int $roomId,
        ?Carbon $startDateOverride = null,
        ?array $weeklySchedule = null,
        array $excludedDates = []
    ): Collection {
        Log::info("Generating class sessions for course offering {$courseOffering->id}");

        try {
            // Load required relationships with proper error checking
            $courseOffering->load([
                'syllabusTemplate.assessmentComponents.details',
                'semester',
            ]);

            // Check if syllabus template exists
            $syllabusTemplate = $courseOffering->syllabusTemplate;
            if (! $syllabusTemplate) {
                throw new \Exception('No syllabus template found for this course offering');
            }

            Log::info("Found syllabus template: {$syllabusTemplate->id} for course offering {$courseOffering->id}");

            // Calculate session details
            $totalRequired = $this->calculateTotalSessions($syllabusTemplate);
            $sessionDuration = $this->calculateSessionDuration($syllabusTemplate);

            // Check if class sessions already exist for this course offering and if they reached totalRequired
            $currentCount = ClassSession::where('course_offering_id', $courseOffering->id)->count();
            if ($currentCount >= $totalRequired) {
                throw new \Exception('Class sessions already reached the maximum sessions defined in the syllabus template (' . $totalRequired . ').');
            }

            Log::info("Current sessions: {$currentCount}, Target: {$totalRequired}, Duration: {$sessionDuration} hours");

            // Get semester dates and schedule
            $startDate = $startDateOverride ?: $this->getStartDate($courseOffering);
            $lectureId = $courseOffering->lecture_id;

            Log::info("Lecture ID: {$lectureId}");
            Log::info("Start date: {$startDate}");

            $sessions = collect();

            // Generate regular sessions using weekly schedule if provided
            if ($weeklySchedule) {
                Log::info("Using provided weekly schedule for session generation");
                $regularSessions = $this->generateSessionsWithWeeklySchedule(
                    $courseOffering,
                    $totalRequired,
                    $currentCount,
                    $startDate,
                    $weeklySchedule,
                    $sessionDuration,
                    $lectureId,
                    $roomId,
                    $excludedDates
                );
            } else {
                // Fallback to course offering schedule
                $scheduleDays = $courseOffering->schedule_days ?? ['Monday'];
                $startTime = $courseOffering->schedule_time_start ? $courseOffering->schedule_time_start->format('H:i') : '09:00';
                $endTime = $courseOffering->schedule_time_end ? $courseOffering->schedule_time_end->format('H:i') : '11:00';

                Log::info("Using course offering schedule: Days: " . implode(',', $scheduleDays) . ", Time: {$startTime}-{$endTime}");

                $regularSessions = $this->generateRegularSessions(
                    $courseOffering,
                    $totalRequired,
                    $currentCount,
                    $startDate,
                    $scheduleDays,
                    $startTime,
                    $endTime,
                    $sessionDuration,
                    $lectureId,
                    $roomId,
                    $excludedDates
                );
            }

            $sessions = $sessions->merge($regularSessions);
            Log::info("Generated " . $regularSessions->count() . " regular sessions");

            // // Generate assessment sessions
            // $assessmentSessions = $this->generateAssessmentSessions(
            //     $courseOffering,
            //     $syllabus,
            //     $startDate,
            //     $scheduleDays,
            //     $startTime,
            //     $endTime
            // );

            // $sessions = $sessions->merge($assessmentSessions);
            // Log::info("Generated {$assessmentSessions->count()} assessment sessions");

            Log::info("Total sessions generated: {$sessions->count()}");

            return $sessions;
        } catch (\Exception $e) {
            Log::error("Error generating class sessions for course offering {$courseOffering->id}: " . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Calculate total sessions needed based on syllabus template
     */
    private function calculateTotalSessions(SyllabusTemplate $syllabusTemplate): int
    {
        // If total_sessions is directly specified, use it
        if ($syllabusTemplate->total_sessions) {
            return $syllabusTemplate->total_sessions;
        }

        // Otherwise, calculate from total hours
        $totalHours = $syllabusTemplate->total_hours ?? 40; // Default 40 hours
        $hoursPerSession = 2; // Default 2 hours (could be made configurable)

        return (int) ceil($totalHours / $hoursPerSession);
    }

    /**
     * Calculate session duration based on syllabus template
     */
    private function calculateSessionDuration(SyllabusTemplate $syllabusTemplate): int
    {
        // If we have total hours and total sessions, calculate duration per session
        if ($syllabusTemplate->total_hours && $syllabusTemplate->total_sessions) {
            return (int) ceil($syllabusTemplate->total_hours / $syllabusTemplate->total_sessions);
        }

        // Default to 2 hours per session
        return 2;
    }

    /**
     * Get course start date
     */
    private function getStartDate(CourseOffering $courseOffering): Carbon
    {
        // Use registration start date or current date + 1 week
        $startDate = $courseOffering->registration_start_date
            ? Carbon::parse($courseOffering->registration_start_date)
            : Carbon::now()->addWeek();

        return $startDate;
    }

    /**
     * Generate regular lecture sessions
     */
    private function generateRegularSessions(
        CourseOffering $courseOffering,
        int $targetTotal,
        int $currentCount,
        Carbon $startDate,
        array $scheduleDays,
        string $startTime,
        string $endTime,
        int $sessionDuration,
        ?int $lectureId,
        int $roomId,
        array $excludedDates = []
    ): Collection {
        $sessions = collect();
        $currentDate = $startDate->copy();
        $currentTotal = $currentCount;

        // Convert schedule days to numbers for easier processing
        $dayNumbers = $this->convertDaysToNumbers($scheduleDays);

        while ($currentTotal < $targetTotal) {
            // Check if date is excluded
            if ($this->isDateExcluded($currentDate, $excludedDates)) {
                $currentDate->addDay();
                continue;
            }

            if (in_array($currentDate->dayOfWeek, $dayNumbers)) {
                $fullStartTime = $currentDate->copy()->setTimeFromTimeString($startTime);

                // Check if session already exists for this offering on this date and time
                $exists = ClassSession::where('course_offering_id', $courseOffering->id)
                    ->where('session_date', $currentDate->toDateString())
                    ->where('start_time', $fullStartTime)
                    ->exists();

                if ($exists) {
                    // Update current total if session found (though it should already be counted in currentCount)
                    // We increment currentDate and continue without creating a new one
                    $currentDate->addDay();
                    continue;
                }

                $sessionData = [
                    'course_offering_id' => $courseOffering->id,
                    'session_title' => 'Session ' . ($currentTotal + 1),
                    'session_description' => 'Regular class session',
                    'session_date' => $currentDate->toDateString(),
                    'start_time' => $fullStartTime,
                    'end_time' => $currentDate->copy()->setTimeFromTimeString($endTime),
                    'duration_minutes' => $sessionDuration * 60,
                    'session_type' => 'lecture',
                    'delivery_mode' => $courseOffering->delivery_mode,
                    'status' => 'scheduled',
                    'attendance_required' => true,
                    'attendance_tracking_enabled' => true,
                    'is_assessment' => false,
                    'is_recurring' => false,
                    'sequence_number' => $currentTotal + 1,
                    'lecture_id' => $lectureId,
                    'room_id' => $roomId,
                ];

                $session = ClassSession::create($sessionData);
                $sessions->push($session);
                $currentTotal++;
            }
            $currentDate->addDay();

            // Safety break to prevent infinite loops (semester is usually ~20 weeks)
            if ($currentDate->diffInDays($startDate) > 365) {
                break;
            }
        }

        return $sessions;
    }

    /**
     * Convert day names to Carbon day numbers
     */
    private function convertDaysToNumbers(array $dayNames): array
    {
        $dayMap = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 0,
        ];

        return array_map(function ($day) use ($dayMap) {
            return $dayMap[$day] ?? 1; // Default to Monday
        }, $dayNames);
    }

    /**
     * Extract enabled days from weekly schedule
     */
    private function getEnabledDaysFromSchedule(array $weeklySchedule): array
    {
        $enabledDays = [];
        $dayMap = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        foreach ($weeklySchedule as $day => $schedule) {
            if (isset($schedule['enabled']) && $schedule['enabled'] === true) {
                $enabledDays[] = $dayMap[$day] ?? ucfirst($day);
            }
        }

        return $enabledDays;
    }

    /**
     * Get schedule times for a specific day from weekly schedule
     */
    private function getScheduleTimesForDay(array $weeklySchedule, string $dayName): array
    {
        $dayKey = strtolower($dayName);

        if (!isset($weeklySchedule[$dayKey]) || !$weeklySchedule[$dayKey]['enabled']) {
            return [];
        }

        return [
            'start_time' => $weeklySchedule[$dayKey]['startTime'] ?? '09:00',
            'end_time' => $weeklySchedule[$dayKey]['endTime'] ?? '11:00',
        ];
    }

    /**
     * Generate sessions with different times for different days based on weekly schedule
     */
    private function generateSessionsWithWeeklySchedule(
        CourseOffering $courseOffering,
        int $targetTotal,
        int $currentCount,
        Carbon $startDate,
        array $weeklySchedule,
        int $sessionDuration,
        ?int $lectureId,
        int $roomId,
        array $excludedDates = []
    ): Collection {
        $sessions = collect();
        $currentDate = $startDate->copy();
        $currentTotal = $currentCount;

        // Get enabled days
        $enabledDays = $this->getEnabledDaysFromSchedule($weeklySchedule);
        $dayNumbers = $this->convertDaysToNumbers($enabledDays);

        while ($currentTotal < $targetTotal) {
            // Check if date is excluded
            if ($this->isDateExcluded($currentDate, $excludedDates)) {
                $currentDate->addDay();
                continue;
            }

            if (in_array($currentDate->dayOfWeek, $dayNumbers)) {
                // Get the day key
                $dayKey = strtolower($currentDate->format('l'));

                // Get settings for this specific day
                $daySettings = $weeklySchedule[$dayKey] ?? null;

                if ($daySettings && isset($daySettings['enabled']) && $daySettings['enabled'] && !empty($daySettings['timeRanges'])) {
                    foreach ($daySettings['timeRanges'] as $range) {
                        if ($currentTotal >= $targetTotal) {
                            break 2;
                        }

                        $fullStartTime = $currentDate->copy()->setTimeFromTimeString($range['startTime']);

                        // Check if session already exists for this offering on this date and time
                        $exists = ClassSession::where('course_offering_id', $courseOffering->id)
                            ->where('session_date', $currentDate->toDateString())
                            ->where('start_time', $fullStartTime)
                            ->exists();

                        if ($exists) {
                            // Already exists, skip this slot but it's part of currentTotal
                            continue;
                        }

                        $sessionData = [
                            'course_offering_id' => $courseOffering->id,
                            'session_title' => 'Session ' . ($currentTotal + 1),
                            'session_description' => 'Regular class session',
                            'session_date' => $currentDate->toDateString(),
                            'start_time' => $fullStartTime,
                            'end_time' => $currentDate->copy()->setTimeFromTimeString($range['endTime']),
                            'duration_minutes' => $this->calculateDurationMinutes($range['startTime'], $range['endTime']),
                            'session_type' => 'lecture',
                            'delivery_mode' => $courseOffering->delivery_mode,
                            'status' => 'scheduled',
                            'attendance_required' => true,
                            'attendance_tracking_enabled' => true,
                            'is_recurring' => false,
                            'sequence_number' => $currentTotal + 1,
                            'lecture_id' => $lectureId,
                            'room_id' => $roomId,
                        ];

                        $session = ClassSession::create($sessionData);
                        $sessions->push($session);
                        $currentTotal++;
                    }
                }
            }
            $currentDate->addDay();

            // Safety break to prevent infinite loops (semester is usually ~20 weeks)
            if ($currentDate->diffInDays($startDate) > 365) {
                break;
            }
        }

        return $sessions;
    }

    /**
     * Calculate duration in minutes between two time strings
     */
    private function calculateDurationMinutes(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Delete all class sessions for a course offering
     */
    public function deleteClassSessions(CourseOffering $courseOffering): bool
    {
        Log::info("Deleting class sessions for course offering {$courseOffering->id}");

        return ClassSession::where('course_offering_id', $courseOffering->id)->delete() > 0;
    }

    /**
     * Get class sessions for a course offering
     */
    public function getClassSessions(CourseOffering $courseOffering): Collection
    {
        return ClassSession::where('course_offering_id', $courseOffering->id)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Create a new class session
     */
    public function createClassSession(array $data): ClassSession
    {
        return DB::transaction(function () use ($data) {
            // Calculate duration in minutes if not provided
            if (! isset($data['duration_minutes']) && isset($data['start_time'], $data['end_time'])) {
                $start = Carbon::createFromFormat('H:i', $data['start_time']);
                $end = Carbon::createFromFormat('H:i', $data['end_time']);
                $data['duration_minutes'] = $start->diffInMinutes($end);
            }

            return ClassSession::create($data);
        });
    }

    /**
     * Update an existing class session
     */
    public function updateClassSession(ClassSession $classSession, array $data): ClassSession
    {
        return DB::transaction(function () use ($classSession, $data) {
            // Calculate duration in minutes if not provided
            if (! isset($data['duration_minutes']) && isset($data['start_time'], $data['end_time'])) {
                $start = Carbon::createFromFormat('H:i', $data['start_time']);
                $end = Carbon::createFromFormat('H:i', $data['end_time']);
                $data['duration_minutes'] = $start->diffInMinutes($end);
            }

            $classSession->update($data);

            return $classSession->fresh();
        });
    }

    /**
     * Delete a class session
     */
    public function deleteClassSession(ClassSession $classSession): bool
    {
        return DB::transaction(function () use ($classSession) {
            // Delete associated attendance records first
            $classSession->attendances()->delete();

            return $classSession->delete();
        });
    }

    /**
     * Delete multiple class sessions
     */
    public function deleteBulk(array $sessionIds): int
    {
        Log::info('Deleting bulk class sessions: ' . implode(',', $sessionIds));

        return DB::transaction(function () use ($sessionIds) {
            // Delete associated attendance records first
            DB::table('attendances')->whereIn('class_session_id', $sessionIds)->delete();

            return ClassSession::whereIn('id', $sessionIds)->delete();
        });
    }

    /**
     * Get paginated class sessions with filters
     */
    public function getPaginatedSessions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ClassSession::with([
            'courseOffering.unit',
            'lecture',
            'attendances',
            'room:id,name'
        ])->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get a single class session with all relationships
     */
    public function getSessionWithRelations(int $sessionId): ?ClassSession
    {
        return ClassSession::with([
            'courseOffering.unit',
            'lecture',
            'attendances.student',
        ])->find($sessionId);
    }

    /**
     * Get upcoming sessions for a course offering
     */
    public function getUpcomingSessions(int $courseOfferingId, int $limit = 5): Collection
    {
        return ClassSession::where('course_offering_id', $courseOfferingId)
            ->where('session_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Get sessions by status
     */
    public function getSessionsByStatus(string $status, ?int $limit = null): Collection
    {
        $query = ClassSession::with(['courseOffering.unit', 'lecture'])
            ->where('status', $status)
            ->orderBy('session_date', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Mark session as started
     */
    public function startSession(ClassSession $session): ClassSession
    {
        return DB::transaction(function () use ($session) {
            $session->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    /**
     * Mark session as completed
     */
    public function completeSession(ClassSession $session): ClassSession
    {
        return DB::transaction(function () use ($session) {
            $session->update([
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    /**
     * Cancel a session
     */
    public function cancelSession(ClassSession $session, ?string $reason = null): ClassSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $session->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(): array
    {
        return [
            'total_sessions' => ClassSession::count(),
            'scheduled' => ClassSession::byStatus('scheduled')->count(),
            'in_progress' => ClassSession::byStatus('in_progress')->count(),
            'completed' => ClassSession::byStatus('completed')->count(),
            'cancelled' => ClassSession::byStatus('cancelled')->count(),
            'today_sessions' => ClassSession::whereDate('session_date', today())->count(),
            'upcoming_sessions' => ClassSession::where('session_date', '>', today())->count(),
        ];
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('session_title', 'like', "%{$search}%")
                    ->orWhere('session_description', 'like', "%{$search}%")
                    ->orWhereHas('courseOffering.unit', function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lecture', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['session_type'])) {
            $query->where('session_type', $filters['session_type']);
        }

        if (! empty($filters['delivery_mode'])) {
            $query->where('delivery_mode', $filters['delivery_mode']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('session_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('session_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['course_offering_id'])) {
            $query->where('course_offering_id', $filters['course_offering_id']);
        }

        if (! empty($filters['lecture_id'])) {
            $query->where('lecture_id', $filters['lecture_id']);
        }

        if (isset($filters['attendance_required'])) {
            $query->where('attendance_required', $filters['attendance_required']);
        }

        if (isset($filters['is_assessment'])) {
            $query->where('is_assessment', $filters['is_assessment']);
        }
    }

    /**
     * Duplicate a session for recurring sessions
     */
    public function duplicateSession(ClassSession $session, string $newDate, array $overrides = []): ClassSession
    {
        return DB::transaction(function () use ($session, $newDate, $overrides) {
            $data = $session->toArray();

            // Remove fields that shouldn't be duplicated
            unset($data['id'], $data['created_at'], $data['updated_at']);

            // Update the date and any overrides
            $data['session_date'] = $newDate;
            $data['status'] = 'scheduled';
            $data['started_at'] = null;
            $data['ended_at'] = null;
            $data['cancelled_at'] = null;

            // Apply overrides
            $data = array_merge($data, $overrides);

            return ClassSession::create($data);
        });
    }

    /**
     * Get all course offerings for dropdowns
     */
    public function getCourseOfferingsForSelect(): Collection
    {
        return CourseOffering::with('unit')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Generate attendance records for students who don't have attendance yet in a class session
     */
    public function generateAttendanceForSession(ClassSession $session): array
    {
        return DB::transaction(function () use ($session) {
            // Get all enrolled students for this course offering
            $enrolledStudents = $session->courseOffering
                ->courseRegistrations()
                ->with('student')
                // ->where('registration_status', 'confirmed')
                ->get()
                ->pluck('student')
                ->filter(); // Remove any null students

            if ($enrolledStudents->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No enrolled students found for this course offering',
                    'student_count' => 0,
                ];
            }

            // Get students who already have attendance records
            $studentsWithAttendance = $session->attendances()->pluck('student_id')->toArray();

            // Filter out students who already have attendance records
            $studentsWithoutAttendance = $enrolledStudents->filter(function ($student) use ($studentsWithAttendance) {
                return !in_array($student->id, $studentsWithAttendance);
            });

            if ($studentsWithoutAttendance->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'All enrolled students already have attendance records for this session',
                    'existing_count' => count($studentsWithAttendance),
                    'student_count' => $enrolledStudents->count(),
                ];
            }

            // Create attendance records for students without attendance
            $attendanceRecords = [];
            foreach ($studentsWithoutAttendance as $student) {
                $attendanceRecords[] = [
                    'class_session_id' => $session->id,
                    'student_id' => $student->id,
                    'status' => 'absent', // Default to absent, can be updated later
                    'recording_method' => 'manual',
                    'is_verified' => false,
                    'affects_grade' => $session->attendance_required,
                    'is_makeup_allowed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert attendance records
            DB::table('attendances')->insert($attendanceRecords);

            // Update session expected attendees count (only if it's not already set)
            if (!$session->expected_attendees) {
                $session->update([
                    'expected_attendees' => $enrolledStudents->count(),
                    'actual_attendees' => $session->attendances()->whereIn('status', ['present', 'late'])->count(),
                ]);

                // Calculate attendance percentage
                $attendancePercentage = $session->expected_attendees > 0
                    ? round(($session->actual_attendees / $session->expected_attendees) * 100, 2)
                    : 0.0;

                $session->update(['attendance_percentage' => $attendancePercentage]);
            }

            return [
                'success' => true,
                'message' => count($studentsWithAttendance) > 0
                    ? 'Attendance records generated for remaining students'
                    : 'Attendance records generated successfully',
                'student_count' => $enrolledStudents->count(),
                'existing_records' => count($studentsWithAttendance),
                'new_records_created' => count($attendanceRecords),
            ];
        });
    }

    /**
     * Check if a date falls within any excluded ranges
     */
    private function isDateExcluded(Carbon $date, array $excludedDates): bool
    {
        foreach ($excludedDates as $range) {
            $start = Carbon::parse($range['start'])->startOfDay();
            $end = Carbon::parse($range['end'])->endOfDay();

            if ($date->between($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
