<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'course_offering' => $this->resource['course_offering'],
            'enrollment_statistics' => $this->resource['enrollment_statistics'],
            'attendance_statistics' => $this->resource['attendance_statistics'],
            'session_overview' => $this->resource['session_overview'],
            'student_performance' => $this->resource['student_performance'],

            // Additional computed fields
            'health_indicators' => [
                'enrollment_health' => $this->getEnrollmentHealth(),
                'attendance_health' => $this->getAttendanceHealth(),
                'session_health' => $this->getSessionHealth(),
                'overall_health' => $this->getOverallHealth(),
            ],

            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Get enrollment health indicator
     */
    protected function getEnrollmentHealth(): string
    {
        $stats = $this->resource['enrollment_statistics'];
        $utilization = $stats['capacity_utilization'];

        if ($utilization >= 90) {
            return 'excellent';
        }
        if ($utilization >= 70) {
            return 'good';
        }
        if ($utilization >= 50) {
            return 'fair';
        }

        return 'poor';
    }

    /**
     * Get attendance health indicator
     */
    protected function getAttendanceHealth(): string
    {
        $stats = $this->resource['attendance_statistics'];
        $rate = $stats['overall_attendance_rate'];

        if ($rate >= 90) {
            return 'excellent';
        }
        if ($rate >= 80) {
            return 'good';
        }
        if ($rate >= 70) {
            return 'fair';
        }

        return 'poor';
    }

    /**
     * Get session health indicator
     */
    protected function getSessionHealth(): string
    {
        $stats = $this->resource['attendance_statistics'];
        $pendingAttendance = $stats['pending_attendance'];

        if ($pendingAttendance === 0) {
            return 'excellent';
        }
        if ($pendingAttendance <= 2) {
            return 'good';
        }
        if ($pendingAttendance <= 5) {
            return 'fair';
        }

        return 'poor';
    }

    /**
     * Get overall health indicator
     */
    protected function getOverallHealth(): string
    {
        $enrollmentHealth = $this->getEnrollmentHealth();
        $attendanceHealth = $this->getAttendanceHealth();
        $sessionHealth = $this->getSessionHealth();

        $healthScores = [
            'excellent' => 4,
            'good' => 3,
            'fair' => 2,
            'poor' => 1,
        ];

        $averageScore = (
            $healthScores[$enrollmentHealth] +
            $healthScores[$attendanceHealth] +
            $healthScores[$sessionHealth]
        ) / 3;

        if ($averageScore >= 3.5) {
            return 'excellent';
        }
        if ($averageScore >= 2.5) {
            return 'good';
        }
        if ($averageScore >= 1.5) {
            return 'fair';
        }

        return 'poor';
    }

    /**
     * Get recommendations based on course data
     */
    protected function getRecommendations(): array
    {
        $recommendations = [];

        $enrollmentStats = $this->resource['enrollment_statistics'];
        $attendanceStats = $this->resource['attendance_statistics'];

        // Enrollment recommendations
        if ($enrollmentStats['capacity_utilization'] < 50) {
            $recommendations[] = [
                'type' => 'enrollment',
                'priority' => 'medium',
                'message' => 'Consider promoting this course to increase enrollment',
                'action' => 'increase_enrollment',
            ];
        }

        if ($enrollmentStats['capacity_utilization'] > 100) {
            $recommendations[] = [
                'type' => 'enrollment',
                'priority' => 'high',
                'message' => 'Course is overenrolled. Consider increasing capacity or opening another section',
                'action' => 'manage_overenrollment',
            ];
        }

        // Attendance recommendations
        if ($attendanceStats['pending_attendance'] > 0) {
            $recommendations[] = [
                'type' => 'attendance',
                'priority' => 'high',
                'message' => "You have {$attendanceStats['pending_attendance']} sessions with unmarked attendance",
                'action' => 'mark_attendance',
            ];
        }

        if ($attendanceStats['overall_attendance_rate'] < 75) {
            $recommendations[] = [
                'type' => 'attendance',
                'priority' => 'medium',
                'message' => 'Low attendance rate detected. Consider engagement strategies',
                'action' => 'improve_engagement',
            ];
        }

        // Session recommendations
        $sessionOverview = $this->resource['session_overview'];
        if ($sessionOverview['next_session']) {
            $nextSession = $sessionOverview['next_session'];
            $sessionDate = \Carbon\Carbon::parse($nextSession['date']);

            if ($sessionDate->isToday()) {
                $recommendations[] = [
                    'type' => 'session',
                    'priority' => 'info',
                    'message' => "You have a session today: {$nextSession['title']} at {$nextSession['start_time']}",
                    'action' => 'prepare_session',
                ];
            }
        }

        return $recommendations;
    }
}
