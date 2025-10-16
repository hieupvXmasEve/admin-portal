<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LecturerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile_phone' => $this->mobile_phone,
            'avatar_url' => $this->avatar_url,

            // Campus and Department Information
            'campus' => $this->whenLoaded('campus', function () {
                return [
                    'id' => $this->campus->id,
                    'name' => $this->campus->name,
                    'code' => $this->campus->code,
                    'location' => $this->campus->location,
                ];
            }),
            'department' => $this->department,
            'faculty' => $this->faculty,
            'specialization' => $this->specialization,
            'expertise_areas' => $this->expertise_areas,

            // Academic Information
            'academic_rank' => $this->academic_rank,
            'highest_degree' => $this->highest_degree,
            'degree_field' => $this->degree_field,
            'alma_mater' => $this->alma_mater,
            'graduation_year' => $this->graduation_year,

            // Employment Information
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'contract_start_date' => $this->contract_start_date?->format('Y-m-d'),
            'contract_end_date' => $this->contract_end_date?->format('Y-m-d'),
            'years_of_service' => $this->years_of_service,
            'is_contract_active' => $this->is_contract_active,

            // Teaching Preferences
            'preferred_teaching_days' => $this->preferred_teaching_days,
            'preferred_start_time' => $this->preferred_start_time?->format('H:i'),
            'preferred_end_time' => $this->preferred_end_time?->format('H:i'),
            'max_teaching_hours_per_week' => $this->max_teaching_hours_per_week,
            'teaching_modalities' => $this->teaching_modalities,
            'can_teach_online' => $this->can_teach_online,

            // Contact Information
            'office_address' => $this->office_address,
            'office_phone' => $this->office_phone,

            // Professional Information
            'biography' => $this->biography,
            'certifications' => $this->certifications,
            'languages' => $this->languages,

            // Status Information
            'is_active' => $this->is_active,
            'is_available_for_assignment' => $this->is_available_for_assignment,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),

            // Course Offerings (when loaded)
            'current_courses' => $this->whenLoaded('courseOfferings', function () {
                return $this->courseOfferings->map(function ($offering) {
                    return [
                        'id' => $offering->id,
                        'section_code' => $offering->section_code,
                        'delivery_mode' => $offering->delivery_mode,
                        'current_enrollment' => $offering->current_enrollment,
                        'max_capacity' => $offering->max_capacity,
                        'unit' => $this->when($offering->relationLoaded('unit'), [
                            'id' => $offering->unit->id,
                            'code' => $offering->unit->code,
                            'name' => $offering->unit->name,
                            'credit_points' => $offering->unit->credit_points,
                        ]),
                        'semester' => $this->when($offering->relationLoaded('semester'), [
                            'id' => $offering->semester->id,
                            'name' => $offering->semester->name,
                            'code' => $offering->semester->code,
                            'start_date' => $offering->semester->start_date?->format('Y-m-d'),
                            'end_date' => $offering->semester->end_date?->format('Y-m-d'),
                        ]),
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
