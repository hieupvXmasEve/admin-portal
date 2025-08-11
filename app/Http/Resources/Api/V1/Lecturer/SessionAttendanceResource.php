<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'session' => $this->resource['session'],

            'students' => collect($this->resource['students'])->map(function ($student) {
                return [
                    'student_id' => $student['student_id'],
                    'student_number' => $student['student_number'],
                    'full_name' => $student['full_name'],
                    'email' => $student['email'],
                    'phone' => $student['phone'],
                    'student_info' => $student['student_info'],
                    'attendance' => [
                        'id' => $student['attendance']['id'],
                        'status' => $student['attendance']['status'],
                        'status_label' => $this->getStatusLabel($student['attendance']['status']),
                        'status_color' => $this->getStatusColor($student['attendance']['status']),
                        'check_in_time' => $student['attendance']['check_in_time'],
                        'check_out_time' => $student['attendance']['check_out_time'],
                        'minutes_late' => $student['attendance']['minutes_late'],
                        'minutes_present' => $student['attendance']['minutes_present'],
                        'participation_level' => $student['attendance']['participation_level'],
                        'participation_score' => $student['attendance']['participation_score'],
                        'notes' => $student['attendance']['notes'],
                        'excuse_reason' => $student['attendance']['excuse_reason'],
                        'recording_method' => $student['attendance']['recording_method'],
                        'is_verified' => $student['attendance']['is_verified'],
                        'recorded_by' => $student['attendance']['recorded_by'],
                        'recorded_at' => $student['attendance']['recorded_at'],
                        'can_edit' => true,
                        'can_verify' => ! $student['attendance']['is_verified'],
                    ],
                ];
            }),

            'summary' => [
                'total_enrolled' => $this->resource['summary']['total_enrolled'],
                'attendance_counts' => $this->resource['summary']['attendance_counts'],
                'attendance_rate' => $this->resource['summary']['attendance_rate'],
                'completion_rate' => $this->resource['summary']['completion_rate'],
                'needs_attention' => $this->needsAttention(),
                'quick_stats' => [
                    'present_percentage' => $this->getPercentageFromCounts('present'),
                    'absent_percentage' => $this->getPercentageFromCounts('absent'),
                    'late_percentage' => $this->getPercentageFromCounts('late'),
                    'excused_percentage' => $this->getPercentageFromCounts('excused'),
                    'not_marked_percentage' => $this->getPercentageFromCounts('not_marked'),
                ],
            ],

            'actions' => [
                'can_mark_all_present' => $this->resource['summary']['attendance_counts']['not_marked'] > 0,
                'can_mark_all_absent' => $this->resource['summary']['attendance_counts']['not_marked'] > 0,
                'can_export' => true,
                'can_send_notifications' => $this->resource['summary']['attendance_counts']['absent'] > 0,
                'can_save_draft' => true,
                'can_generate_report' => true,
            ],

            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Get status label for display
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'excused' => 'Excused',
            'not_marked' => 'Not Marked',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for UI
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'present' => 'green',
            'late' => 'yellow',
            'excused' => 'blue',
            'absent' => 'red',
            'not_marked' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get percentage for specific status from counts
     */
    protected function getPercentageFromCounts(string $status): float
    {
        $total = $this->resource['summary']['total_enrolled'];
        $count = $this->resource['summary']['attendance_counts'][$status] ?? 0;

        return $total > 0 ? round(($count / $total) * 100, 1) : 0;
    }

    /**
     * Check if session needs attention
     */
    protected function needsAttention(): bool
    {
        $summary = $this->resource['summary'];

        // Needs attention if:
        // 1. Low attendance rate (< 70%)
        // 2. High absence rate (> 30%)
        // 3. Many students not marked

        return $summary['attendance_rate'] < 70 ||
            $this->getPercentageFromCounts('absent') > 30 ||
            $summary['attendance_counts']['not_marked'] > ($summary['total_enrolled'] * 0.2);
    }

    /**
     * Get recommendations based on attendance data
     */
    protected function getRecommendations(): array
    {
        $recommendations = [];
        $summary = $this->resource['summary'];
        $counts = $summary['attendance_counts'];

        if ($counts['not_marked'] > 0) {
            $recommendations[] = [
                'type' => 'action',
                'priority' => 'high',
                'message' => "Mark attendance for {$counts['not_marked']} remaining students",
                'action' => 'mark_remaining_attendance',
            ];
        }

        if ($summary['attendance_rate'] < 60) {
            $recommendations[] = [
                'type' => 'alert',
                'priority' => 'high',
                'message' => 'Very low attendance rate detected. Consider follow-up actions.',
                'action' => 'investigate_low_attendance',
            ];
        } elseif ($summary['attendance_rate'] < 75) {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => 'medium',
                'message' => 'Below average attendance rate. Monitor closely.',
                'action' => 'monitor_attendance',
            ];
        }

        if ($counts['absent'] > 0) {
            $recommendations[] = [
                'type' => 'action',
                'priority' => 'medium',
                'message' => "Consider sending follow-up to {$counts['absent']} absent students",
                'action' => 'contact_absent_students',
            ];
        }

        if ($summary['attendance_rate'] >= 90) {
            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'low',
                'message' => 'Excellent attendance rate! Keep up the good work.',
                'action' => 'maintain_engagement',
            ];
        }

        return $recommendations;
    }
}
