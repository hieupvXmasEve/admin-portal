<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Handle both array and model data
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'id' => $data['id'],
            'title' => $data['title'] ?? $data['session_title'] ?? null,
            'description' => $data['description'] ?? $data['session_description'] ?? null,
            'date' => $data['date'] ?? $data['session_date'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration_minutes' => $data['duration_minutes'] ?? $this->calculateDuration($data['start_time'], $data['end_time']),
            'session_type' => $data['session_type'] ?? 'lecture',
            'delivery_mode' => $data['delivery_mode'] ?? 'in_person',
            'status' => $data['status'] ?? 'scheduled',

            // Course Information
            'course' => $data['course'] ?? null,

            // Room Information
            'room' => $data['room'] ?? null,

            // Attendance Information
            'attendance_marked' => $data['attendance_marked'] ?? false,
            'expected_attendees' => $data['expected_attendees'] ?? 0,
            'actual_attendees' => $data['actual_attendees'] ?? null,
            'attendance_percentage' => $data['attendance_percentage'] ?? null,

            // Academic Content
            'learning_objectives' => $data['learning_objectives'] ?? [],
            'topics_covered' => $data['topics_covered'] ?? [],
            'required_materials' => $data['required_materials'] ?? [],
            'preparation_notes' => $data['preparation_notes'] ?? null,

            // Status and Actions
            'status_info' => [
                'status' => $data['status'] ?? 'scheduled',
                'status_label' => $this->getStatusLabel($data['status'] ?? 'scheduled'),
                'status_color' => $this->getStatusColor($data['status'] ?? 'scheduled'),
                'can_edit' => $this->canEdit($data),
                'can_cancel' => $this->canCancel($data),
                'can_mark_attendance' => $this->canMarkAttendance($data),
            ],

            // Time Information
            'time_info' => [
                'is_today' => $this->isToday($data['date'] ?? $data['session_date']),
                'is_past' => $this->isPast($data['date'] ?? $data['session_date'], $data['start_time']),
                'is_upcoming' => $this->isUpcoming($data['date'] ?? $data['session_date'], $data['start_time']),
                'starts_in_minutes' => $this->getStartsInMinutes($data['date'] ?? $data['session_date'], $data['start_time']),
                'formatted_date' => $this->getFormattedDate($data['date'] ?? $data['session_date']),
                'formatted_time' => $this->getFormattedTime($data['start_time'], $data['end_time']),
                'relative_time' => $this->getRelativeTime($data['date'] ?? $data['session_date'], $data['start_time']),
            ],

            // Quick Stats
            'quick_stats' => [
                'duration_hours' => round(($data['duration_minutes'] ?? 0) / 60, 1),
                'attendance_status' => $this->getAttendanceStatus($data),
                'preparation_status' => $this->getPreparationStatus($data),
            ],

            // Actions Available
            'available_actions' => $this->getAvailableActions($data),

            // Timestamps
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    /**
     * Calculate duration between start and end time
     */
    protected function calculateDuration(string $startTime, string $endTime): int
    {
        try {
            $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
            $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);

            return $start->diffInMinutes($end);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get status label for display
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for UI
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'scheduled' => 'blue',
            'in_progress' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if session can be edited
     */
    protected function canEdit(array $data): bool
    {
        $status = $data['status'] ?? 'scheduled';
        $date = $data['date'] ?? $data['session_date'] ?? null;

        if ($status === 'completed') {
            return false;
        }

        if ($date) {
            $sessionDate = \Carbon\Carbon::parse($date);

            return $sessionDate->isFuture() || $sessionDate->isToday();
        }

        return true;
    }

    /**
     * Check if session can be cancelled
     */
    protected function canCancel(array $data): bool
    {
        $status = $data['status'] ?? 'scheduled';

        return in_array($status, ['scheduled', 'in_progress']);
    }

    /**
     * Check if attendance can be marked
     */
    protected function canMarkAttendance(array $data): bool
    {
        $status = $data['status'] ?? 'scheduled';
        $date = $data['date'] ?? $data['session_date'] ?? null;

        if (! $date) {
            return false;
        }

        $sessionDate = \Carbon\Carbon::parse($date);

        return in_array($status, ['completed', 'in_progress']) ||
               ($status === 'scheduled' && $sessionDate->lte(now()));
    }

    /**
     * Check if session is today
     */
    protected function isToday(?string $date): bool
    {
        if (! $date) {
            return false;
        }

        return \Carbon\Carbon::parse($date)->isToday();
    }

    /**
     * Check if session is in the past
     */
    protected function isPast(?string $date, string $startTime): bool
    {
        if (! $date) {
            return false;
        }

        $sessionDateTime = \Carbon\Carbon::parse($date.' '.$startTime);

        return $sessionDateTime->isPast();
    }

    /**
     * Check if session is upcoming
     */
    protected function isUpcoming(?string $date, string $startTime): bool
    {
        if (! $date) {
            return false;
        }

        $sessionDateTime = \Carbon\Carbon::parse($date.' '.$startTime);

        return $sessionDateTime->isFuture();
    }

    /**
     * Get minutes until session starts
     */
    protected function getStartsInMinutes(?string $date, string $startTime): ?int
    {
        if (! $date) {
            return null;
        }

        $sessionDateTime = \Carbon\Carbon::parse($date.' '.$startTime);

        if ($sessionDateTime->isPast()) {
            return null;
        }

        return now()->diffInMinutes($sessionDateTime);
    }

    /**
     * Get formatted date
     */
    protected function getFormattedDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        $carbonDate = \Carbon\Carbon::parse($date);

        if ($carbonDate->isToday()) {
            return 'Today';
        } elseif ($carbonDate->isTomorrow()) {
            return 'Tomorrow';
        } elseif ($carbonDate->isYesterday()) {
            return 'Yesterday';
        } else {
            return $carbonDate->format('M j, Y');
        }
    }

    /**
     * Get formatted time range
     */
    protected function getFormattedTime(string $startTime, string $endTime): string
    {
        return $startTime.' - '.$endTime;
    }

    /**
     * Get relative time description
     */
    protected function getRelativeTime(?string $date, string $startTime): ?string
    {
        if (! $date) {
            return null;
        }

        $sessionDateTime = \Carbon\Carbon::parse($date.' '.$startTime);

        return $sessionDateTime->diffForHumans();
    }

    /**
     * Get attendance status
     */
    protected function getAttendanceStatus(array $data): string
    {
        $attendanceMarked = $data['attendance_marked'] ?? false;
        $status = $data['status'] ?? 'scheduled';

        if ($status === 'completed' && ! $attendanceMarked) {
            return 'pending';
        } elseif ($attendanceMarked) {
            return 'marked';
        } else {
            return 'not_applicable';
        }
    }

    /**
     * Get preparation status
     */
    protected function getPreparationStatus(array $data): string
    {
        $hasObjectives = ! empty($data['learning_objectives']);
        $hasTopics = ! empty($data['topics_covered']);
        $hasNotes = ! empty($data['preparation_notes']);

        $preparationScore = ($hasObjectives ? 1 : 0) + ($hasTopics ? 1 : 0) + ($hasNotes ? 1 : 0);

        return match ($preparationScore) {
            3 => 'complete',
            2 => 'good',
            1 => 'partial',
            0 => 'none',
            default => 'none',
        };
    }

    /**
     * Get available actions for the session
     */
    protected function getAvailableActions(array $data): array
    {
        return [
            'edit' => $this->canEdit($data),
            'cancel' => $this->canCancel($data),
            'mark_attendance' => $this->canMarkAttendance($data),
            'view_details' => true,
            'duplicate' => true,
            'export' => true,
        ];
    }
}
