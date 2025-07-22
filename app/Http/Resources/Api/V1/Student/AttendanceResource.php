<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'semester' => $this->resource['semester'],
            'overall_summary' => $this->formatOverallSummary($this->resource['overall_summary']),
            'attendance_by_course' => $this->formatAttendanceByCourse($this->resource['attendance_by_course']),
            'attendance_trends' => $this->formatAttendanceTrends($this->resource['attendance_trends']),
            'recent_attendance' => $this->formatRecentAttendance($this->resource['recent_attendance']),
            'attendance_alerts' => $this->formatAttendanceAlerts($this->resource['attendance_alerts']),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format overall summary
     */
    protected function formatOverallSummary(array $summary): array
    {
        return [
            'statistics' => [
                'total_sessions' => $summary['total_sessions'],
                'present_sessions' => $summary['present_sessions'],
                'absent_sessions' => $summary['absent_sessions'],
                'late_sessions' => $summary['late_sessions'],
                'excused_sessions' => $summary['excused_sessions'],
            ],
            'rates' => [
                'attendance_rate' => $summary['attendance_rate'],
                'punctuality_rate' => $summary['punctuality_rate'],
                'attendance_status' => $summary['attendance_status'],
                'attendance_status_display' => $this->getAttendanceStatusDisplay($summary['attendance_status']),
            ],
            'performance_indicators' => [
                'meets_requirement' => $summary['attendance_rate'] >= 75,
                'risk_level' => $this->getRiskLevel($summary['attendance_rate']),
                'improvement_needed' => $summary['attendance_rate'] < 80,
            ],
        ];
    }

    /**
     * Format attendance by course
     */
    protected function formatAttendanceByCourse(array $courseAttendance): array
    {
        return collect($courseAttendance)->map(function ($course) {
            return [
                'course_offering_id' => $course['course_offering_id'],
                'course_info' => [
                    'code' => $course['course_code'],
                    'name' => $course['course_name'],
                ],
                'attendance_summary' => [
                    'statistics' => $course['attendance_summary'],
                    'status_indicators' => [
                        'status_color' => $this->getStatusColor($course['attendance_summary']['attendance_status']),
                        'meets_requirement' => $course['attendance_summary']['meets_requirement'],
                        'requires_attention' => $course['attendance_summary']['attendance_rate'] < 75,
                    ],
                ],
                'recent_sessions' => collect($course['recent_sessions'])->map(function ($session) {
                    return [
                        'date' => $session['date'],
                        'status' => $session['status'],
                        'status_display' => $session['status_display'],
                        'status_icon' => $this->getStatusIcon($session['status']),
                        'status_color' => $this->getStatusColor($session['status']),
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    /**
     * Format attendance trends
     */
    protected function formatAttendanceTrends(array $trends): array
    {
        if (empty($trends['weekly_trends'])) {
            return [
                'has_data' => false,
                'message' => 'Insufficient data for trend analysis',
            ];
        }

        return [
            'has_data' => true,
            'weekly_trends' => collect($trends['weekly_trends'])->map(function ($week) {
                return [
                    'week' => $week['week'],
                    'total_sessions' => $week['total_sessions'],
                    'present_sessions' => $week['present_sessions'],
                    'attendance_rate' => $week['attendance_rate'],
                    'performance_level' => $this->getPerformanceLevel($week['attendance_rate']),
                ];
            })->toArray(),
            'trend_analysis' => [
                'overall_trend' => $trends['trend_analysis'],
                'trend_display' => $this->getTrendDisplay($trends['trend_analysis']),
                'trend_icon' => $this->getTrendIcon($trends['trend_analysis']),
            ],
        ];
    }

    /**
     * Format recent attendance
     */
    protected function formatRecentAttendance(array $recentAttendance): array
    {
        return collect($recentAttendance)->map(function ($attendance) {
            return [
                'id' => $attendance['id'],
                'course_info' => [
                    'code' => $attendance['course_code'],
                    'name' => $attendance['course_name'],
                ],
                'session_info' => [
                    'date' => $attendance['session_date'],
                    'date_display' => $this->formatDateDisplay($attendance['session_date']),
                    'time' => $attendance['session_time'],
                ],
                'attendance_info' => [
                    'status' => $attendance['status'],
                    'status_display' => $attendance['status_display'],
                    'status_icon' => $this->getStatusIcon($attendance['status']),
                    'status_color' => $this->getStatusColor($attendance['status']),
                    'marked_at' => $attendance['marked_at'],
                    'notes' => $attendance['notes'],
                ],
            ];
        })->toArray();
    }

    /**
     * Format attendance alerts
     */
    protected function formatAttendanceAlerts(array $alerts): array
    {
        return collect($alerts)->map(function ($alert) {
            return [
                'type' => $alert['type'],
                'severity' => $alert['severity'],
                'severity_display' => $this->getSeverityDisplay($alert['severity']),
                'severity_color' => $this->getSeverityColor($alert['severity']),
                'message' => $alert['message'],
                'action_required' => $alert['action_required'],
                'course_info' => isset($alert['course_code']) ? [
                    'code' => $alert['course_code'],
                    'name' => $alert['course_name'] ?? null,
                    'attendance_rate' => $alert['attendance_rate'] ?? null,
                ] : null,
                'recommendations' => $this->getAlertRecommendations($alert),
            ];
        })->toArray();
    }

    /**
     * Get attendance status display
     */
    protected function getAttendanceStatusDisplay(string $status): string
    {
        return match ($status) {
            'excellent' => 'Excellent Attendance',
            'good' => 'Good Attendance',
            'satisfactory' => 'Satisfactory Attendance',
            'warning' => 'Attendance Warning',
            'critical' => 'Critical Attendance',
            'no_data' => 'No Data Available',
            default => ucfirst($status),
        };
    }

    /**
     * Get risk level based on attendance rate
     */
    protected function getRiskLevel(float $rate): string
    {
        return match (true) {
            $rate >= 90 => 'low',
            $rate >= 75 => 'medium',
            $rate >= 60 => 'high',
            default => 'critical',
        };
    }

    /**
     * Get status color
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'present', 'excellent' => '#22c55e', // Green
            'late', 'good' => '#f59e0b',        // Amber
            'excused', 'satisfactory' => '#3b82f6', // Blue
            'absent', 'warning' => '#f97316',   // Orange
            'critical' => '#ef4444',           // Red
            default => '#6b7280',              // Gray
        };
    }

    /**
     * Get status icon
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'present' => 'check-circle',
            'absent' => 'x-circle',
            'late' => 'clock',
            'excused' => 'shield-check',
            default => 'question-mark-circle',
        };
    }

    /**
     * Get performance level
     */
    protected function getPerformanceLevel(float $rate): string
    {
        return match (true) {
            $rate >= 95 => 'excellent',
            $rate >= 85 => 'good',
            $rate >= 75 => 'satisfactory',
            $rate >= 60 => 'needs_improvement',
            default => 'poor',
        };
    }

    /**
     * Get trend display
     */
    protected function getTrendDisplay(string $trend): string
    {
        return match ($trend) {
            'improving' => 'Improving Trend',
            'declining' => 'Declining Trend',
            'stable' => 'Stable Trend',
            'insufficient_data' => 'Insufficient Data',
            default => ucfirst($trend),
        };
    }

    /**
     * Get trend icon
     */
    protected function getTrendIcon(string $trend): string
    {
        return match ($trend) {
            'improving' => 'trending-up',
            'declining' => 'trending-down',
            'stable' => 'minus',
            default => 'question-mark-circle',
        };
    }

    /**
     * Format date display
     */
    protected function formatDateDisplay(string $date): string
    {
        return \Carbon\Carbon::parse($date)->format('M j, Y');
    }

    /**
     * Get severity display
     */
    protected function getSeverityDisplay(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critical',
            'warning' => 'Warning',
            'info' => 'Information',
            default => ucfirst($severity),
        };
    }

    /**
     * Get severity color
     */
    protected function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => '#ef4444', // Red
            'warning' => '#f59e0b',  // Amber
            'info' => '#3b82f6',     // Blue
            default => '#6b7280',    // Gray
        };
    }

    /**
     * Get alert recommendations
     */
    protected function getAlertRecommendations(array $alert): array
    {
        $recommendations = [];

        switch ($alert['type']) {
            case 'low_attendance':
                $recommendations[] = 'Review your schedule and prioritize class attendance';
                $recommendations[] = 'Contact your lecturer to discuss missed sessions';
                if ($alert['severity'] === 'critical') {
                    $recommendations[] = 'Meet with your academic advisor immediately';
                    $recommendations[] = 'Consider academic support services';
                }
                break;

            case 'consecutive_absences':
                $recommendations[] = 'Identify and address barriers to attendance';
                $recommendations[] = 'Communicate with lecturers about your situation';
                $recommendations[] = 'Seek support from student services if needed';
                break;

            default:
                $recommendations[] = 'Monitor your attendance regularly';
                $recommendations[] = 'Maintain consistent class participation';
        }

        return $recommendations;
    }
}
