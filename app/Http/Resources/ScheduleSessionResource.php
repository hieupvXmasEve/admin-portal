<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleSessionResource extends JsonResource
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
            'title' => $this->session_title,
            'description' => $this->session_description,
            'unitCode' => $this->courseOffering->curriculumUnit->unit->code ?? 'N/A',
            'unitName' => $this->courseOffering->curriculumUnit->unit->name ?? 'N/A',
            'section' => $this->courseOffering->section ?? 'A',
            'lecturer' => [
                'id' => $this->lecture->id ?? null,
                'name' => $this->lecture->name ?? 'TBA',
                'email' => $this->lecture->email ?? null,
            ],
            'room' => [
                'id' => $this->room->id ?? null,
                'name' => $this->room->name ?? 'TBA',
                'code' => $this->room->code ?? null,
                'building' => $this->room->building ? [
                    'id' => $this->room->building->id,
                    'name' => $this->room->building->name,
                    'code' => $this->room->building->code,
                ] : null,
                'fullCode' => $this->room->full_code ?? 'TBA',
            ],
            'campus' => [
                'id' => $this->room->campus->id ?? null,
                'name' => $this->room->campus->name ?? 'N/A',
                'code' => $this->room->campus->code ?? null,
            ],
            'schedule' => [
                'date' => $this->session_date->toDateString(),
                'startTime' => $this->start_time->format('H:i'),
                'endTime' => $this->end_time->format('H:i'),
                'duration' => $this->duration_minutes,
                'formattedDate' => $this->formatted_date,
                'formattedTime' => $this->formatted_time,
            ],
            'details' => [
                'sessionType' => $this->session_type,
                'deliveryMode' => $this->delivery_mode,
                'status' => $this->status,
                'statusBadgeColor' => $this->status_badge_color,
                'attendanceRequired' => $this->attendance_required,
                'attendanceTrackingEnabled' => $this->attendance_tracking_enabled,
                'isAssessment' => $this->is_assessment,
                'assessmentWeight' => $this->assessment_weight,
                'sequenceNumber' => $this->sequence_number,
            ],
            'attendance' => [
                'expectedAttendees' => $this->expected_attendees,
                'actualAttendees' => $this->actual_attendees,
                'attendancePercentage' => $this->attendance_percentage,
                'stats' => $this->attendance_stats,
            ],
            'metadata' => [
                'learningObjectives' => $this->learning_objectives,
                'requiredMaterials' => $this->required_materials,
                'topicsCovered' => $this->topics_covered,
                'instructorNotes' => $this->instructor_notes,
                'adminNotes' => $this->admin_notes,
                'studentInstructions' => $this->student_instructions,
            ],
            'timestamps' => [
                'scheduledAt' => $this->scheduled_at?->toISOString(),
                'startedAt' => $this->started_at?->toISOString(),
                'endedAt' => $this->ended_at?->toISOString(),
                'cancelledAt' => $this->cancelled_at?->toISOString(),
                'createdAt' => $this->created_at->toISOString(),
                'updatedAt' => $this->updated_at->toISOString(),
            ],
        ];
    }
}
