<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimetableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'semester' => $this->resource['semester'],
            'weekly_schedule' => $this->formatWeeklySchedule($this->resource['weekly_schedule']),
            'schedule_summary' => $this->formatScheduleSummary($this->resource['schedule_summary']),
            'time_blocks' => $this->formatTimeBlocks($this->resource['time_blocks']),
            'filters_applied' => $this->resource['filters_applied'],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format weekly schedule for display
     */
    protected function formatWeeklySchedule(array $weeklySchedule): array
    {
        $formatted = [];

        foreach ($weeklySchedule as $day => $dayData) {
            $formatted[$day] = [
                'day_name' => $dayData['day_name'],
                'day_abbreviation' => substr($dayData['day_name'], 0, 3),
                'session_count' => count($dayData['sessions']),
                'sessions' => collect($dayData['sessions'])->map(function ($session) {
                    return [
                        'id' => $session['id'],
                        'course_code' => $session['course_code'],
                        'course_name' => $session['course_name'],
                        'session_type' => $session['session_type'],
                        'session_type_display' => ucfirst($session['session_type']),
                        'time' => [
                            'start' => $session['start_time'],
                            'end' => $session['end_time'],
                            'display' => $this->formatTimeRange($session['start_time'], $session['end_time']),
                            'duration_minutes' => $session['duration_minutes'],
                            'duration_display' => $this->formatDuration($session['duration_minutes']),
                        ],
                        'lecturer' => $session['lecturer'],
                        'room' => [
                            'code' => $session['room']['code'],
                            'name' => $session['room']['name'],
                            'building' => $session['room']['building'],
                            'full_location' => $this->formatRoomLocation($session['room']),
                        ],
                        'color' => $session['color'],
                        'is_current' => $this->isCurrentSession($session, $day),
                        'is_upcoming' => $this->isUpcomingSession($session, $day),
                    ];
                })->toArray(),
                'total_duration' => $this->calculateDayDuration($dayData['sessions']),
            ];
        }

        return $formatted;
    }

    /**
     * Format schedule summary
     */
    protected function formatScheduleSummary(array $summary): array
    {
        return [
            'overview' => [
                'total_sessions_per_week' => $summary['total_sessions_per_week'],
                'unique_courses' => $summary['unique_courses'],
                'total_hours_per_week' => $summary['total_hours_per_week'],
                'average_hours_per_day' => round($summary['total_hours_per_week'] / 7, 1),
            ],
            'schedule_pattern' => [
                'busiest_day' => $summary['busiest_day'],
                'earliest_start' => $summary['earliest_start'],
                'latest_end' => $summary['latest_end'],
                'earliest_start_display' => $this->formatTime($summary['earliest_start']),
                'latest_end_display' => $this->formatTime($summary['latest_end']),
            ],
            'distribution' => [
                'by_day' => $summary['day_distribution'],
                'by_session_type' => $summary['session_type_distribution'],
            ],
        ];
    }

    /**
     * Format time blocks for grid view
     */
    protected function formatTimeBlocks(array $timeBlocks): array
    {
        return collect($timeBlocks)->map(function ($block) {
            return [
                'time' => $block['time'],
                'display_time' => $block['display_time'],
                'sessions' => $this->formatTimeBlockSessions($block['sessions']),
                'has_sessions' => $this->hasAnySessions($block['sessions']),
            ];
        })->toArray();
    }

    /**
     * Format sessions within a time block
     */
    protected function formatTimeBlockSessions(array $sessions): array
    {
        $formatted = [];

        foreach ($sessions as $day => $daySessions) {
            $formatted[$day] = collect($daySessions)->map(function ($session) {
                return [
                    'id' => $session['id'],
                    'course_code' => $session['course_code'],
                    'session_type' => $session['session_type'],
                    'room' => $session['room'],
                    'color' => $session['color'],
                    'time_span' => [
                        'start' => $session['start_time'],
                        'end' => $session['end_time'],
                    ],
                ];
            })->toArray();
        }

        return $formatted;
    }

    /**
     * Format time range for display
     */
    protected function formatTimeRange(string $startTime, string $endTime): string
    {
        $start = \Carbon\Carbon::createFromTimeString($startTime);
        $end = \Carbon\Carbon::createFromTimeString($endTime);
        
        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
    }

    /**
     * Format single time for display
     */
    protected function formatTime(string $time): string
    {
        return \Carbon\Carbon::createFromTimeString($time)->format('g:i A');
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
     * Format room location
     */
    protected function formatRoomLocation(array $room): string
    {
        $parts = array_filter([
            $room['code'],
            $room['name'],
            $room['building'],
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Check if session is currently happening
     */
    protected function isCurrentSession(array $session, string $day): bool
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        return $day === $currentDay && 
               $currentTime >= $session['start_time'] && 
               $currentTime < $session['end_time'];
    }

    /**
     * Check if session is upcoming today
     */
    protected function isUpcomingSession(array $session, string $day): bool
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        return $day === $currentDay && $currentTime < $session['start_time'];
    }

    /**
     * Calculate total duration for a day
     */
    protected function calculateDayDuration(array $sessions): array
    {
        $totalMinutes = array_sum(array_column($sessions, 'duration_minutes'));
        
        return [
            'total_minutes' => $totalMinutes,
            'display' => $this->formatDuration($totalMinutes),
        ];
    }

    /**
     * Check if time block has any sessions
     */
    protected function hasAnySessions(array $sessions): bool
    {
        foreach ($sessions as $daySessions) {
            if (!empty($daySessions)) {
                return true;
            }
        }
        return false;
    }
}
