<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'enrollment_status' => $this->enrollment_status,
            'academic_level' => $this->academic_level,
            'program' => $this->program,
            'year_level' => $this->year_level,

            // Course Enrollments
            'course_enrollments' => $this->whenLoaded('courseRegistrations', function () {
                return $this->courseRegistrations->map(function ($registration) {
                    return [
                        'course_offering_id' => $registration->courseOffering->id,
                        'unit_code' => $registration->courseOffering->curriculumUnit->unit_code,
                        'unit_name' => $registration->courseOffering->curriculumUnit->unit_name,
                        'section_code' => $registration->courseOffering->section_code,
                        'registration_status' => $registration->registration_status,
                        'registration_date' => $registration->created_at->format('Y-m-d'),
                    ];
                });
            }),

            // Attendance Summary
            'attendance_summary' => $this->whenLoaded('attendances', function () {
                $attendances = $this->attendances;
                $totalSessions = $attendances->count();
                $presentSessions = $attendances->whereIn('status', ['present', 'late'])->count();
                $absentSessions = $attendances->where('status', 'absent')->count();
                $excusedSessions = $attendances->where('status', 'excused')->count();

                return [
                    'total_sessions' => $totalSessions,
                    'present_sessions' => $presentSessions,
                    'absent_sessions' => $absentSessions,
                    'excused_sessions' => $excusedSessions,
                    'attendance_percentage' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
                    'last_attendance' => $attendances->sortByDesc('created_at')->first()?->created_at?->format('Y-m-d'),
                ];
            }),

            // Status Indicators
            'status_indicators' => [
                'attendance_status' => $this->getAttendanceStatus(),
                'risk_level' => $this->getRiskLevel(),
                'needs_attention' => $this->needsAttention(),
                'has_recent_activity' => $this->hasRecentActivity(),
                'engagement_level' => $this->getEngagementLevel(),
            ],

            // Quick Stats
            'quick_stats' => [
                'courses_enrolled' => $this->whenLoaded('courseRegistrations', function () {
                    return $this->courseRegistrations->where('registration_status', 'enrolled')->count();
                }),
                'attendance_rate' => $this->getAttendanceRate(),
                'days_since_last_attendance' => $this->getDaysSinceLastAttendance(),
                'consecutive_absences' => $this->getConsecutiveAbsences(),
            ],

            // Actions Available
            'available_actions' => [
                'can_add_note' => true,
                'can_contact' => true,
                'can_view_details' => true,
                'can_mark_for_follow_up' => true,
                'can_send_notification' => true,
            ],

            // Alert Information
            'alert_info' => [
                'has_alerts' => $this->hasAlerts(),
                'alert_count' => $this->getAlertCount(),
                'highest_priority_alert' => $this->getHighestPriorityAlert(),
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get attendance status based on attendance percentage
     */
    protected function getAttendanceStatus(): string
    {
        $rate = $this->getAttendanceRate();

        if ($rate >= 90) {
            return 'excellent';
        }
        if ($rate >= 75) {
            return 'good';
        }
        if ($rate >= 60) {
            return 'warning';
        }

        return 'at_risk';
    }

    /**
     * Get risk level for student
     */
    protected function getRiskLevel(): string
    {
        $attendanceRate = $this->getAttendanceRate();
        $consecutiveAbsences = $this->getConsecutiveAbsences();
        $daysSinceLastAttendance = $this->getDaysSinceLastAttendance();

        // High risk conditions
        if ($attendanceRate < 50 || $consecutiveAbsences >= 3 || $daysSinceLastAttendance > 14) {
            return 'high';
        }

        // Medium risk conditions
        if ($attendanceRate < 75 || $consecutiveAbsences >= 2 || $daysSinceLastAttendance > 7) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if student needs attention
     */
    protected function needsAttention(): bool
    {
        return $this->getRiskLevel() !== 'low';
    }

    /**
     * Check if student has recent activity
     */
    protected function hasRecentActivity(): bool
    {
        return $this->getDaysSinceLastAttendance() <= 7;
    }

    /**
     * Get engagement level
     */
    protected function getEngagementLevel(): string
    {
        $attendanceRate = $this->getAttendanceRate();

        if ($attendanceRate >= 95) {
            return 'high';
        }
        if ($attendanceRate >= 80) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get attendance rate
     */
    protected function getAttendanceRate(): float
    {
        if (! $this->relationLoaded('attendances')) {
            return 0.0;
        }

        $attendances = $this->attendances;
        $totalSessions = $attendances->count();
        $presentSessions = $attendances->whereIn('status', ['present', 'late'])->count();

        return $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0.0;
    }

    /**
     * Get days since last attendance
     */
    protected function getDaysSinceLastAttendance(): ?int
    {
        if (! $this->relationLoaded('attendances')) {
            return null;
        }

        $lastAttendance = $this->attendances
            ->whereIn('status', ['present', 'late'])
            ->sortByDesc('created_at')
            ->first();

        if (! $lastAttendance) {
            return null;
        }

        return now()->diffInDays($lastAttendance->created_at);
    }

    /**
     * Get consecutive absences count
     */
    protected function getConsecutiveAbsences(): int
    {
        if (! $this->relationLoaded('attendances')) {
            return 0;
        }

        $recentAttendances = $this->attendances
            ->sortByDesc('created_at')
            ->take(10);

        $consecutiveAbsences = 0;

        foreach ($recentAttendances as $attendance) {
            if ($attendance->status === 'absent') {
                $consecutiveAbsences++;
            } else {
                break;
            }
        }

        return $consecutiveAbsences;
    }

    /**
     * Check if student has alerts
     */
    protected function hasAlerts(): bool
    {
        // This would typically check against an alerts table or system
        // For now, base it on risk level
        return $this->getRiskLevel() !== 'low';
    }

    /**
     * Get alert count
     */
    protected function getAlertCount(): int
    {
        // This would typically count actual alerts
        // For now, return based on conditions
        $count = 0;

        if ($this->getAttendanceRate() < 75) {
            $count++;
        }
        if ($this->getConsecutiveAbsences() >= 2) {
            $count++;
        }
        if ($this->getDaysSinceLastAttendance() > 7) {
            $count++;
        }

        return $count;
    }

    /**
     * Get highest priority alert
     */
    protected function getHighestPriorityAlert(): ?string
    {
        if ($this->getAttendanceRate() < 50) {
            return 'high';
        }
        if ($this->getConsecutiveAbsences() >= 3) {
            return 'high';
        }
        if ($this->getAttendanceRate() < 75) {
            return 'medium';
        }
        if ($this->getConsecutiveAbsences() >= 2) {
            return 'medium';
        }

        return null;
    }
}
