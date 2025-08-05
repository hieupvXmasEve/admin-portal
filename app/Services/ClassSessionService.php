<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Syllabus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassSessionService
{
    /**
     * Auto-generate class sessions based on syllabus data
     */
    public function generateClassSessions(CourseOffering $courseOffering, int $roomId): Collection
    {
        Log::info("Generating class sessions for course offering {$courseOffering->id}");

        try {
            // Check if class sessions already exist for this course offering
            $existingSessions = ClassSession::where('course_offering_id', $courseOffering->id)->count();
            if ($existingSessions > 0) {
                throw new \Exception('Class sessions already exist for this course offering');
            }
            // Load required relationships with proper error checking
            $courseOffering->load([
                'curriculumUnit.syllabus.assessmentComponents.details',
                'semester',
            ]);

            // First check if curriculumUnit exists
            if (! $courseOffering->curriculumUnit) {
                throw new \Exception('No curriculum unit found for this course offering');
            }

            // Then check if syllabus exists
            $syllabus = $courseOffering->curriculumUnit->syllabus;
            if (! $syllabus) {
                throw new \Exception('No syllabus found for this course offering');
            }

            Log::info("Found syllabus: {$syllabus->id} for course offering {$courseOffering->id}");

            // Calculate session details
            $totalSessions = $this->calculateTotalSessions($syllabus);
            $sessionDuration = $syllabus->hours_per_session ?? 2; // Default 2 hours

            Log::info("Total sessions to generate: {$totalSessions}, Duration: {$sessionDuration} hours");

            // Get semester dates and schedule
            $startDate = $this->getStartDate($courseOffering);
            $scheduleDays = $courseOffering->schedule_days ?? ['Monday'];
            $startTime = $courseOffering->schedule_time_start ? $courseOffering->schedule_time_start->format('H:i') : '09:00';
            $endTime = $courseOffering->schedule_time_end ? $courseOffering->schedule_time_end->format('H:i') : '11:00';
            $lectureId = $courseOffering->lecture_id;

            Log::info("Lecture ID: {$lectureId}");
            Log::info("Schedule: {$startDate}, Days: " . implode(',', $scheduleDays) . ", Time: {$startTime}-{$endTime}");

            $sessions = collect();

            // Generate regular sessions
            $regularSessions = $this->generateRegularSessions(
                $courseOffering,
                $totalSessions,
                $startDate,
                $scheduleDays,
                $startTime,
                $endTime,
                $sessionDuration,
                $lectureId,
                $roomId
            );

            $sessions = $sessions->merge($regularSessions);
            Log::info("Generated {$regularSessions} regular sessions");

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
     * Calculate total sessions needed based on syllabus
     */
    private function calculateTotalSessions(Syllabus $syllabus): int
    {
        $totalHours = $syllabus->total_hours ?? 40; // Default 40 hours
        $hoursPerSession = $syllabus->hours_per_session ?? 2; // Default 2 hours

        return (int) ceil($totalHours / $hoursPerSession);
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
        int $totalSessions,
        Carbon $startDate,
        array $scheduleDays,
        string $startTime,
        string $endTime,
        int $sessionDuration,
        int $lectureId,
        int $roomId
    ): Collection {
        $sessions = collect();
        $currentDate = $startDate->copy();
        $sessionCount = 0;

        // Convert schedule days to numbers for easier processing
        $dayNumbers = $this->convertDaysToNumbers($scheduleDays);

        while ($sessionCount < $totalSessions) {
            if (in_array($currentDate->dayOfWeek, $dayNumbers)) {
                $sessionData = [
                    'course_offering_id' => $courseOffering->id,
                    'session_title' => 'Session ' . ($sessionCount + 1),
                    'session_description' => 'Regular class session',
                    'session_date' => $currentDate->toDateString(),
                    'start_time' => $currentDate->copy()->setTimeFromTimeString($startTime),
                    'end_time' => $currentDate->copy()->setTimeFromTimeString($endTime),
                    'duration_minutes' => $sessionDuration * 60,
                    'session_type' => 'lecture',
                    'delivery_mode' => $courseOffering->delivery_mode,
                    'status' => 'scheduled',
                    'attendance_required' => true,
                    'attendance_tracking_enabled' => true,
                    'is_assessment' => false,
                    'is_recurring' => false,
                    'sequence_number' => $sessionCount + 1,
                    'lecture_id' => $lectureId,
                    'room_id' => $roomId,
                ];

                $session = ClassSession::create($sessionData);
                $sessions->push($session);
                $sessionCount++;
            }
            $currentDate->addDay();
        }

        return $sessions;
    }

    /**
     * Generate assessment sessions based on syllabus assessment components
     */
    private function generateAssessmentSessions(
        CourseOffering $courseOffering,
        Syllabus $syllabus,
        Carbon $startDate,
        array $scheduleDays,
        string $startTime,
        string $endTime
    ): Collection {
        $sessions = collect();
        $assessmentComponents = $syllabus->assessmentComponents;

        if ($assessmentComponents->isEmpty()) {
            return $sessions;
        }

        // Calculate assessment dates (spread throughout the semester)
        $semesterWeeks = 16; // Typical semester length
        $weekInterval = $semesterWeeks / ($assessmentComponents->count() + 1);

        $assessmentComponents->each(function ($component, $index) use (
            $courseOffering,
            $startDate,
            $scheduleDays,
            $startTime,
            $endTime,
            &$sessions,
            $weekInterval
        ) {
            $assessmentDate = $startDate->copy()->addWeeks(($index + 1) * $weekInterval);

            // Find the next scheduled day
            $dayNumbers = $this->convertDaysToNumbers($scheduleDays);
            while (! in_array($assessmentDate->dayOfWeek, $dayNumbers)) {
                $assessmentDate->addDay();
            }

            $sessionData = [
                'course_offering_id' => $courseOffering->id,
                'session_title' => $component->name . ' Assessment',
                'session_description' => 'Assessment session for ' . $component->name,
                'session_date' => $assessmentDate->toDateString(),
                'start_time' => $assessmentDate->copy()->setTimeFromTimeString($startTime),
                'end_time' => $assessmentDate->copy()->setTimeFromTimeString($endTime),
                'duration_minutes' => 120, // Default 2 hours for assessments
                'session_type' => 'assessment',
                'delivery_mode' => $courseOffering->delivery_mode,
                'status' => 'scheduled',
                'attendance_required' => true,
                'attendance_tracking_enabled' => true,
                'is_assessment' => true,
                'assessment_weight' => $component->weight,
                'assessment_duration_minutes' => 120,
                'is_recurring' => false,
                'sequence_number' => 1000 + $index, // High numbers to distinguish from regular sessions
            ];

            $session = ClassSession::create($sessionData);
            $sessions->push($session);
        });

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
     * Get paginated class sessions with filters
     */
    public function getPaginatedSessions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ClassSession::with([
            'courseOffering.curriculumUnit.unit',
            'lecture',
            'attendances',
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
            'courseOffering.curriculumUnit.unit',
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
        $query = ClassSession::with(['courseOffering.curriculumUnit.unit', 'lecture'])
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
                    ->orWhereHas('courseOffering.curriculumUnit.unit', function ($q) use ($search) {
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
        return CourseOffering::with('curriculumUnit.unit')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Generate attendance records for all enrolled students in a class session
     */
    public function generateAttendanceForSession(ClassSession $session): array
    {
        return DB::transaction(function () use ($session) {
            // Check if attendance already exists for this session
            $existingAttendance = $session->attendances()->count();

            if ($existingAttendance > 0) {
                return [
                    'success' => false,
                    'message' => 'Attendance records already exist for this session',
                    'existing_count' => $existingAttendance,
                ];
            }

            // Get all enrolled students for this course offering
            $enrolledStudents = $session->courseOffering
                ->courseRegistrations()
                ->with('student')
                ->where('registration_status', 'confirmed')
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

            // Create attendance records for all enrolled students
            $attendanceRecords = [];
            foreach ($enrolledStudents as $student) {
                $attendanceRecords[] = [
                    'class_session_id' => $session->id,
                    'student_code' => $student->id,
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

            // Update session expected attendees count
            $session->update([
                'expected_attendees' => $enrolledStudents->count(),
                'actual_attendees' => 0, // Will be updated when attendance is taken
                'attendance_percentage' => 0.0,
            ]);

            return [
                'success' => true,
                'message' => 'Attendance records generated successfully',
                'student_count' => $enrolledStudents->count(),
                'records_created' => count($attendanceRecords),
            ];
        });
    }
}
