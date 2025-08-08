<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'semester' => $this->resource['semester'],
            'teaching_summary' => [
                'total_courses' => $this->resource['teaching_summary']['total_courses'],
                'total_students' => $this->resource['teaching_summary']['total_students'],
                'total_sessions' => $this->resource['teaching_summary']['total_sessions'],
                'completed_sessions' => $this->resource['teaching_summary']['completed_sessions'],
                'pending_sessions' => $this->resource['teaching_summary']['pending_sessions'],
                'average_class_size' => $this->resource['teaching_summary']['average_class_size'],
                'courses' => $this->resource['teaching_summary']['courses'],
            ],
            'attendance_overview' => [
                'total_sessions' => $this->resource['attendance_overview']['total_sessions'],
                'sessions_with_attendance' => $this->resource['attendance_overview']['sessions_with_attendance'],
                'pending_attendance_marking' => $this->resource['attendance_overview']['pending_attendance_marking'],
                'average_attendance_rate' => $this->resource['attendance_overview']['average_attendance_rate'],
                'sessions_requiring_attention' => $this->resource['attendance_overview']['sessions_requiring_attention'],
                'attendance_trends' => $this->resource['attendance_overview']['attendance_trends'],
            ],
            'student_alerts' => [
                'total_alerts' => $this->resource['student_alerts']['total_alerts'],
                'low_attendance_students' => $this->resource['student_alerts']['low_attendance_students'],
                'recently_absent_students' => $this->resource['student_alerts']['recently_absent_students'],
                'critical_alerts' => $this->resource['student_alerts']['critical_alerts'],
            ],
            'upcoming_sessions' => $this->resource['upcoming_sessions'],
            'recent_activities' => $this->resource['recent_activities'],
            'summary_stats' => [
                'courses_count' => $this->resource['teaching_summary']['total_courses'],
                'students_count' => $this->resource['teaching_summary']['total_students'],
                'alerts_count' => $this->resource['student_alerts']['total_alerts'],
                'pending_attendance_count' => $this->resource['attendance_overview']['pending_attendance_marking'],
                'upcoming_sessions_count' => count($this->resource['upcoming_sessions']),
            ],
            'quick_actions' => [
                'mark_attendance_available' => $this->resource['attendance_overview']['pending_attendance_marking'] > 0,
                'alerts_require_attention' => $this->resource['student_alerts']['total_alerts'] > 0,
                'sessions_today' => $this->getSessionsToday(),
                'reports_available' => true,
            ],
        ];
    }

    /**
     * Get sessions scheduled for today
     */
    protected function getSessionsToday(): int
    {
        $today = now()->format('Y-m-d');
        $todaySessions = collect($this->resource['upcoming_sessions'])
            ->filter(fn ($session) => $session['date'] === $today);

        return $todaySessions->count();
    }
}
