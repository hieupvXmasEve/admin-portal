<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'session' => $this->formatSession($this->resource['session']),
            'course' => $this->formatCourse($this->resource['course']),
            'lecturer' => $this->formatLecturer($this->resource['lecturer']),
            'room' => $this->formatRoom($this->resource['room']),
            'attendance_info' => $this->formatAttendanceInfo($this->resource['attendance_info']),
            'upcoming_sessions' => $this->formatUpcomingSessions($this->resource['upcoming_sessions']),
            'session_status' => $this->getSessionStatus($this->resource['session']),
        ];
    }

    /**
     * Format session information
     */
    protected function formatSession(array $session): array
    {
        return [
            'id' => $session['id'],
            'day_of_week' => $session['day_of_week'],
            'day_display' => ucfirst($session['day_of_week']),
            'start_time' => $session['start_time'],
            'end_time' => $session['end_time'],
            'time_display' => $this->formatTimeRange($session['start_time'], $session['end_time']),
            'session_type' => $session['session_type'],
            'session_type_display' => ucfirst($session['session_type']),
            'duration_minutes' => $session['duration_minutes'],
            'duration_display' => $this->formatDuration($session['duration_minutes']),
        ];
    }

    /**
     * Format course information
     */
    protected function formatCourse(array $course): array
    {
        return [
            'code' => $course['code'],
            'name' => $course['name'],
            'credit_hours' => $course['credit_hours'],
            'full_title' => $course['code'].' - '.$course['name'],
        ];
    }

    /**
     * Format lecturer information
     */
    protected function formatLecturer(?array $lecturer): ?array
    {
        if (! $lecturer || ! $lecturer['id']) {
            return null;
        }

        return [
            'id' => $lecturer['id'],
            'name' => $lecturer['name'],
            'email' => $lecturer['email'],
            'phone' => $lecturer['phone'],
            'contact_info' => $this->formatContactInfo($lecturer),
        ];
    }

    /**
     * Format room information
     */
    protected function formatRoom(?array $room): ?array
    {
        if (! $room || ! $room['id']) {
            return null;
        }

        return [
            'id' => $room['id'],
            'code' => $room['code'],
            'name' => $room['name'],
            'building' => $room['building'] ?? null, // May be building object or null
            'capacity' => $room['capacity'],
            'facilities' => $room['facilities'],
            'full_location' => $this->formatRoomLocation($room),
        ];
    }

    /**
     * Format attendance information
     */
    protected function formatAttendanceInfo(array $attendanceInfo): array
    {
        return [
            'summary' => [
                'total_sessions' => $attendanceInfo['total_sessions'],
                'attended_sessions' => $attendanceInfo['attended_sessions'],
                'attendance_percentage' => $attendanceInfo['attendance_percentage'],
                'attendance_status' => $this->getAttendanceStatus($attendanceInfo['attendance_percentage']),
            ],
            'recent_attendance' => collect($attendanceInfo['recent_attendance'])->map(function ($record) {
                return [
                    'date' => $record['date'] ?? null,
                    'status' => $record['status'] ?? 'unknown',
                    'status_display' => $this->getAttendanceStatusDisplay($record['status'] ?? 'unknown'),
                ];
            })->toArray(),
        ];
    }

    /**
     * Format upcoming sessions
     */
    protected function formatUpcomingSessions(array $upcomingSessions): array
    {
        return collect($upcomingSessions)->map(function ($session) {
            return [
                'date' => $session['date'] ?? null,
                'day_of_week' => $session['day_of_week'] ?? null,
                'start_time' => $session['start_time'] ?? null,
                'end_time' => $session['end_time'] ?? null,
                'time_display' => isset($session['start_time'], $session['end_time'])
                    ? $this->formatTimeRange($session['start_time'], $session['end_time'])
                    : null,
            ];
        })->toArray();
    }

    /**
     * Get session status based on current time
     */
    protected function getSessionStatus(array $session): array
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');
        $sessionDay = $session['day_of_week'];
        $startTime = $session['start_time'];
        $endTime = $session['end_time'];

        if ($currentDay === $sessionDay) {
            if ($currentTime < $startTime) {
                $minutesUntil = $this->calculateMinutesUntil($startTime);

                return [
                    'status' => 'upcoming_today',
                    'display' => 'Upcoming Today',
                    'minutes_until' => $minutesUntil,
                    'time_until_display' => $this->formatTimeUntil($minutesUntil),
                ];
            } elseif ($currentTime >= $startTime && $currentTime < $endTime) {
                $minutesRemaining = $this->calculateMinutesRemaining($endTime);

                return [
                    'status' => 'in_progress',
                    'display' => 'In Progress',
                    'minutes_remaining' => $minutesRemaining,
                    'time_remaining_display' => $this->formatTimeRemaining($minutesRemaining),
                ];
            } else {
                return [
                    'status' => 'completed_today',
                    'display' => 'Completed Today',
                ];
            }
        }

        return [
            'status' => 'scheduled',
            'display' => 'Scheduled',
            'next_occurrence' => $this->getNextOccurrence($sessionDay),
        ];
    }

    /**
     * Format time range for display
     */
    protected function formatTimeRange(string $startTime, string $endTime): string
    {
        $start = \Carbon\Carbon::createFromTimeString($startTime);
        $end = \Carbon\Carbon::createFromTimeString($endTime);

        return $start->format('g:i A').' - '.$end->format('g:i A');
    }

    /**
     * Format duration in minutes to readable format
     */
    protected function formatDuration(int $minutes): string
    {
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }

    /**
     * Format contact information
     */
    protected function formatContactInfo(array $lecturer): array
    {
        $contact = [];

        if ($lecturer['email']) {
            $contact[] = [
                'type' => 'email',
                'value' => $lecturer['email'],
                'display' => 'Email: '.$lecturer['email'],
            ];
        }

        if ($lecturer['phone']) {
            $contact[] = [
                'type' => 'phone',
                'value' => $lecturer['phone'],
                'display' => 'Phone: '.$lecturer['phone'],
            ];
        }

        return $contact;
    }

    /**
     * Format room location
     */
    protected function formatRoomLocation(array $room): string
    {
        $buildingName = null;
        if (isset($room['building'])) {
            if (is_array($room['building'])) {
                $buildingName = $room['building']['name'] ?? null;
            } else {
                $buildingName = $room['building'];
            }
        }

        $parts = array_filter([
            $room['code'],
            $room['name'],
            $buildingName,
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Get attendance status based on percentage
     */
    protected function getAttendanceStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'excellent',
            $percentage >= 80 => 'good',
            $percentage >= 70 => 'satisfactory',
            $percentage >= 60 => 'warning',
            default => 'critical',
        };
    }

    /**
     * Get attendance status display text
     */
    protected function getAttendanceStatusDisplay(string $status): string
    {
        return match ($status) {
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'excused' => 'Excused',
            default => 'Unknown',
        };
    }

    /**
     * Calculate minutes until session starts
     */
    protected function calculateMinutesUntil(string $startTime): int
    {
        $now = now();
        $sessionStart = \Carbon\Carbon::createFromTimeString($startTime);

        // If session is tomorrow or later, calculate accordingly
        if ($sessionStart->lt($now)) {
            $sessionStart->addDay();
        }

        return $now->diffInMinutes($sessionStart);
    }

    /**
     * Calculate minutes remaining in session
     */
    protected function calculateMinutesRemaining(string $endTime): int
    {
        $now = now();
        $sessionEnd = \Carbon\Carbon::createFromTimeString($endTime);

        return $now->diffInMinutes($sessionEnd);
    }

    /**
     * Format time until session
     */
    protected function formatTimeUntil(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($mins > 0) {
            return "{$hours}h {$mins}m";
        }

        return "{$hours}h";
    }

    /**
     * Format time remaining in session
     */
    protected function formatTimeRemaining(int $minutes): string
    {
        return $this->formatTimeUntil($minutes).' remaining';
    }

    /**
     * Get next occurrence of session
     */
    protected function getNextOccurrence(string $dayOfWeek): string
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = array_search(strtolower(now()->format('l')), $daysOfWeek);
        $sessionDayIndex = array_search($dayOfWeek, $daysOfWeek);

        $daysUntil = ($sessionDayIndex - $currentDayIndex + 7) % 7;
        if ($daysUntil === 0) {
            $daysUntil = 7; // Next week
        }

        return now()->addDays($daysUntil)->format('l, M j');
    }
}
