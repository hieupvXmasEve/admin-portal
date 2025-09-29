<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Remove default resource data wrapper so callers receive a flat payload.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campus_id' => $this->campus_id,
            'title' => $this->title,
            'description' => $this->description,
            'start_time' => $this->start_time?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),
            'location' => $this->location,
            'gold_reward_amount' => $this->gold_reward_amount,
            'max_participants' => $this->max_participants,
            'qr_code' => $this->qr_code,
            'organizer_type' => $this->organizer_type,
            'organizer_id' => $this->organizer_id,
            'status' => $this->status,
            'is_manual' => $this->is_manual,
            'is_historical' => $this->is_historical,
            'published_at' => $this->published_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'campus' => $this->whenLoaded('campus'),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_by_admin' => $this->whenLoaded('createdByAdmin', function () {
                return [
                    'id' => $this->createdByAdmin->id,
                    'name' => $this->createdByAdmin->name,
                    'email' => $this->createdByAdmin->email,
                ];
            }),

            // Computed properties
            'is_published' => $this->isPublished(),
            'is_cancelled' => $this->isCancelled(),
            'is_completed' => $this->isCompleted(),
            'is_manual_event' => $this->isManual(),
            'is_historical_event' => $this->isHistorical(),
            'can_register' => $this->canRegister(),
            'has_reached_capacity' => $this->hasReachedCapacity(),

            // Participant counts (when available)
            'registered_count' => $this->when(
                $this->relationLoaded('participants'),
                fn() => $this->getRegisteredCount()
            ),
            'checked_in_count' => $this->when(
                $this->relationLoaded('participants'),
                fn() => $this->getCheckedInCount()
            ),
            'completed_count' => $this->when(
                $this->relationLoaded('participants'),
                fn() => $this->getCompletedCount()
            ),
        ];
    }
}
