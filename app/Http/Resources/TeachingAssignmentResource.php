<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingAssignmentResource extends JsonResource
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
            'semester' => [
                'id' => $this->semester?->id,
                'name' => $this->semester?->name,
                'code' => $this->semester?->code,
                'start_date' => $this->semester?->start_date?->format('Y-m-d'),
                'end_date' => $this->semester?->end_date?->format('Y-m-d'),
                'is_active' => $this->semester?->is_active,
            ],
            'unit' => [
                'id' => $this->curriculumUnit?->unit?->id,
                'code' => $this->curriculumUnit?->unit?->code,
                'name' => $this->curriculumUnit?->unit?->name,
                'credit_points' => $this->curriculumUnit?->unit?->credit_points,
            ],
            'curriculum_unit' => [
                'id' => $this->curriculumUnit?->id,
                'unit_type' => $this->curriculumUnit?->unit_type,
                'is_elective' => $this->curriculumUnit?->is_elective,
            ],
            'section_code' => $this->section_code,
            'lecturer' => $this->when($this->lecture, [
                'id' => $this->lecture?->id,
                'employee_id' => $this->lecture?->employee_id,
                'full_name' => $this->lecture?->full_name,
                'display_name' => $this->lecture?->display_name,
                'title' => $this->lecture?->title,
                'first_name' => $this->lecture?->first_name,
                'last_name' => $this->lecture?->last_name,
                'email' => $this->lecture?->email,
                'department' => $this->lecture?->department,
                'faculty' => $this->lecture?->faculty,
                'academic_rank' => $this->lecture?->academic_rank,
                'employment_status' => $this->lecture?->employment_status,
            ]),
            'schedule' => [
                'days' => $this->schedule_days ?? [],
                'time_start' => $this->schedule_time_start?->format('H:i'),
                'time_end' => $this->schedule_time_end?->format('H:i'),
                'location' => $this->location,
            ],
            'enrollment' => [
                'current' => $this->current_enrollment ?? 0,
                'maximum' => $this->max_capacity,
                'waitlist_current' => $this->current_waitlist ?? 0,
                'waitlist_maximum' => $this->waitlist_capacity ?? 0,
                'status' => $this->enrollment_status,
                'available_spots' => $this->getAvailableSpots(),
                'available_waitlist_spots' => $this->getAvailableWaitlistSpots(),
            ],
            'delivery_mode' => $this->delivery_mode,
            'assignment_status' => $this->getInstructorAssignmentStatus(),
            'assignment_status_label' => $this->getInstructorAssignmentStatusLabel(),
            'assignment_priority' => $this->getAssignmentPriority(),
            'is_active' => $this->is_active,
            'campus' => [
                'id' => $this->campus?->id,
                'name' => $this->campus?->name,
                'code' => $this->campus?->code,
            ],
            'registration' => [
                'start_date' => $this->registration_start_date?->format('Y-m-d'),
                'end_date' => $this->registration_end_date?->format('Y-m-d'),
                'is_open' => $this->isRegistrationOpen(),
            ],
            'flags' => [
                'has_instructor' => $this->hasInstructor(),
                'needs_instructor_urgently' => $this->needsInstructorBeforeClasses(),
                'can_enroll' => $this->canEnroll(),
                'can_join_waitlist' => $this->canJoinWaitlist(),
                'is_full' => $this->isFull(),
                'is_waitlist_full' => $this->isWaitlistFull(),
            ],
            'special_requirements' => $this->special_requirements,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
