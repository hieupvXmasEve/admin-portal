<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $expectedAttendees = $this->activeRosterEnrollmentCount();
        $actualAttendees = $this->actualRosterAttendees();
        $attendanceMarked = $this->activeRosterAttendanceMarked();
        $attendancePercentage = $expectedAttendees > 0 && $actualAttendees !== null
            ? round(($actualAttendees / $expectedAttendees) * 100, 1)
            : $this->attendance_percentage;

        return [
            'id' => $this->id,
            'title' => $this->session_title,
            'description' => $this->session_description,
            'date' => $this->session_date->format('Y-m-d'),
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'session_type' => $this->session_type,
            'delivery_mode' => $this->delivery_mode,
            'status' => $this->status,

            // Attendance Information
            'attendance_marked' => $attendanceMarked,
            'attendance_percentage' => $attendancePercentage,
            'expected_attendees' => $expectedAttendees,
            'actual_attendees' => $actualAttendees,

            // Course Information
            'course' => $this->whenLoaded('courseOffering', function () {
                return [
                    'id' => $this->courseOffering->id,
                    'unit_code' => $this->courseOffering->unit->code,
                    'unit_name' => $this->courseOffering->unit->name,
                    'section_code' => $this->courseOffering->section_code,
                    'enrollment' => $expectedAttendees,
                    'semester' => $this->when($this->courseOffering->relationLoaded('semester'), [
                        'id' => $this->courseOffering->semester->id,
                        'name' => $this->courseOffering->semester->name,
                        'code' => $this->courseOffering->semester->code,
                    ]),
                ];
            }),

            // Attendance Statistics
            'attendance_stats' => $this->whenLoaded('attendances', function () {
                $attendances = $this->activeRosterAttendances();
                $total = $attendances->count();
                $present = $attendances->whereIn('status', ['present', 'late'])->count();
                $absent = $attendances->where('status', 'absent')->count();
                $excused = $attendances->where('status', 'excused')->count();

                return [
                    'total_marked' => $total,
                    'present' => $present,
                    'absent' => $absent,
                    'excused' => $excused,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
                ];
            }),

            // Status Indicators
            'status_indicators' => [
                'needs_attention' => $this->needsAttention(),
                'is_overdue' => $this->isOverdue(),
                'can_mark_attendance' => $this->canMarkAttendance(),
                'is_upcoming' => $this->isUpcoming(),
                'priority' => $this->getPriority(),
            ],

            // Quick Actions
            'actions' => [
                'can_mark_attendance' => $this->canMarkAttendance(),
                'can_edit_session' => $this->canEditSession(),
                'can_view_details' => true,
                'can_export_attendance' => $attendanceMarked,
            ],

            // Time Information
            'time_info' => [
                'is_today' => $this->session_date->isToday(),
                'is_past' => $this->session_date->isPast(),
                'is_future' => $this->session_date->isFuture(),
                'days_from_now' => $this->session_date->diffInDays(now(), false),
                'formatted_date' => $this->session_date->format('M d, Y'),
                'relative_date' => $this->session_date->diffForHumans(),
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function activeRosterEnrollmentCount(): ?int
    {
        if (! $this->relationLoaded('courseOffering') || ! $this->courseOffering) {
            return $this->expected_attendees;
        }

        return $this->courseOffering->activeClassRosterEnrollmentCount();
    }

    protected function activeRosterAttendances()
    {
        if (! $this->relationLoaded('courseOffering') || ! $this->courseOffering) {
            return $this->attendances;
        }

        return $this->attendances
            ->whereIn('student_id', $this->courseOffering->activeClassRosterStudentIds())
            ->values();
    }

    protected function actualRosterAttendees(): ?int
    {
        if (! $this->relationLoaded('attendances')) {
            return $this->actual_attendees;
        }

        return $this->activeRosterAttendances()
            ->whereIn('status', ['present', 'late'])
            ->count();
    }

    protected function activeRosterAttendanceMarked(): bool
    {
        if (! $this->relationLoaded('attendances')) {
            return $this->attendance_marked;
        }

        return $this->activeRosterAttendances()->isNotEmpty();
    }

    /**
     * Check if session needs attention
     */
    protected function needsAttention(): bool
    {
        // Session needs attention if:
        // 1. Attendance not marked and session is completed or overdue
        // 2. Low attendance rate (if marked)

        if (! $this->activeRosterAttendanceMarked() && $this->isOverdue()) {
            return true;
        }

        $expectedAttendees = $this->activeRosterEnrollmentCount();
        $actualAttendees = $this->actualRosterAttendees();
        $attendancePercentage = $expectedAttendees > 0 && $actualAttendees !== null
            ? round(($actualAttendees / $expectedAttendees) * 100, 1)
            : $this->attendance_percentage;

        if ($this->activeRosterAttendanceMarked() && $attendancePercentage < 60) {
            return true;
        }

        return false;
    }

    /**
     * Check if session is overdue for attendance marking
     */
    protected function isOverdue(): bool
    {
        return ! $this->activeRosterAttendanceMarked() &&
               $this->session_date->lt(now()->subHours(2)) &&
               in_array($this->status, ['completed', 'in_progress']);
    }

    /**
     * Check if attendance can be marked
     */
    protected function canMarkAttendance(): bool
    {
        return in_array($this->status, ['completed', 'in_progress']) ||
               ($this->status === 'scheduled' && $this->session_date->lte(now()));
    }

    /**
     * Check if session is upcoming
     */
    protected function isUpcoming(): bool
    {
        return $this->session_date->isFuture() && $this->status === 'scheduled';
    }

    /**
     * Check if session can be edited
     */
    protected function canEditSession(): bool
    {
        return $this->session_date->isFuture() ||
               ($this->session_date->isToday() && $this->start_time->gt(now()));
    }

    /**
     * Get priority level for session
     */
    protected function getPriority(): string
    {
        if ($this->needsAttention()) {
            return 'high';
        }

        if ($this->isUpcoming() && $this->session_date->isToday()) {
            return 'medium';
        }

        if ($this->isUpcoming() && $this->session_date->isTomorrow()) {
            return 'medium';
        }

        return 'low';
    }
}
