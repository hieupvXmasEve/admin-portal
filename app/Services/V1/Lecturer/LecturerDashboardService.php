<?php

declare(strict_types=1);

namespace App\Services\V1\Lecturer;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LecturerDashboardService
{
    /**
     * Get comprehensive dashboard data for lecturer
     */
    public function getDashboardData(Lecture $lecturer, ?int $semesterId = null): array
    {
        $semester = $semesterId ? Semester::find($semesterId) : $this->currentSemester();
        $cacheKey = "lecturer-dashboard:{$lecturer->id}:{$semester?->id}";

        // If no semester is found, return empty data structure
        if (! $semester) {
            Log::info('No semester found for lecturer dashboard', [
                'lecturer_id' => $lecturer->id,
                'requested_semester_id' => $semesterId,
            ]);

            return [
                'semester' => null,
                'teaching_summary' => $this->getEmptyTeachingSummary(),
                'attendance_overview' => $this->getEmptyAttendanceOverview(),
                'student_alerts' => $this->getEmptyStudentAlerts(),
                'upcoming_sessions' => [],
                'recent_activities' => [],
            ];
        }

        //        return Cache::remember($cacheKey, 0, function () use ($lecturer, $semester) {
        return [
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
                'code' => $semester->code,
                'start_date' => $semester->start_date?->format('Y-m-d'),
                'end_date' => $semester->end_date?->format('Y-m-d'),
            ],
            'teaching_summary' => $this->getTeachingSummary($lecturer, $semester),
            'attendance_overview' => $this->getAttendanceOverview($lecturer, $semester),
            'student_alerts' => $this->getStudentAlerts($lecturer, $semester),
            'upcoming_sessions' => $this->getUpcomingSessions($lecturer, 5),
            'recent_activities' => $this->getRecentActivities($lecturer, 10),
        ];
        //        });
    }

    private function currentSemester(): ?Semester
    {
        $currentPeriodId = app(AcademicPeriodReader::class)->current()?->id;

        return $currentPeriodId === null ? null : Semester::find($currentPeriodId);
    }

    /**
     * Get teaching summary statistics
     */
    public function getTeachingSummary(Lecture $lecturer, ?Semester $semester): array
    {
        // If no semester provided, return empty structure
        if (! $semester) {
            Log::info('No semester provided for teaching summary', [
                'lecturer_id' => $lecturer->id,
            ]);

            return $this->getEmptyTeachingSummary();
        }

        // Use class session based logic instead of direct course offering relationship
        $query = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->where('campus_id', $lecturer->campus_id)
            ->where('is_active', true)
            ->where('semester_id', $semester->id);

        $courseOfferings = $query->with([
            'unit',
            'classRosterRegistrations',
            'classSessions' => function ($q) use ($lecturer) {
                $q->where('lecture_id', $lecturer->id);
            },
        ])->get();
        Log::info('$courseOfferings', [
            'courseOfferings' => $courseOfferings,
        ]);
        $totalCourses = $courseOfferings->count();
        $totalStudents = $courseOfferings->sum(fn ($offering) => $offering->activeClassRosterEnrollmentCount());
        // Use eager-loaded relationships instead of additional queries
        $totalSessions = $courseOfferings->sum(function ($offering) {
            return $offering->classSessions->count(); // Already filtered by lecturer in with()
        });
        $completedSessions = $courseOfferings->sum(function ($offering) {
            return $offering->classSessions->where('status', 'completed')->count();
        });

        return [
            'total_courses' => $totalCourses,
            'total_students' => $totalStudents,
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'pending_sessions' => $totalSessions - $completedSessions,
            'average_class_size' => $totalCourses > 0 ? round($totalStudents / $totalCourses, 1) : 0,
            'courses' => $courseOfferings->map(function ($offering) {
                return [
                    'id' => $offering->id,
                    'unit_code' => $offering?->unit?->code,
                    'unit_name' => $offering?->unit?->name,
                    'section_code' => $offering->section_code,
                    'enrollment' => $offering->activeClassRosterEnrollmentCount(),
                    'capacity' => $offering->max_capacity,
                    'delivery_mode' => $offering->delivery_mode,
                ];
            }),
        ];
    }

    /**
     * Get attendance overview with alerts
     */
    public function getAttendanceOverview(Lecture $lecturer, ?Semester $semester): array
    {
        // If no semester provided, return empty structure
        if (! $semester) {
            Log::info('No semester provided for attendance overview', [
                'lecturer_id' => $lecturer->id,
            ]);

            return $this->getEmptyAttendanceOverview();
        }

        $sessionsQuery = $lecturer->classSessions()
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'attendances'])
            ->whereHas('courseOffering', function ($query) use ($semester) {
                $query->where('semester_id', $semester->id);
            });

        $sessions = $sessionsQuery->where('session_date', '<=', now())
            ->orderBy('session_date', 'desc')
            ->limit(20)
            ->get();

        $totalSessions = $sessions->count();
        Log::info('getAttendanceOverview ', [
            '$totalSessions' => $totalSessions,
        ]);
        $sessionsWithAttendance = $sessions
            ->filter(fn ($session) => $this->sessionHasActiveRosterAttendance($session))
            ->count();
        $pendingAttendance = $sessions->filter(function ($session) {
            Log::info('$sessions', [
                'start_time' => $session->start_time,
                'session date' => $session->session_date,
            ]);
            // Combine session date with start time for proper comparison
            $sessionDateTime = Carbon::parse($session->session_date)
                ->setTimeFromTimeString($session->start_time->format('H:i:s'));

            return ! $this->sessionHasActiveRosterAttendance($session) && $sessionDateTime->lt(now()->subMinute(10));
        })->count();

        $averageAttendance = $sessions
            ->filter(fn ($session) => $this->sessionHasActiveRosterAttendance($session))
            ->avg(fn ($session) => $this->activeRosterAttendancePercentage($session)) ?? 0;

        return [
            'total_sessions' => $totalSessions,
            'sessions_with_attendance' => $sessionsWithAttendance,
            'pending_attendance_marking' => $pendingAttendance,
            'average_attendance_rate' => round($averageAttendance, 1),
            'sessions_requiring_attention' => $this->getSessionsRequiringAttention($lecturer, $semester),
            'attendance_trends' => $this->getAttendanceTrends($lecturer, $semester),
        ];
    }

    /**
     * Get student alerts for lecturer's courses
     */
    public function getStudentAlerts(Lecture $lecturer, ?Semester $semester): array
    {
        // If no semester provided, return empty structure
        if (! $semester) {
            Log::info('No semester provided for student alerts', [
                'lecturer_id' => $lecturer->id,
            ]);

            return $this->getEmptyStudentAlerts();
        }

        // Get students with low attendance (below 75%)
        $lowAttendanceStudents = $this->getLowAttendanceStudents($lecturer, $semester, 75);

        // Get students with no recent attendance
        $absentStudents = $this->getRecentlyAbsentStudents($lecturer, $semester, 7);

        return [
            'total_alerts' => count($lowAttendanceStudents) + count($absentStudents),
            'low_attendance_students' => $lowAttendanceStudents,
            'recently_absent_students' => $absentStudents,
            'critical_alerts' => array_merge(
                array_filter($lowAttendanceStudents, fn ($s) => $s['attendance_percentage'] < 50),
                $absentStudents
            ),
        ];
    }

    /**
     * Get upcoming sessions for lecturer (including all of today's sessions)
     */
    public function getUpcomingSessions(Lecture $lecturer, int $limit = 5): array
    {
        $now = now();
        $today = $now->toDateString();

        $sessions = $lecturer->classSessions()
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'room'])
            ->where(function ($query) use ($today) {
                $query->where('session_date', '>', $today) // Future dates
                    ->orWhere('session_date', '=', $today); // All of today's sessions
            })
            ->whereIn('status', ['scheduled', 'in_progress', 'completed'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();

        return $sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'title' => $session->session_title,
                'course' => [
                    'unit_code' => $session->courseOffering?->unit?->code,
                    'unit_name' => $session->courseOffering?->unit?->name,
                    'section_code' => $session->courseOffering?->section_code,
                ],
                'date' => $session->session_date->format('Y-m-d'),
                'start_time' => $session->start_time->format('H:i'),
                'end_time' => $session->end_time->format('H:i'),
                'delivery_mode' => $session->delivery_mode,
                'room' => $session->room ? [
                    'name' => $session->room->name,
                    'building' => $session->room->building,
                    'capacity' => $session->room->capacity,
                ] : null,
                'expected_attendees' => $session->courseOffering?->activeClassRosterEnrollmentCount()
                    ?? $session->expected_attendees,
                'status' => $session->status,
            ];
        })->toArray();
    }

    /**
     * Get recent teaching activities
     */
    public function getRecentActivities(Lecture $lecturer, int $limit = 10): array
    {
        // This would typically come from an activity log table
        // For now, we'll use recent sessions and attendance marking
        $recentSessions = $lecturer->classSessions()
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'attendances'])
            ->where('session_date', '>=', now()->subDays(7))
            ->where('status', 'completed')
            ->orderBy('session_date', 'desc')
            ->limit($limit)
            ->get();

        return $recentSessions->map(function ($session) {
            return [
                'type' => 'session_completed',
                'description' => "Completed session: {$session->session_title}",
                'course' => $session->courseOffering?->unit?->code,
                'date' => $session->session_date->format('Y-m-d'),
                'time' => $session->start_time->format('H:i'),
                'attendance_marked' => $this->sessionHasActiveRosterAttendance($session),
            ];
        })->toArray();
    }

    /**
     * Get sessions requiring attention (attendance not marked)
     */
    protected function getSessionsRequiringAttention(Lecture $lecturer, ?Semester $semester): array
    {
        $query = $lecturer->classSessions()
            ->with(['courseOffering.unit', 'courseOffering.classRosterRegistrations', 'attendances'])
            ->where('session_date', '<', now()->subHours(2))
            ->where('status', 'completed');

        if ($semester) {
            $query->whereHas('courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            });
        }

        return $query->orderBy('session_date', 'desc')
            ->limit(50)
            ->get()
            ->filter(fn ($session) => ! $this->sessionHasActiveRosterAttendance($session))
            ->take(10)
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'title' => $session->session_title,
                    'course' => $session->courseOffering?->unit?->code,
                    'date' => $session->session_date->format('Y-m-d'),
                    'time' => $session->start_time->format('H:i'),
                    'days_overdue' => now()->diffInDays($session->session_date),
                ];
            })->toArray();
    }

    /**
     * Get attendance trends for lecturer's courses
     */
    protected function getAttendanceTrends(Lecture $lecturer, ?Semester $semester): array
    {
        // Implementation would calculate weekly attendance trends
        // This is a simplified version
        return [
            'weekly_average' => 85.5,
            'trend' => 'stable', // 'increasing', 'decreasing', 'stable'
            'last_week_change' => 2.3,
        ];
    }

    /**
     * Get students with low attendance
     */
    protected function getLowAttendanceStudents(Lecture $lecturer, ?Semester $semester, float $threshold): array
    {
        // This would be a complex query joining students, course registrations, sessions, and attendance
        // Simplified implementation
        return [];
    }

    /**
     * Get students who have been absent recently
     */
    protected function getRecentlyAbsentStudents(Lecture $lecturer, ?Semester $semester, int $days): array
    {
        // Implementation would find students with consecutive absences
        return [];
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

    protected function activeRosterAttendancePercentage(ClassSession $session): float
    {
        if (! $session->relationLoaded('attendances') || ! $session->courseOffering) {
            return (float) ($session->attendance_percentage ?? 0);
        }

        $expectedAttendees = $session->courseOffering->activeClassRosterEnrollmentCount();
        if ($expectedAttendees === 0) {
            return 0.0;
        }

        $actualAttendees = $session->attendances
            ->whereIn('student_id', $session->courseOffering->activeClassRosterStudentIds())
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($actualAttendees / $expectedAttendees) * 100, 1);
    }

    /**
     * Get empty teaching summary structure
     */
    private function getEmptyTeachingSummary(): array
    {
        return [
            'total_courses' => 0,
            'total_students' => 0,
            'total_sessions' => 0,
            'completed_sessions' => 0,
            'pending_sessions' => 0,
            'average_class_size' => 0,
            'courses' => [],
        ];
    }

    /**
     * Get empty attendance overview structure
     */
    private function getEmptyAttendanceOverview(): array
    {
        return [
            'total_sessions' => 0,
            'sessions_with_attendance' => 0,
            'pending_attendance_marking' => 0,
            'average_attendance_rate' => 0,
            'sessions_requiring_attention' => [],
            'attendance_trends' => [
                'weekly_average' => 0,
                'trend' => 'stable',
                'last_week_change' => 0,
            ],
        ];
    }

    /**
     * Get empty student alerts structure
     */
    private function getEmptyStudentAlerts(): array
    {
        return [
            'total_alerts' => 0,
            'low_attendance_students' => [],
            'recently_absent_students' => [],
            'critical_alerts' => [],
        ];
    }
}
