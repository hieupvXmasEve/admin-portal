<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableLecturerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile_phone' => $this->mobile_phone,

            // Professional Information
            'department' => $this->department,
            'faculty' => $this->faculty,
            'academic_rank' => $this->academic_rank,
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'specialization' => $this->specialization,
            'expertise_areas' => $this->expertise_areas ?? [],

            // Teaching Preferences and Constraints
            'preferred_teaching_days' => $this->preferred_teaching_days ?? [],
            'preferred_start_time' => $this->preferred_start_time?->format('H:i'),
            'preferred_end_time' => $this->preferred_end_time?->format('H:i'),
            'max_teaching_hours_per_week' => $this->max_teaching_hours_per_week,
            'teaching_modalities' => $this->teaching_modalities ?? [],
            'can_teach_online' => $this->can_teach_online,

            // Availability and Status
            'is_active' => $this->is_active,
            'is_available_for_assignment' => $this->is_available_for_assignment,
            'employment_status' => $this->employment_status,

            // Contract Information
            'contract_start_date' => $this->contract_start_date?->format('Y-m-d'),
            'contract_end_date' => $this->contract_end_date?->format('Y-m-d'),
            'is_contract_active' => $this->is_contract_active,

            // Campus Information
            'campus' => [
                'id' => $this->campus?->id,
                'name' => $this->campus?->name,
                'code' => $this->campus?->code,
            ],

            // Office Information
            'office_address' => $this->office_address,
            'office_phone' => $this->office_phone,

            // Teaching Load Information
            'current_course_load' => $this->current_course_load ?? $this->getCurrentSemesterLoad(),
            'years_of_service' => $this->years_of_service,

            // Assignment Capability (added by service)
            'can_be_assigned' => $this->can_be_assigned ?? true,
            'conflicts' => ConflictResource::collection($this->whenLoaded('conflicts', $this->conflicts ?? collect())),

            // Additional Information
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'highest_degree' => $this->highest_degree,
            'degree_field' => $this->degree_field,
            'alma_mater' => $this->alma_mater,
            'graduation_year' => $this->graduation_year,
            'certifications' => $this->certifications ?? [],
            'languages' => $this->languages ?? [],

            // Computed Properties
            'availability_status' => $this->getAvailabilityStatus(),
            'teaching_capacity_status' => $this->getTeachingCapacityStatus(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get lecturer availability status
     */
    private function getAvailabilityStatus(): string
    {
        if (! $this->is_active || $this->employment_status !== 'active') {
            return 'inactive';
        }

        if (! $this->is_available_for_assignment) {
            return 'unavailable';
        }

        if ($this->employment_type === 'contract' && ! $this->is_contract_active) {
            return 'contract_expired';
        }

        return 'available';
    }

    /**
     * Get teaching capacity status
     */
    private function getTeachingCapacityStatus(): string
    {
        $currentLoad = $this->current_course_load ?? $this->getCurrentSemesterLoad();
        $maxHours = $this->max_teaching_hours_per_week;

        if (! $maxHours) {
            return 'unlimited';
        }

        $percentage = ($currentLoad / $maxHours) * 100;

        if ($percentage >= 100) {
            return 'at_capacity';
        } elseif ($percentage >= 80) {
            return 'near_capacity';
        } elseif ($percentage >= 50) {
            return 'moderate_load';
        } else {
            return 'light_load';
        }
    }
}
