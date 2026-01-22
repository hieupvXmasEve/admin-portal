<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'student' => $this->resource['student'],

            'course_enrollments' => collect($this->resource['course_enrollments'])->map(function ($enrollment) {
                return [
                    'course_offering_id' => $enrollment['course_offering_id'],
                    'unit_code' => $enrollment['unit_code'],
                    'unit_name' => $enrollment['unit_name'],
                    'section_code' => $enrollment['section_code'],
                    'semester' => $enrollment['semester'],
                    'registration_date' => $enrollment['registration_date'],
                    'registration_status' => $enrollment['registration_status'],
                    'status_label' => $this->getRegistrationStatusLabel($enrollment['registration_status']),
                    'status_color' => $this->getRegistrationStatusColor($enrollment['registration_status']),
                ];
            }),

            'attendance_summary' => [
                'total_sessions' => $this->resource['attendance_summary']['total_sessions'],
                'present_sessions' => $this->resource['attendance_summary']['present_sessions'],
                'absent_sessions' => $this->resource['attendance_summary']['absent_sessions'],
                'excused_sessions' => $this->resource['attendance_summary']['excused_sessions'],
                'attendance_percentage' => $this->resource['attendance_summary']['attendance_percentage'],
                'last_attendance' => $this->resource['attendance_summary']['last_attendance'],
                'attendance_status' => $this->getAttendanceStatus($this->resource['attendance_summary']['attendance_percentage']),
                'attendance_trend' => $this->getAttendanceTrend(),
            ],

            'performance_indicators' => [
                'attendance_status' => $this->resource['performance_indicators']['attendance_status'],
                'risk_level' => $this->resource['performance_indicators']['risk_level'],
                'engagement_score' => $this->resource['performance_indicators']['engagement_score'],
                'needs_attention' => $this->resource['performance_indicators']['needs_attention'],
                'risk_factors' => $this->getRiskFactors(),
                'strengths' => $this->getStrengths(),
            ],

            'alerts' => collect($this->resource['alerts'])->map(function ($alert) {
                return [
                    'type' => $alert['type'],
                    'priority' => $alert['priority'],
                    'message' => $alert['message'],
                    'created_at' => $alert['created_at'],
                    'priority_label' => $this->getPriorityLabel($alert['priority']),
                    'priority_color' => $this->getPriorityColor($alert['priority']),
                    'type_label' => $this->getAlertTypeLabel($alert['type']),
                ];
            }),

            'notes' => collect($this->resource['notes'])->map(function ($note) {
                return [
                    'id' => $note['id'],
                    'note_type' => $note['note_type'],
                    'title' => $note['title'],
                    'content' => $note['content'],
                    'is_private' => $note['is_private'],
                    'is_alert' => $note['is_alert'],
                    'priority' => $note['priority'],
                    'created_at' => $note['created_at'],
                    'updated_at' => $note['updated_at'],
                    'type_label' => $this->getNoteTypeLabel($note['note_type']),
                    'priority_label' => $this->getPriorityLabel($note['priority']),
                    'priority_color' => $this->getPriorityColor($note['priority']),
                ];
            }),

            'recent_activity' => collect($this->resource['recent_activity'])->map(function ($activity) {
                return [
                    'type' => $activity['type'],
                    'status' => $activity['status'] ?? null,
                    'course' => $activity['course'] ?? null,
                    'date' => $activity['date'],
                    'time' => $activity['time'] ?? null,
                    'description' => $this->getActivityDescription($activity),
                    'status_color' => $this->getActivityStatusColor($activity),
                ];
            }),

            'summary_stats' => [
                'total_courses' => count($this->resource['course_enrollments']),
                'attendance_rate' => $this->resource['attendance_summary']['attendance_percentage'],
                'total_notes' => count($this->resource['notes']),
                'total_alerts' => count($this->resource['alerts']),
                'high_priority_alerts' => collect($this->resource['alerts'])->where('priority', 'high')->count(),
                'days_since_last_attendance' => $this->getDaysSinceLastAttendance(),
            ],

            'recommendations' => $this->getRecommendations(),

            'action_items' => $this->getActionItems(),
        ];
    }

    /**
     * Get registration status label
     */
    protected function getRegistrationStatusLabel(string $status): string
    {
        return match ($status) {
            'enrolled' => 'Enrolled',
            'waitlisted' => 'Waitlisted',
            'dropped' => 'Dropped',
            'withdrawn' => 'Withdrawn',
            'defer' => 'Deferred',
            default => 'Unknown',
        };
    }

    /**
     * Get registration status color
     */
    protected function getRegistrationStatusColor(string $status): string
    {
        return match ($status) {
            'enrolled' => 'green',
            'waitlisted' => 'yellow',
            'dropped' => 'red',
            'withdrawn' => 'gray',
            'defer' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Get attendance status
     */
    protected function getAttendanceStatus(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'excellent';
        }
        if ($percentage >= 75) {
            return 'good';
        }
        if ($percentage >= 60) {
            return 'warning';
        }

        return 'at_risk';
    }

    /**
     * Get attendance trend (placeholder)
     */
    protected function getAttendanceTrend(): string
    {
        // This would calculate trend based on recent attendance data
        return 'stable'; // 'improving', 'declining', 'stable'
    }

    /**
     * Get risk factors
     */
    protected function getRiskFactors(): array
    {
        $factors = [];
        $attendancePercentage = $this->resource['attendance_summary']['attendance_percentage'];

        if ($attendancePercentage < 50) {
            $factors[] = 'Very low attendance rate';
        } elseif ($attendancePercentage < 75) {
            $factors[] = 'Below average attendance rate';
        }

        $daysSinceLastAttendance = $this->getDaysSinceLastAttendance();
        if ($daysSinceLastAttendance > 14) {
            $factors[] = 'No recent attendance';
        } elseif ($daysSinceLastAttendance > 7) {
            $factors[] = 'Missing recent sessions';
        }

        if ($this->resource['attendance_summary']['present_sessions'] === 0) {
            $factors[] = 'Never attended any session';
        }

        return $factors;
    }

    /**
     * Get student strengths
     */
    protected function getStrengths(): array
    {
        $strengths = [];
        $attendancePercentage = $this->resource['attendance_summary']['attendance_percentage'];

        if ($attendancePercentage >= 95) {
            $strengths[] = 'Excellent attendance record';
        } elseif ($attendancePercentage >= 85) {
            $strengths[] = 'Good attendance record';
        }

        if ($this->resource['attendance_summary']['excused_sessions'] > 0) {
            $strengths[] = 'Communicates absences appropriately';
        }

        if (count($this->resource['course_enrollments']) > 1) {
            $strengths[] = 'Enrolled in multiple courses';
        }

        return $strengths;
    }

    /**
     * Get priority label
     */
    protected function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent',
            default => 'Unknown Priority',
        };
    }

    /**
     * Get priority color
     */
    protected function getPriorityColor(string $priority): string
    {
        return match ($priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get alert type label
     */
    protected function getAlertTypeLabel(string $type): string
    {
        return match ($type) {
            'low_attendance' => 'Low Attendance',
            'consecutive_absence' => 'Consecutive Absences',
            'no_recent_attendance' => 'No Recent Attendance',
            'performance_concern' => 'Performance Concern',
            default => 'General Alert',
        };
    }

    /**
     * Get note type label
     */
    protected function getNoteTypeLabel(string $type): string
    {
        return match ($type) {
            'general' => 'General Note',
            'academic' => 'Academic Note',
            'behavioral' => 'Behavioral Note',
            'attendance' => 'Attendance Note',
            'performance' => 'Performance Note',
            'personal' => 'Personal Note',
            'alert' => 'Alert Note',
            default => 'Note',
        };
    }

    /**
     * Get activity description
     */
    protected function getActivityDescription(array $activity): string
    {
        return match ($activity['type']) {
            'attendance' => "Marked {$activity['status']} for {$activity['course']}",
            'note' => 'Note added',
            'alert' => 'Alert generated',
            default => 'Activity recorded',
        };
    }

    /**
     * Get activity status color
     */
    protected function getActivityStatusColor(array $activity): string
    {
        if ($activity['type'] === 'attendance') {
            return match ($activity['status']) {
                'present' => 'green',
                'late' => 'yellow',
                'absent' => 'red',
                'excused' => 'blue',
                default => 'gray',
            };
        }

        return 'blue';
    }

    /**
     * Get days since last attendance
     */
    protected function getDaysSinceLastAttendance(): ?int
    {
        $lastAttendance = $this->resource['attendance_summary']['last_attendance'];

        if (! $lastAttendance) {
            return null;
        }

        return now()->diffInDays(\Carbon\Carbon::parse($lastAttendance));
    }

    /**
     * Get recommendations for student
     */
    protected function getRecommendations(): array
    {
        $recommendations = [];
        $attendancePercentage = $this->resource['attendance_summary']['attendance_percentage'];
        $riskLevel = $this->resource['performance_indicators']['risk_level'];

        if ($riskLevel === 'high') {
            $recommendations[] = [
                'type' => 'urgent_action',
                'message' => 'Schedule immediate one-on-one meeting',
                'priority' => 'high',
            ];
            $recommendations[] = [
                'type' => 'intervention',
                'message' => 'Consider academic intervention or support services',
                'priority' => 'high',
            ];
        } elseif ($riskLevel === 'medium') {
            $recommendations[] = [
                'type' => 'monitoring',
                'message' => 'Increase monitoring and check-ins',
                'priority' => 'medium',
            ];
        }

        if ($attendancePercentage < 75) {
            $recommendations[] = [
                'type' => 'communication',
                'message' => 'Send attendance reminder and discuss attendance policy',
                'priority' => 'medium',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'maintenance',
                'message' => 'Continue current support level',
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }

    /**
     * Get action items for student
     */
    protected function getActionItems(): array
    {
        $actionItems = [];
        $alertCount = count($this->resource['alerts']);
        $noteCount = count($this->resource['notes']);

        if ($alertCount > 0) {
            $actionItems[] = [
                'action' => 'review_alerts',
                'description' => "Review {$alertCount} active alert(s)",
                'priority' => 'high',
                'due_date' => now()->addDays(1)->format('Y-m-d'),
            ];
        }

        if ($this->resource['performance_indicators']['needs_attention']) {
            $actionItems[] = [
                'action' => 'contact_student',
                'description' => 'Contact student to discuss attendance',
                'priority' => 'medium',
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ];
        }

        if ($noteCount === 0) {
            $actionItems[] = [
                'action' => 'add_initial_note',
                'description' => 'Add initial observation note',
                'priority' => 'low',
                'due_date' => now()->addWeek()->format('Y-m-d'),
            ];
        }

        return $actionItems;
    }
}
