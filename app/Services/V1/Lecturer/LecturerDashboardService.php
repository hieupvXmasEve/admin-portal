<?php

declare(strict_types=1);

namespace App\Services\V1\Lecturer;

use App\Models\Lecture;
use App\Models\Semester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LecturerDashboardService
{
    /**
     * Get comprehensive dashboard data for lecturer
     */
    public function getDashboardData(Lecture $lecturer, ?int $semesterId = null): array
    {
        $semester = $semesterId ? Semester::find($semesterId) : Semester::getActiveSemester();
        $cacheKey = "lecturer-dashboard:{$lecturer->id}:{$semester?->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($lecturer, $semester) {
            return [
                'semester' => $semester ? [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                ] : null,
                'teaching_summary' => $this->getTeachingSummary($lecturer, $semester),
                'attendance_overview' => $this->getAttendanceOverview($lecturer, $semester),
                'student_alerts' => $this->getStudentAlerts($lecturer, $semester),
                'upcoming_sessions' => $this->getUpcomingSessions($lecturer, 5),
                'recent_activities' => $this->getRecentActivities($lecturer, 10),
            ];
        });
    }

    /**
     * Get teaching summary statistics
     */
    public function getTeachingSummary(Lecture $lecturer, ?Semester $semester): array
    {
        $query = $lecturer->courseOfferings()->where('is_active', true);
        
        if ($semester) {
            $query->where('semester_id', $semester->id);
        }
        
        $courseOfferings = $query->with(['curriculumUnit', 'courseRegistrations'])->get();
        
        $totalCourses = $courseOfferings->count();
        $totalStudents = $courseOfferings->sum('current_enrollment');
        $totalSessions = $courseOfferings->sum(function ($offering) {
            return $offering->classSessions()->count();
        });
        $completedSessions = $courseOfferings->sum(function ($offering) {
            return $offering->classSessions()->where('status', 'completed')->count();
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
                    'unit_code' => $offering->curriculumUnit->unit_code,
                    'unit_name' => $offering->curriculumUnit->unit_name,
                    'section_code' => $offering->section_code,
                    'enrollment' => $offering->current_enrollment,
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
        $sessionsQuery = $lecturer->classSessions()
            ->with(['courseOffering.curriculumUnit', 'attendances']);
            
        if ($semester) {
            $sessionsQuery->whereHas('courseOffering', function ($query) use ($semester) {
                $query->where('semester_id', $semester->id);
            });
        }
        
        $sessions = $sessionsQuery->where('session_date', '<=', now())
                                 ->orderBy('session_date', 'desc')
                                 ->limit(20)
                                 ->get();
        
        $totalSessions = $sessions->count();
        $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();
        $pendingAttendance = $sessions->where('attendance_marked', false)
                                   ->where('session_date', '<', now()->subHours(2))
                                   ->count();
        
        $averageAttendance = $sessions->where('attendance_marked', true)
                                   ->avg('attendance_percentage') ?? 0;
        
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
        // Get students with low attendance (below 75%)
        $lowAttendanceStudents = $this->getLowAttendanceStudents($lecturer, $semester, 75);
        
        // Get students with no recent attendance
        $absentStudents = $this->getRecentlyAbsentStudents($lecturer, $semester, 7);
        
        return [
            'total_alerts' => count($lowAttendanceStudents) + count($absentStudents),
            'low_attendance_students' => $lowAttendanceStudents,
            'recently_absent_students' => $absentStudents,
            'critical_alerts' => array_merge(
                array_filter($lowAttendanceStudents, fn($s) => $s['attendance_percentage'] < 50),
                $absentStudents
            ),
        ];
    }

    /**
     * Get upcoming sessions for lecturer
     */
    public function getUpcomingSessions(Lecture $lecturer, int $limit = 5): array
    {
        $sessions = $lecturer->classSessions()
            ->with(['courseOffering.curriculumUnit', 'room'])
            ->where('session_date', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
            
        return $sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'title' => $session->session_title,
                'course' => [
                    'unit_code' => $session->courseOffering->curriculumUnit->unit_code,
                    'unit_name' => $session->courseOffering->curriculumUnit->unit_name,
                    'section_code' => $session->courseOffering->section_code,
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
                'expected_attendees' => $session->expected_attendees,
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
            ->with(['courseOffering.curriculumUnit'])
            ->where('session_date', '>=', now()->subDays(7))
            ->where('status', 'completed')
            ->orderBy('session_date', 'desc')
            ->limit($limit)
            ->get();
            
        return $recentSessions->map(function ($session) {
            return [
                'type' => 'session_completed',
                'description' => "Completed session: {$session->session_title}",
                'course' => $session->courseOffering->curriculumUnit->unit_code,
                'date' => $session->session_date->format('Y-m-d'),
                'time' => $session->start_time->format('H:i'),
                'attendance_marked' => $session->attendance_marked,
            ];
        })->toArray();
    }

    /**
     * Get sessions requiring attention (attendance not marked)
     */
    protected function getSessionsRequiringAttention(Lecture $lecturer, ?Semester $semester): array
    {
        $query = $lecturer->classSessions()
            ->with(['courseOffering.curriculumUnit'])
            ->where('attendance_marked', false)
            ->where('session_date', '<', now()->subHours(2))
            ->where('status', 'completed');
            
        if ($semester) {
            $query->whereHas('courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            });
        }
        
        return $query->orderBy('session_date', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($session) {
                        return [
                            'id' => $session->id,
                            'title' => $session->session_title,
                            'course' => $session->courseOffering->curriculumUnit->unit_code,
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
}
