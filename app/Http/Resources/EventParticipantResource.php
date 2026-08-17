<?php

namespace App\Http\Resources;

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
            'event_id' => $this->event_id,
            'student_id' => $this->student_id,
            'status' => $this->status,
            'registered_at' => $this->registered_at?->toISOString(),
            'checkin_time' => $this->checkin_time?->toISOString(),
            'checkin_device_info' => $this->checkin_device_info,
            'checkin_staff_id' => $this->checkin_staff_id,
            'gold_awarded' => $this->gold_awarded,
            'awarded_at' => $this->awarded_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Relationships
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_id' => $this->student->student_id,
                    'full_name' => $this->student->full_name,
                    'email' => $this->student->email,
                    'phone' => $this->student->phone,
                    'program' => $this->student->program ? [
                        'id' => $this->student->program->id,
                        'name' => $this->student->program->name,
                        'code' => $this->student->program->code,
                    ] : null,
                    'specialization' => $this->student->specialization ? [
                        'id' => $this->student->specialization->id,
                        'name' => $this->student->specialization->name,
                        'code' => $this->student->specialization->code,
                    ] : null,
                    // Plan 260817-0017 phase 4: `students.status` here is a
                    // known, documented gap — a Resource has no batching seam
                    // (one instance per row) and this phase does not thread a
                    // resolved-status map through every Engagement controller
                    // that builds this collection. Accepted per that phase's
                    // own escape hatch rather than resolving the reader here.
                    'status' => $this->student->status,
                    'academic_status' => $this->student->academic_status,
                ];
            }),

            'checkin_staff' => $this->whenLoaded('checkinStaff', function () {
                return [
                    'id' => $this->checkinStaff->id,
                    'name' => $this->checkinStaff->name,
                    'email' => $this->checkinStaff->email,
                ];
            }),

            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event->id,
                    'title' => $this->event->title,
                    'gold_reward_amount' => $this->event->gold_reward_amount,
                    'is_manual' => $this->event->is_manual,
                    'is_historical' => $this->event->is_historical,
                ];
            }),

            // Computed properties
            'can_check_in' => $this->canCheckIn(),
            'can_complete' => $this->canComplete(),
            'can_cancel' => $this->canCancel(),
            'has_been_awarded' => $this->hasBeenAwarded(),
            'time_since_registration' => $this->getTimeSinceRegistration(),
            'time_since_checkin' => $this->getTimeSinceCheckin(),
            'checkin_duration' => $this->getCheckInDuration(),

            // Status helpers
            'is_registered' => $this->isRegistered(),
            'is_checked_in' => $this->isCheckedIn(),
            'is_completed' => $this->isCompleted(),
            'is_cancelled' => $this->isCancelled(),
            'is_active' => $this->isActive(),
        ];
    }
}
