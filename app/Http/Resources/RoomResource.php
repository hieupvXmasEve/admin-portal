<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campus_id' => $this->campus_id,
            'name' => $this->name,
            'code' => $this->code,
            'full_code' => $this->full_code,
            'building' => $this->building,
            'floor' => $this->floor,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'is_bookable' => $this->is_bookable,
            'requires_approval' => $this->requires_approval,
            'available_from' => $this->available_from?->format('H:i:s'),
            'available_until' => $this->available_until?->format('H:i:s'),
            'blocked_days' => $this->blocked_days,
            'description' => $this->description,
            'usage_guidelines' => $this->usage_guidelines,
            'booking_notes' => $this->booking_notes,
            'campus' => $this->whenLoaded('campus', function () {
                return [
                    'id' => $this->campus->id,
                    'name' => $this->campus->name,
                    'code' => $this->campus->code,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Computed properties
            'is_available' => $this->isAvailable(),
            'requires_approval_status' => $this->requiresApproval(),
            
            // Type and status labels for UI
            'type_label' => $this->getTypeLabel(),
            'status_label' => $this->getStatusLabel(),
        ];
    }

    /**
     * Get human-readable type label.
     */
    private function getTypeLabel(): string
    {
        return match ($this->type) {
            'classroom' => 'Classroom',
            'laboratory' => 'Laboratory',
            'computer_lab' => 'Computer Lab',
            'auditorium' => 'Auditorium',
            'meeting_room' => 'Meeting Room',
            'library' => 'Library',
            'study_room' => 'Study Room',
            'workshop' => 'Workshop',
            'office' => 'Office',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'occupied' => 'Occupied',
            'maintenance' => 'Under Maintenance',
            'out_of_service' => 'Out of Service',
            'reserved' => 'Reserved',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}