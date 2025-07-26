<?php

declare(strict_types=1);

namespace App\Services\V1\Lecturer;

use App\Models\Lecture;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class LecturerCourseService
{
    /**
     * Get lecturer's course offerings with filtering and pagination
     */
    public function getCourseOfferings(
        Lecture $lecturer,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $lecturer->courseOfferings()
            ->with([
                'curriculumUnit',
                'semester',
                'courseRegistrations' => function ($q) {
                    $q->where('registration_status', 'enrolled');
                },
                'classSessions' => function ($q) {
                    $q->orderBy('session_date', 'desc')->limit(5);
                }
            ])
            ->where('is_active', true);

        // Apply filters
        $this->applyCourseFilters($query, $filters);

        return $query->orderBy('semester_id', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get detailed course offering information
     */
    public function getCourseOfferingDetails(Lecture $lecturer, int $courseOfferingId): ?array
    {
        $cacheKey = "lecturer-course-details:{$lecturer->id}:{$courseOfferingId}";

        return Cache::remember($cacheKey, 600, function () use ($lecturer, $courseOfferingId) {
            $courseOffering = $lecturer->courseOfferings()
                ->with([
                    'curriculumUnit',
                    'semester',
                    'courseRegistrations.student',
                    'classSessions' => function ($q) {
                        $q->orderBy('session_date', 'asc');
                    }
                ])
                ->where('id', $courseOfferingId)
                ->where('is_active', true)
                ->first();

            if (!$courseOffering) {
                return null;
            }

            return [
                'course_offering' => $this->formatCourseOffering($courseOffering),
                'enrollment_statistics' => $this->getEnrollmentStatistics($courseOffering),
                'attendance_statistics' => $this->getAttendanceStatistics($courseOffering),
                'session_overview' => $this->getSessionOverview($courseOffering),
                'student_performance' => $this->getStudentPerformanceOverview($courseOffering),
            ];
        });
    }

    /**
     * Get unit information by course offering id
     */
    public function getUnitByCourseOfferingId(Lecture $lecturer, int $courseOfferingId): ?Unit
    {
        $courseOffering = $lecturer->courseOfferings()
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (!$courseOffering) {
            return null;
        }

        return $courseOffering->curriculumUnit->unit;
    }

    /**
     * Get course statistics for lecturer
     */
    public function getCourseStatistics(Lecture $lecturer, int $courseOfferingId): array
    {
        $courseOffering = $lecturer->courseOfferings()
            ->with(['courseRegistrations', 'classSessions.attendances'])
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (!$courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        return [
            'enrollment_stats' => $this->getDetailedEnrollmentStats($courseOffering),
            'attendance_stats' => $this->getDetailedAttendanceStats($courseOffering),
            'session_stats' => $this->getDetailedSessionStats($courseOffering),
            'performance_trends' => $this->getPerformanceTrends($courseOffering),
            'weekly_breakdown' => $this->getWeeklyBreakdown($courseOffering),
        ];
    }

    /**
     * Get enrolled students for a course
     */
    public function getCourseStudents(
        Lecture $lecturer,
        int $courseOfferingId,
        array $filters = []
    ): array {
        $courseOffering = $lecturer->courseOfferings()
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (!$courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        $studentsQuery = $courseOffering->courseRegistrations()
            ->with([
                'student',
                'student.attendances' => function ($q) use ($courseOfferingId) {
                    $q->whereHas('classSession', function ($sessionQuery) use ($courseOfferingId) {
                        $sessionQuery->where('course_offering_id', $courseOfferingId);
                    });
                }
            ])
            ->where('registration_status', 'enrolled');

        // Apply student filters
        $this->applyStudentFilters($studentsQuery, $filters);

        $students = $studentsQuery->get();

        return $students->map(function ($registration) use ($courseOfferingId) {
            $student = $registration->student;
            $attendanceStats = $this->calculateStudentAttendanceStats($student, $courseOfferingId);

            return [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'registration_date' => $registration->created_at->format('Y-m-d'),
                'attendance_percentage' => $attendanceStats['percentage'],
                'sessions_attended' => $attendanceStats['attended'],
                'total_sessions' => $attendanceStats['total'],
                'last_attendance' => $attendanceStats['last_attendance'],
                'status' => $this->getStudentStatus($attendanceStats),
            ];
        })->toArray();
    }

    /**
     * Get filter options for courses
     */
    public function getFilterOptions(Lecture $lecturer): array
    {
        $semesters = $lecturer->courseOfferings()
            ->with('semester')
            ->get()
            ->pluck('semester')
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();

        $deliveryModes = $lecturer->courseOfferings()
            ->distinct()
            ->pluck('delivery_mode')
            ->filter()
            ->sort()
            ->values();

        return [
            'semesters' => $semesters->map(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                ];
            }),
            'delivery_modes' => $deliveryModes->map(function ($mode) {
                return [
                    'value' => $mode,
                    'label' => ucfirst(str_replace('_', ' ', $mode)),
                ];
            }),
            'enrollment_statuses' => [
                ['value' => 'open', 'label' => 'Open for Enrollment'],
                ['value' => 'closed', 'label' => 'Enrollment Closed'],
                ['value' => 'full', 'label' => 'Full Capacity'],
            ],
        ];
    }

    /**
     * Apply course filters to query
     */
    protected function applyCourseFilters($query, array $filters): void
    {
        if (!empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (!empty($filters['delivery_mode'])) {
            $query->where('delivery_mode', $filters['delivery_mode']);
        }

        if (!empty($filters['enrollment_status'])) {
            switch ($filters['enrollment_status']) {
                case 'open':
                    $query->where('enrollment_status', 'open')
                        ->whereColumn('current_enrollment', '<', 'max_capacity');
                    break;
                case 'closed':
                    $query->where('enrollment_status', 'closed');
                    break;
                case 'full':
                    $query->whereColumn('current_enrollment', '>=', 'max_capacity');
                    break;
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('curriculumUnit', function ($q) use ($search) {
                $q->where('unit_code', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Apply student filters to query
     */
    protected function applyStudentFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['attendance_status'])) {
            // This would require a complex subquery to filter by attendance percentage
            // Implementation depends on specific requirements
        }
    }

    /**
     * Format course offering data
     */
    protected function formatCourseOffering(CourseOffering $courseOffering): array
    {
        return [
            'id' => $courseOffering->id,
            'section_code' => $courseOffering->section_code,
            'delivery_mode' => $courseOffering->delivery_mode,
            'location' => $courseOffering->location,
            'max_capacity' => $courseOffering->max_capacity,
            'current_enrollment' => $courseOffering->current_enrollment,
            'enrollment_status' => $courseOffering->enrollment_status,
            'schedule_days' => $courseOffering->schedule_days,
            'schedule_time_start' => $courseOffering->schedule_time_start?->format('H:i'),
            'schedule_time_end' => $courseOffering->schedule_time_end?->format('H:i'),
            'curriculum_unit' => [
                'id' => $courseOffering->curriculumUnit->id,
                'unit_code' => $courseOffering->curriculumUnit->unit_code,
                'unit_name' => $courseOffering->curriculumUnit->unit_name,
                'credit_hours' => $courseOffering->curriculumUnit->credit_hours,
                'description' => $courseOffering->curriculumUnit->description,
            ],
            'semester' => [
                'id' => $courseOffering->semester->id,
                'name' => $courseOffering->semester->name,
                'code' => $courseOffering->semester->code,
                'start_date' => $courseOffering->semester->start_date?->format('Y-m-d'),
                'end_date' => $courseOffering->semester->end_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get enrollment statistics
     */
    protected function getEnrollmentStatistics(CourseOffering $courseOffering): array
    {
        $totalRegistrations = $courseOffering->courseRegistrations()->count();
        $enrolledStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'enrolled')->count();
        $waitlistedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'waitlisted')->count();
        $droppedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'dropped')->count();

        return [
            'total_registrations' => $totalRegistrations,
            'enrolled_students' => $enrolledStudents,
            'waitlisted_students' => $waitlistedStudents,
            'dropped_students' => $droppedStudents,
            'capacity_utilization' => $courseOffering->max_capacity > 0
                ? round(($enrolledStudents / $courseOffering->max_capacity) * 100, 1)
                : 0,
            'available_spots' => max(0, $courseOffering->max_capacity - $enrolledStudents),
        ];
    }

    /**
     * Get attendance statistics for course
     */
    protected function getAttendanceStatistics(CourseOffering $courseOffering): array
    {
        $sessions = $courseOffering->classSessions;
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();

        $attendanceRecords = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->where('attendances.status', 'present')
            ->count();

        $totalPossibleAttendances = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->count();

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'sessions_with_attendance' => $sessionsWithAttendance,
            'pending_attendance' => $completedSessions - $sessionsWithAttendance,
            'overall_attendance_rate' => $totalPossibleAttendances > 0
                ? round(($attendanceRecords / $totalPossibleAttendances) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get session overview
     */
    protected function getSessionOverview(CourseOffering $courseOffering): array
    {
        $sessions = $courseOffering->classSessions;

        return [
            'total_sessions' => $sessions->count(),
            'upcoming_sessions' => $sessions->where('session_date', '>=', now())->count(),
            'completed_sessions' => $sessions->where('status', 'completed')->count(),
            'cancelled_sessions' => $sessions->where('status', 'cancelled')->count(),
            'next_session' => $this->getNextSession($sessions),
        ];
    }

    /**
     * Get next session details
     */
    protected function getNextSession($sessions): ?array
    {
        $nextSession = $sessions->where('session_date', '>=', now())
            ->sortBy('session_date')
            ->first();

        if (!$nextSession) {
            return null;
        }

        return [
            'id' => $nextSession->id,
            'title' => $nextSession->session_title,
            'date' => $nextSession->session_date->format('Y-m-d'),
            'start_time' => $nextSession->start_time->format('H:i'),
            'end_time' => $nextSession->end_time->format('H:i'),
            'delivery_mode' => $nextSession->delivery_mode,
        ];
    }

    /**
     * Get student performance overview (placeholder)
     */
    protected function getStudentPerformanceOverview(CourseOffering $courseOffering): array
    {
        // This would integrate with grading system
        return [
            'average_grade' => null,
            'grade_distribution' => [],
            'at_risk_students' => 0,
        ];
    }

    /**
     * Calculate student attendance statistics
     */
    protected function calculateStudentAttendanceStats($student, int $courseOfferingId): array
    {
        $attendances = $student->attendances()
            ->whereHas('classSession', function ($q) use ($courseOfferingId) {
                $q->where('course_offering_id', $courseOfferingId);
            })
            ->get();

        $totalSessions = DB::table('class_sessions')
            ->where('course_offering_id', $courseOfferingId)
            ->where('attendance_marked', true)
            ->count();

        $attendedSessions = $attendances->whereIn('status', ['present', 'late'])->count();
        $percentage = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;

        $lastAttendance = $attendances->sortByDesc('created_at')->first();

        return [
            'total' => $totalSessions,
            'attended' => $attendedSessions,
            'percentage' => $percentage,
            'last_attendance' => $lastAttendance?->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Get student status based on attendance
     */
    protected function getStudentStatus(array $attendanceStats): string
    {
        $percentage = $attendanceStats['percentage'];

        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 75) return 'good';
        if ($percentage >= 60) return 'warning';
        return 'at_risk';
    }

    /**
     * Placeholder methods for detailed statistics
     */
    protected function getDetailedEnrollmentStats(CourseOffering $courseOffering): array
    {
        return $this->getEnrollmentStatistics($courseOffering);
    }

    protected function getDetailedAttendanceStats(CourseOffering $courseOffering): array
    {
        return $this->getAttendanceStatistics($courseOffering);
    }

    protected function getDetailedSessionStats(CourseOffering $courseOffering): array
    {
        return $this->getSessionOverview($courseOffering);
    }

    protected function getPerformanceTrends(CourseOffering $courseOffering): array
    {
        return ['trend' => 'stable', 'change' => 0];
    }

    protected function getWeeklyBreakdown(CourseOffering $courseOffering): array
    {
        return [];
    }
}
