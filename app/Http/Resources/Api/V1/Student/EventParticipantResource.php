<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'registered_at' => $this->registered_at?->format('Y-m-d H:i:s'),
            'checkin_time' => $this->checkin_time?->format('Y-m-d H:i:s'),
            'gold_awarded' => $this->gold_awarded,
            'awarded_at' => $this->awarded_at?->format('Y-m-d H:i:s'),

            // Event information
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event->id,
                    'title' => $this->event->title,
                    'description' => $this->event->description,
                    'location' => $this->event->location,
                    'start_time' => $this->event->start_time->format('Y-m-d H:i:s'),
                    'end_time' => $this->event->end_time->format('Y-m-d H:i:s'),
                    'start_time_iso' => $this->event->start_time->toISOString(),
                    'end_time_iso' => $this->event->end_time->toISOString(),
                    'gold_reward_amount' => (float) $this->event->gold_reward_amount,
                    'status' => $this->event->status,
                    'status_display' => $this->getEventStatusDisplay($this->event->status),
                    'qr_code' => $this->event->qr_code,

                    // Event flags
                    'flags' => [
                        'is_published' => $this->event->isPublished(),
                        'is_cancelled' => $this->event->isCancelled(),
                        'is_completed' => $this->event->isCompleted(),
                        'has_started' => $this->event->hasStarted(),
                        'has_ended' => $this->event->hasEnded(),
                    ],

                    // Campus information
                    'campus' => $this->whenLoaded('event.campus', function () {
                        return [
                            'id' => $this->event->campus->id,
                            'name' => $this->event->campus->name,
                            'code' => $this->event->campus->code,
                        ];
                    }),

                    // Creator information (limited)
                    'creator' => $this->whenLoaded('event.creator', function () {
                        return [
                            'id' => $this->event->creator->id,
                            'name' => $this->event->creator->name,
                        ];
                    }),
                ];
            }),

            // Student information (limited, mainly for admin use)
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_code' => $this->student->student_code,
                    'full_name' => $this->student->full_name,
                    'email' => $this->student->email,
                ];
            }),

            // Check-in staff information (if available)
            'checkin_staff' => $this->whenLoaded('checkinStaff', function () {
                return $this->checkinStaff ? [
                    'id' => $this->checkinStaff->id,
                    'name' => $this->checkinStaff->name,
                ] : null;
            }),

            // Participation flags and capabilities
            'flags' => [
                'is_registered' => $this->isRegistered(),
                'is_checked_in' => $this->isCheckedIn(),
                'is_completed' => $this->isCompleted(),
                'is_cancelled' => $this->isCancelled(),
                'is_active' => $this->isActive(),
                'can_check_in' => $this->canCheckIn(),
                'can_complete' => $this->canComplete(),
                'can_cancel' => $this->canCancel(),
                'has_been_awarded' => $this->hasBeenAwarded(),
            ],

            // Time-based information
            'timing' => [
                'time_since_registration_minutes' => $this->getTimeSinceRegistration(),
                'time_since_checkin_minutes' => $this->getTimeSinceCheckin(),
                'checkin_duration_minutes' => $this->getCheckInDuration(),
            ],

            // Device information (sanitized for privacy)
            'checkin_device_info' => $this->when(
                $this->checkin_device_info && $request->user()?->id === $this->student_id,
                function () {
                    return [
                        'device_type' => $this->checkin_device_info['device_type'] ?? null,
                        'browser' => $this->checkin_device_info['browser'] ?? null,
                        'platform' => $this->checkin_device_info['platform'] ?? null,
                        // Exclude sensitive information like IP address for privacy
                    ];
                }
            ),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get participation status display name
     */
    protected function getStatusDisplay(): string
    {
        return match ($this->status) {
            'registered' => 'Registered',
            'checked_in' => 'Checked In',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get event status display name
     */
    protected function getEventStatusDisplay(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            default => ucfirst($status),
        };
    }
}
