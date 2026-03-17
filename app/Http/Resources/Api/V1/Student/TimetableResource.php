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
                'day' => $dayData['day'],
                'day_abbreviation' => $dayData['day_abbreviation'],
                'event_count' => $dayData['event_count'] ?? count($dayData['events'] ?? []),
                'session_count' => $dayData['session_count'],
                'events' => collect($dayData['events'] ?? [])->map(function ($event) {
                    return [
                        'id' => $event['id'],
                        'campus_id' => $event['campus_id'],
                        'title' => $event['title'],
                        'description' => $event['description'],
                        'location' => $event['location'],
                        'status' => $event['status'],
                        'status_display' => $this->formatEventStatus($event['status']),
                        'start_time' => $event['start_time'],
                        'end_time' => $event['end_time'],
                        'start_time_iso' => $event['start_time_iso'],
                        'end_time_iso' => $event['end_time_iso'],
                        'time' => [
                            'start' => $event['display_start_time'],
                            'end' => $event['display_end_time'],
                            'display' => $this->formatTimeRange($event['display_start_time'], $event['display_end_time']),
                        ],
                        'occurrence_date' => $event['occurrence_date'],
                        'gold_reward_amount' => $event['gold_reward_amount'],
                        'max_participants' => $event['max_participants'],
                        'qr_code' => $event['qr_code'],
                        'organizer_type' => $event['organizer_type'],
                        'organizer_id' => $event['organizer_id'],
                        'published_at' => $event['published_at'],
                        'cancelled_at' => $event['cancelled_at'],
                        'completed_at' => $event['completed_at'],
                        'created_by_user_id' => $event['created_by_user_id'],
                        'created_by_admin_id' => $event['created_by_admin_id'],
                        'is_manual' => $event['is_manual'],
                        'is_historical' => $event['is_historical'],
                        'requires_registration' => $event['requires_registration'],
                        'created_at' => $event['created_at'],
                        'updated_at' => $event['updated_at'],
                        'is_multi_day' => $event['is_multi_day'],
                        'color' => $event['color'],
                        'item_type' => $event['item_type'],
                        'is_current' => $this->isCurrentEvent($event),
                        'is_upcoming' => $this->isUpcomingEvent($event),
                    ];
                })->toArray(),
                'sessions' => collect($dayData['sessions'])->map(function ($session) use ($day) {
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
                            'building' => $session['room']['building'] ?? null, // This may be building data object or null
                            'full_location' => $this->formatRoomLocation($session['room']),
                        ],
                        'color' => $session['color'],
                        'is_current' => $this->isCurrentSession($session, $day),
                        'is_upcoming' => $this->isUpcomingSession($session, $day),
                    ];
                })->toArray(),
                'total_duration' => $dayData['total_duration'],
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
                'total_sessions_per_week' => $summary['overview']['total_sessions_per_week'],
                'unique_courses' => $summary['overview']['unique_courses'],
                'total_hours_per_week' => $summary['overview']['total_hours_per_week'],
                'average_hours_per_day' => $summary['overview']['average_hours_per_day'],
            ],
            'schedule_pattern' => [
                'busiest_day' => $summary['schedule_pattern']['busiest_day'],
                'earliest_start' => $summary['schedule_pattern']['earliest_start'],
                'latest_end' => $summary['schedule_pattern']['latest_end'],
                'earliest_start_display' => $summary['schedule_pattern']['earliest_start_display'],
                'latest_end_display' => $summary['schedule_pattern']['latest_end_display'],
            ],
            'distribution' => [
                'by_day' => $summary['distribution']['by_day'],
                'by_session_type' => $summary['distribution']['by_session_type'],
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
    protected function formatTimeRange(string|\Carbon\Carbon $startTime, string|\Carbon\Carbon $endTime): string
    {
        $start = $startTime instanceof \Carbon\Carbon ? $startTime : \Carbon\Carbon::createFromTimeString($startTime);
        $end = $endTime instanceof \Carbon\Carbon ? $endTime : \Carbon\Carbon::createFromTimeString($endTime);

        return $start->format('g:i A').' - '.$end->format('g:i A');
    }

    /**
     * Format single time for display
     */
    protected function formatTime(string|\Carbon\Carbon|null $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $carbonTime = $time instanceof \Carbon\Carbon ? $time : \Carbon\Carbon::createFromTimeString($time);

        return $carbonTime->format('g:i A');
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

    protected function formatEventStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            default => ucfirst($status),
        };
    }

    /**
     * Check if session is currently happening
     */
    protected function isCurrentSession(array $session, string $day): bool
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        $startTime = $session['start_time'] instanceof \Carbon\Carbon ? $session['start_time']->format('H:i') : $session['start_time'];
        $endTime = $session['end_time'] instanceof \Carbon\Carbon ? $session['end_time']->format('H:i') : $session['end_time'];

        return $day === $currentDay &&
            $currentTime >= $startTime &&
            $currentTime < $endTime;
    }

    /**
     * Check if session is upcoming today
     */
    protected function isUpcomingSession(array $session, string $day): bool
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        $startTime = $session['start_time'] instanceof \Carbon\Carbon ? $session['start_time']->format('H:i') : $session['start_time'];

        return $day === $currentDay && $currentTime < $startTime;
    }

    protected function isCurrentEvent(array $event): bool
    {
        return now()->betweenIncluded(
            \Carbon\Carbon::parse($event['start_time']),
            \Carbon\Carbon::parse($event['end_time'])
        );
    }

    protected function isUpcomingEvent(array $event): bool
    {
        return now()->lt(\Carbon\Carbon::parse($event['start_time']));
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
            if (! empty($daySessions)) {
                return true;
            }
        }

        return false;
    }
}
