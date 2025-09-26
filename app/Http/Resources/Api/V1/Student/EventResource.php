<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public static $wrap = null;
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_time' => $this->start_time->format('Y-m-d H:i:s'),
            'end_time' => $this->end_time->format('Y-m-d H:i:s'),
            'start_time_iso' => $this->start_time->toISOString(),
            'end_time_iso' => $this->end_time->toISOString(),
            'gold_reward_amount' => (float) $this->gold_reward_amount,
            'max_participants' => $this->max_participants,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'qr_code' => $this->qr_code,
            'organizer_type' => $this->organizer_type,
            'organizer_id' => $this->organizer_id,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),

            // Campus information
            'campus' => $this->whenLoaded('campus', function () {
                return [
                    'id' => $this->campus->id,
                    'name' => $this->campus->name,
                    'code' => $this->campus->code,
                ];
            }),

            // Creator information (limited for students)
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),

            // Event status flags
            'flags' => [
                'is_published' => $this->isPublished(),
                'is_cancelled' => $this->isCancelled(),
                'is_completed' => $this->isCompleted(),
                'is_draft' => $this->isDraft(),
                'can_register' => $this->canRegister(),
                'can_check_in' => $this->canCheckIn(),
                'has_started' => $this->hasStarted(),
                'has_ended' => $this->hasEnded(),
                'has_reached_capacity' => $this->hasReachedCapacity(),
            ],

            // Participation counts (public information)
            'participation' => [
                'registered_count' => $this->getRegisteredCount(),
                'checked_in_count' => $this->getCheckedInCount(),
                'completed_count' => $this->getCompletedCount(),
                'available_spots' => $this->getAvailableSpots(),
                'participation_rate' => round($this->getParticipationRate(), 2),
            ],

            // Student's participation status (if loaded)
            'my_participation' => $this->when(
                isset($this->student_participation) && $this->student_participation,
                function () {
                    return [
                        'id' => $this->student_participation->id,
                        'status' => $this->student_participation->status,
                        'status_display' => $this->getParticipationStatusDisplay($this->student_participation->status),
                        'registered_at' => $this->student_participation->registered_at?->format('Y-m-d H:i:s'),
                        'checkin_time' => $this->student_participation->checkin_time?->format('Y-m-d H:i:s'),
                        'gold_awarded' => $this->student_participation->gold_awarded,
                        'awarded_at' => $this->student_participation->awarded_at?->format('Y-m-d H:i:s'),
                        'can_cancel' => $this->student_participation->canCancel(),
                        'can_check_in' => $this->student_participation->canCheckIn(),
                    ];
                }
            ),

            // Event statistics (if loaded)
            'statistics' => $this->when(
                isset($this->statistics),
                $this->statistics
            ),

            // Time-based information
            'timing' => [
                'duration_minutes' => $this->start_time->diffInMinutes($this->end_time),
                'starts_in_minutes' => $this->start_time->isFuture() ? (int) now()->diffInMinutes($this->start_time) : null,
                'ends_in_minutes' => $this->end_time->isFuture() ? (int) now()->diffInMinutes($this->end_time) : null,
                'is_today' => $this->start_time->isToday(),
                'is_tomorrow' => $this->start_time->isTomorrow(),
                'is_this_week' => $this->start_time->isCurrentWeek(),
            ],

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get status display name
     */
    protected function getStatusDisplay(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get participation status display name
     */
    protected function getParticipationStatusDisplay(string $status): string
    {
        return match ($status) {
            'registered' => 'Registered',
            'checked_in' => 'Checked In',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
    }
}
