<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LecturerAttendanceService
{
    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    /**
     * Get attendance sessions for lecturer with filtering
     */
    public function getAttendanceSessions(
        int $lecturerId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ClassSession::where('lecture_id', $lecturerId)
            ->with([
                'courseOffering',
                'courseOffering.semester',
                'courseOffering.classRosterRegistrations',
                'attendances.student',
            ])
            ->orderBy('session_date', 'desc');

        // Apply filters
        $this->applySessionFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get session attendance details
     */
    public function getSessionAttendance(int $lecturerId, int $sessionId): ?array
    {
        $session = ClassSession::where('lecture_id', $lecturerId)
            ->with([
                'courseOffering',
                'courseOffering.semester',
                'courseOffering.classRosterRegistrations.student.program',
                'courseOffering.classRosterRegistrations.student.specialization',
                'courseOffering.classRosterRegistrations.student.campus',
                'attendances.student',
                'attendances.recordedBy',
            ])
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return null;
        }

        $attendanceRecords = $session->attendances->keyBy('student_id');

        $rosterRegistrations = $session->courseOffering->classRosterRegistrations
            ->filter(fn (CourseRegistration $registration) => $registration->student !== null);
        $lifecycleStatuses = $this->lifecycleStatuses->statusesFor(
            $rosterRegistrations->map(fn (CourseRegistration $registration): int => (int) $registration->student->id)->all(),
        );

        $students = $rosterRegistrations
            ->map(function (CourseRegistration $registration) use ($attendanceRecords, $lifecycleStatuses) {
                $student = $registration->student;
                $status = $lifecycleStatuses[(int) $student->id] ?? $student->status;
                $isRosterActive = $registration->isClassRosterActive();
                $rosterStatus = $registration->classRosterStatus();
                $rosterStatusLabel = $registration->classRosterStatusLabel();
                $attendance = $attendanceRecords->get($student->id);

                return [
                    'student_id' => $student->id,
                    'student_number' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'is_roster_active' => $isRosterActive,
                    'roster_status' => $rosterStatus,
                    'roster_status_label' => $rosterStatusLabel,
                    'can_mark_attendance' => $isRosterActive,
                    'roster' => [
                        'is_active' => $isRosterActive,
                        'status' => $rosterStatus,
                        'status_label' => $rosterStatusLabel,
                        'can_mark_attendance' => $isRosterActive,
                    ],
                    'attendance' => [
                        'id' => $attendance?->id,
                        'status' => $attendance?->status ?? 'not_marked',
                        'check_in_time' => $attendance?->check_in_time?->format('Y-m-d H:i:s'),
                        'check_out_time' => $attendance?->check_out_time?->format('Y-m-d H:i:s'),
                        'minutes_late' => $attendance?->minutes_late ?? 0,
                        'minutes_present' => $attendance?->minutes_present,
                        'participation_level' => $attendance?->participation_level,
                        'participation_score' => $attendance?->participation_score,
                        'notes' => $attendance?->notes,
                        'excuse_reason' => $attendance?->excuse_reason,
                        'recording_method' => $attendance?->recording_method ?? 'manual',
                        'is_verified' => $attendance?->is_verified ?? false,
                        'recorded_by' => $attendance?->recordedBy?->full_name,
                        'recorded_at' => $attendance?->created_at?->format('Y-m-d H:i:s'),
                    ],
                    'student_info' => [
                        'program' => $student->program?->program_name,
                        'specialization' => $student->specialization?->specialization_name,
                        'academic_status' => $student->academic_status,
                        'status' => $status,
                        'campus' => $student->campus?->name,
                        'admission_date' => $student->admission_date?->format('Y-m-d'),
                        'expected_graduation_date' => $student->expected_graduation_date?->format('Y-m-d'),
                    ],
                ];
            });
        $activeStudents = $students
            ->where('is_roster_active', true)
            ->values();
        $attendanceCounts = $this->getAttendanceCounts($activeStudents);

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->session_title,
                'date' => $session->session_date->format('Y-m-d'),
                'start_time' => $session->start_time->format('H:i'),
                'end_time' => $session->end_time->format('H:i'),
                'status' => $session->status,
                'session_type' => $session->session_type,
                'delivery_mode' => $session->delivery_mode,
                'attendance_required' => $session->attendance_required,
                'attendance_marked' => $activeStudents->isNotEmpty() && $attendanceCounts['not_marked'] === 0,
                'expected_attendees' => $activeStudents->count(),
                'actual_attendees' => $attendanceCounts['present'] + $attendanceCounts['late'],
                'course' => [
                    'id' => $session->courseOffering->id,
                    'unit_code' => $session->courseOffering->unit->code,
                    'unit_name' => $session->courseOffering->unit->name,
                    'section_code' => $session->courseOffering->section_code,
                    'semester' => $session->courseOffering->semester->name ?? null,
                ],
            ],
            'students' => $students->values()->toArray(),
            'summary' => [
                'total_enrolled' => $activeStudents->count(),
                'total_roster' => $students->count(),
                'active_roster' => $activeStudents->count(),
                'inactive_roster' => $students->count() - $activeStudents->count(),
                'attendance_counts' => $attendanceCounts,
                'attendance_rate' => $this->calculateAttendanceRate($activeStudents),
                'completion_rate' => $this->calculateCompletionRate($activeStudents),
            ],
        ];
    }

    /**
     * Mark attendance for a session
     */
    public function markAttendance(
        int $lecturerId,
        int $sessionId,
        array $attendanceData
    ): array {
        $session = ClassSession::where('lecture_id', $lecturerId)
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            throw new \Exception('Session not found or access denied');
        }

        return DB::transaction(function () use ($session, $attendanceData, $lecturerId) {
            $results = [];
            $totalMarked = 0;
            $errors = [];

            foreach ($attendanceData as $record) {
                try {
                    $attendance = $this->markStudentAttendance($session, $record, $lecturerId);
                    $results[] = [
                        'student_id' => $record['student_id'],
                        'status' => 'success',
                        'attendance_id' => $attendance->id,
                    ];
                    $totalMarked++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $record['student_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Update session attendance status
            if ($totalMarked > 0) {
                $this->updateSessionAttendanceStatus($session);
            }

            return [
                'session_id' => $session->id,
                'total_marked' => $totalMarked,
                'total_errors' => count($errors),
                'results' => $results,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Generate attendance records for all enrolled students in a session
     */
    public function generateAttendanceRecords(int $lecturerId, int $sessionId): array
    {
        $session = ClassSession::where('lecture_id', $lecturerId)
            ->with([
                'courseOffering.activeClassRosterRegistrations.student',
            ])
            ->where('id', $sessionId)
            ->first();
        Log::debug('session', ['session' => $session]);
        if (! $session) {
            throw new \Exception('Session not found or access denied');
        }

        $enrolledStudents = $session->courseOffering->activeClassRosterRegistrations;

        if ($enrolledStudents->isEmpty()) {
            return [
                'session_id' => $sessionId,
                'total_records_created' => 0,
                'students' => [],
                'message' => 'No enrolled students found for this session',
            ];
        }

        return DB::transaction(function () use ($enrolledStudents, $lecturerId, $sessionId) {
            $createdRecords = [];
            $existingCount = 0;

            foreach ($enrolledStudents as $registration) {
                $existingAttendance = Attendance::where('class_session_id', $sessionId)
                    ->where('student_id', $registration->student_id)
                    ->first();

                if ($existingAttendance) {
                    $existingCount++;

                    continue;
                }

                $attendance = Attendance::create([
                    'class_session_id' => $sessionId,
                    'student_id' => $registration->student_id,
                    'recorded_by_lecture_id' => $lecturerId,
                    'status' => 'absent', // Default status
                    'recording_method' => 'manual',
                    'affects_grade' => true,
                ]);

                $createdRecords[] = [
                    'student_id' => $registration->student_id,
                    'student_name' => $registration->student->full_name ?? 'Unknown',
                    'student_number' => $registration->student->student_number ?? 'Unknown',
                    'attendance_id' => $attendance->id,
                    'status' => $attendance->status,
                ];
            }

            $message = count($createdRecords) > 0
                ? 'Attendance records generated successfully'
                : 'Attendance records already exist for all enrolled students';

            if ($existingCount > 0 && count($createdRecords) > 0) {
                $message = 'Generated '.count($createdRecords).' new records. '.$existingCount.' records already existed.';
            }

            return [
                'session_id' => $sessionId,
                'total_records_created' => count($createdRecords),
                'existing_records' => $existingCount,
                // 'students' => $createdRecords,
                'message' => $message,
            ];
        });
    }

    /**
     * Get attendance analytics for course
     */
    public function getCourseAttendanceAnalytics(
        int $lecturerId,
        int $courseOfferingId,
        array $filters = []
    ): array {
        $courseOffering = CourseOffering::query()
            ->where('lecture_id', $lecturerId)
            ->whereKey($courseOfferingId)
            ->first();

        if (! $courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        $cacheKey = "lecturer-attendance-analytics:{$lecturerId}:{$courseOfferingId}:".md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($courseOffering, $filters) {
            return [
                'course_info' => $this->getCourseInfo($courseOffering),
                'overall_statistics' => $this->getOverallAttendanceStats($courseOffering, $filters),
                'session_breakdown' => $this->getSessionBreakdown($courseOffering, $filters),
                'student_breakdown' => $this->getStudentAttendanceBreakdown($courseOffering, $filters),
                'trends' => $this->getAttendanceTrends($courseOffering, $filters),
                'alerts' => $this->getCourseAttendanceAlerts($courseOffering),
            ];
        });
    }

    /**
     * Get attendance alerts for lecturer
     */
    public function getAttendanceAlerts(int $lecturerId, array $filters = []): array
    {
        $alerts = [];

        // Sessions requiring attention (unmarked attendance)
        $unmmarkedSessions = ClassSession::where('lecture_id', $lecturerId)
            ->where('session_date', '<', now()->subHours(2))
            ->where('status', 'completed')
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'attendances'])
            ->get()
            ->filter(fn ($session) => ! $this->sessionHasActiveRosterAttendance($session));

        foreach ($unmmarkedSessions as $session) {
            $alerts[] = [
                'type' => 'unmarked_attendance',
                'priority' => 'high',
                'session_id' => $session->id,
                'message' => "Attendance not marked for {$session->courseOffering->unit->code} on {$session->session_date->format('M d')}",
                'course' => $session->courseOffering->unit->code,
                'date' => $session->session_date->format('Y-m-d'),
                'days_overdue' => now()->diffInDays($session->session_date),
            ];
        }

        // Low attendance students
        $lowAttendanceStudents = $this->getLowAttendanceStudents($lecturerId, 75);
        foreach ($lowAttendanceStudents as $student) {
            $alerts[] = [
                'type' => 'low_attendance',
                'priority' => $student['attendance_percentage'] < 50 ? 'high' : 'medium',
                'student_id' => $student['student_id'],
                'message' => "{$student['student_name']} has {$student['attendance_percentage']}% attendance in {$student['course_code']}",
                'course' => $student['course_code'],
                'attendance_percentage' => $student['attendance_percentage'],
            ];
        }

        return $alerts;
    }

    /**
     * Export attendance data for course
     */
    public function exportAttendanceData(
        int $lecturerId,
        int $courseOfferingId,
        string $format = 'csv',
        ?string $lecturerName = null,
    ): array {
        $courseOffering = CourseOffering::query()
            ->where('lecture_id', $lecturerId)
            ->whereKey($courseOfferingId)
            ->first();

        if (! $courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        $attendanceData = $this->getDetailedAttendanceData($courseOffering);

        return [
            'course_info' => $this->getCourseInfo($courseOffering),
            'export_format' => $format,
            'data' => $attendanceData,
            'generated_at' => now()->toISOString(),
            'generated_by' => $lecturerName,
        ];
    }

    /**
     * Apply session filters to query
     */
    protected function applySessionFilters($query, array $filters): void
    {
        if (! empty($filters['course_offering_id'])) {
            $query->where('course_offering_id', $filters['course_offering_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->whereHas('courseOffering', function ($q) use ($filters) {
                $q->where('semester_id', $filters['semester_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['attendance_status'])) {
            if ($filters['attendance_status'] === 'marked') {
                $query->whereHas('attendances');
            } elseif ($filters['attendance_status'] === 'unmarked') {
                $query->whereDoesntHave('attendances');
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('session_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('session_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Mark attendance for individual student
     */
    protected function markStudentAttendance(
        ClassSession $session,
        array $record,
        int $lecturerId
    ): Attendance {
        $isRosterStudent = CourseRegistration::query()
            ->where('course_offering_id', $session->course_offering_id)
            ->where('student_id', $record['student_id'])
            ->activeForClassRoster()
            ->exists();

        if (! $isRosterStudent) {
            throw new \Exception('Student is not active in this class roster');
        }

        $attendanceData = [
            'class_session_id' => $session->id,
            'student_id' => $record['student_id'],
            'status' => $record['status'],
            'recorded_by_lecture_id' => $lecturerId,
            'check_in_time' => $record['check_in_time'] ?? null,
            'minutes_late' => $record['minutes_late'] ?? 0,
            'participation_score' => $record['participation_score'] ?? null,
            'notes' => $record['notes'] ?? null,
            'recording_method' => 'manual',
        ];

        return Attendance::updateOrCreate(
            [
                'class_session_id' => $session->id,
                'student_id' => $record['student_id'],
            ],
            $attendanceData
        );
    }

    /**
     * Update session attendance status
     */
    protected function updateSessionAttendanceStatus(ClassSession $session): void
    {
        $activeStudentIds = $session->courseOffering->activeClassRosterStudentIds();

        $expectedAttendees = $activeStudentIds->count();

        $presentCount = $session->attendances()
            ->whereIn('student_id', $activeStudentIds)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $attendancePercentage = $expectedAttendees > 0
            ? round(($presentCount / $expectedAttendees) * 100, 1)
            : 0;

        $session->update([
            'expected_attendees' => $expectedAttendees,
            'actual_attendees' => $presentCount,
            'attendance_percentage' => $attendancePercentage,
        ]);
    }

    /**
     * Get attendance counts by status
     */
    protected function getAttendanceCounts($students): array
    {
        $counts = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'not_marked' => 0,
        ];

        foreach ($students as $student) {
            $status = $student['attendance']['status'];
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /**
     * Calculate attendance rate (present + late / total)
     */
    protected function calculateAttendanceRate($students): float
    {
        $total = $students->count();
        if ($total === 0) {
            return 0.0;
        }

        $present = $students->filter(function ($student) {
            return in_array($student['attendance']['status'], ['present', 'late']);
        })->count();

        return round(($present / $total) * 100, 1);
    }

    /**
     * Calculate completion rate (marked / total)
     */
    protected function calculateCompletionRate($students): float
    {
        $total = $students->count();
        if ($total === 0) {
            return 0.0;
        }

        $marked = $students->filter(function ($student) {
            return $student['attendance']['status'] !== 'not_marked';
        })->count();

        return round(($marked / $total) * 100, 1);
    }

    /**
     * Calculate session statistics (legacy method for compatibility)
     */
    protected function calculateSessionStatistics($attendanceData): array
    {
        $total = $attendanceData->count();
        $present = $attendanceData->whereIn('status', ['present', 'late'])->count();
        $absent = $attendanceData->where('status', 'absent')->count();
        $excused = $attendanceData->where('status', 'excused')->count();
        $notMarked = $attendanceData->where('status', 'not_marked')->count();

        return [
            'total_students' => $total,
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'not_marked' => $notMarked,
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get course info for analytics
     */
    protected function getCourseInfo(CourseOffering $courseOffering): array
    {
        return [
            'id' => $courseOffering->id,
            'unit_code' => $courseOffering->unit->code,
            'unit_name' => $courseOffering->unit->name,
            'section_code' => $courseOffering->section_code,
            'semester' => $courseOffering->semester->name,
            'enrollment' => $courseOffering->activeClassRosterEnrollmentCount(),
        ];
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
     * Placeholder methods for detailed analytics
     */
    protected function getOverallAttendanceStats(CourseOffering $courseOffering, array $filters): array
    {
        // Implementation would calculate comprehensive attendance statistics
        return [
            'overall_rate' => 85.5,
            'total_sessions' => 12,
            'sessions_with_attendance' => 10,
        ];
    }

    protected function getSessionBreakdown(CourseOffering $courseOffering, array $filters): array
    {
        // Implementation would provide session-by-session breakdown
        return [];
    }

    protected function getStudentAttendanceBreakdown(CourseOffering $courseOffering, array $filters): array
    {
        // Implementation would provide student-by-student breakdown
        return [];
    }

    protected function getAttendanceTrends(CourseOffering $courseOffering, array $filters): array
    {
        // Implementation would calculate attendance trends over time
        return [];
    }

    protected function getCourseAttendanceAlerts(CourseOffering $courseOffering): array
    {
        // Implementation would identify attendance-related alerts
        return [];
    }

    protected function getLowAttendanceStudents(int $lecturerId, float $threshold): array
    {
        // Implementation would identify students with low attendance
        return [];
    }

    protected function getDetailedAttendanceData(CourseOffering $courseOffering): array
    {
        // Implementation would provide detailed data for export
        return [];
    }
}
