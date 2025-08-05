<?php

declare(strict_types=1);

namespace App\Http\Resources\ClassSession;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionResource extends JsonResource
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
            'course_offering_id' => $this->course_offering_id,
            'room_id' => $this->room_id,
            'room_booking_id' => $this->room_booking_id,
            'instructor_id' => $this->instructor_id,
            'session_title' => $this->session_title,
            'session_description' => $this->session_description,
            'session_date' => $this->session_date,
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'session_type' => $this->session_type,
            'delivery_mode' => $this->delivery_mode,
            'status' => $this->status,
            'online_meeting_url' => $this->online_meeting_url,
            'meeting_id' => $this->meeting_id,
            'meeting_password' => $this->meeting_password,
            'learning_objectives' => $this->learning_objectives,
            'required_materials' => $this->required_materials,
            'topics_covered' => $this->topics_covered,
            'attendance_required' => $this->attendance_required,
            'attendance_tracking_enabled' => $this->attendance_tracking_enabled,
            'expected_attendees' => $this->expected_attendees,
            'actual_attendees' => $this->actual_attendees,
            'attendance_percentage' => $this->attendance_percentage,
            'is_assessment' => $this->is_assessment,
            'assessment_weight' => $this->assessment_weight,
            'assessment_duration_minutes' => $this->assessment_duration_minutes,
            'assessment_materials_allowed' => $this->assessment_materials_allowed,
            'is_recurring' => $this->is_recurring,
            'parent_session_id' => $this->parent_session_id,
            'sequence_number' => $this->sequence_number,
            'instructor_notes' => $this->instructor_notes,
            'admin_notes' => $this->admin_notes,
            'student_instructions' => $this->student_instructions,
            'cancellation_reason' => $this->cancellation_reason,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed attributes
            'formatted_date' => $this->formatted_date,
            'formatted_time' => $this->formatted_time,
            'duration_in_minutes' => $this->duration_in_minutes,
            'status_badge_color' => $this->status_badge_color,
            'can_take_attendance' => $this->canTakeAttendance(),
            'is_today' => $this->isToday(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),

            // Relationships
            'course_offering' => $this->whenLoaded('courseOffering', function () {
                return [
                    'id' => $this->courseOffering->id,
                    'curriculum_unit' => $this->courseOffering->curriculumUnit ? [
                        'id' => $this->courseOffering->curriculumUnit->id,
                        'unit' => $this->courseOffering->curriculumUnit->unit ? [
                            'id' => $this->courseOffering->curriculumUnit->unit->id,
                            'code' => $this->courseOffering->curriculumUnit->unit->code,
                            'name' => $this->courseOffering->curriculumUnit->unit->name,
                            'credit_points' => $this->courseOffering->curriculumUnit->unit->credit_points,
                        ] : null,
                    ] : null,
                ];
            }),

            'instructor' => $this->whenLoaded('instructor', function () {
                return [
                    'id' => $this->instructor->id,
                    'name' => $this->instructor->name,
                    'email' => $this->instructor->email,
                ];
            }),

            'attendances' => $this->whenLoaded('attendances', function () {
                return $this->attendances->map(function ($attendance) {
                    return [
                        'id' => $attendance->id,
                        'student_code' => $attendance->student_code,
                        'status' => $attendance->status,
                        'status_badge_color' => $attendance->status_badge_color,
                        'check_in_time' => $attendance->check_in_time?->format('H:i'),
                        'check_out_time' => $attendance->check_out_time?->format('H:i'),
                        'minutes_late' => $attendance->minutes_late,
                        'minutes_present' => $attendance->minutes_present,
                        'recording_method' => $attendance->recording_method,
                        'participation_level' => $attendance->participation_level,
                        'participation_score' => $attendance->participation_score,
                        'is_verified' => $attendance->is_verified,
                        'student' => $attendance->student ? [
                            'id' => $attendance->student->id,
                            'student_code' => $attendance->student->student_code,
                            'user' => $attendance->student->user ? [
                                'id' => $attendance->student->user->id,
                                'name' => $attendance->student->user->name,
                                'email' => $attendance->student->user->email,
                            ] : null,
                        ] : null,
                    ];
                });
            }),

            'attendance_stats' => $this->when(
                $this->relationLoaded('attendances') || isset($this->attendance_stats),
                $this->attendance_stats ?? $this->attendanceStats
            ),
        ];
    }
}
