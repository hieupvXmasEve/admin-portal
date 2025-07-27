<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_code' => $this->section_code,
            'delivery_mode' => $this->delivery_mode,
            'location' => $this->location,
            'max_capacity' => $this->max_capacity,
            'current_enrollment' => $this->current_enrollment,
            'enrollment_status' => $this->enrollment_status,
            'schedule_days' => $this->schedule_days,
            'schedule_time_start' => $this->schedule_time_start?->format('H:i'),
            'schedule_time_end' => $this->schedule_time_end?->format('H:i'),

            // Curriculum Unit Information
            'curriculum_unit' => $this->whenLoaded('curriculumUnit', function () {
                return [
                    'id' => $this->curriculumUnit->id,
                    'code' => $this->curriculumUnit->unit->code,
                    'name' => $this->curriculumUnit->unit->name,
                    'credit_hours' => $this->curriculumUnit->credit_hours,
                    'description' => $this->curriculumUnit->description,
                ];
            }),

            // Semester Information
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'name' => $this->semester->name,
                    'code' => $this->semester->code,
                    'start_date' => $this->semester->start_date?->format('Y-m-d'),
                    'end_date' => $this->semester->end_date?->format('Y-m-d'),
                    'is_active' => $this->semester->is_active,
                ];
            }),

            // Enrollment Statistics
            'enrollment_stats' => [
                'enrolled_count' => $this->whenLoaded('courseRegistrations', function () {
                    return $this->courseRegistrations->where('registration_status', 'enrolled')->count();
                }),
                'capacity_utilization' => $this->max_capacity > 0
                    ? round(($this->current_enrollment / $this->max_capacity) * 100, 1)
                    : 0,
                'available_spots' => max(0, $this->max_capacity - $this->current_enrollment),
                'is_full' => $this->current_enrollment >= $this->max_capacity,
            ],

            // Session Statistics
            'session_stats' => $this->whenLoaded('classSessions', function () {
                $sessions = $this->classSessions;
                $totalSessions = $sessions->count();
                $completedSessions = $sessions->where('status', 'completed')->count();
                $upcomingSessions = $sessions->where('session_date', '>=', now())->count();
                $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();

                return [
                    'total_sessions' => $totalSessions,
                    'completed_sessions' => $completedSessions,
                    'upcoming_sessions' => $upcomingSessions,
                    'sessions_with_attendance' => $sessionsWithAttendance,
                    'pending_attendance' => $completedSessions - $sessionsWithAttendance,
                ];
            }),

            // Recent Sessions (limited to 3 most recent)
            'recent_sessions' => $this->whenLoaded('classSessions', function () {
                return $this->classSessions->sortByDesc('session_date')->take(3)->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'title' => $session->session_title,
                        'date' => $session->session_date->format('Y-m-d'),
                        'start_time' => $session->start_time->format('H:i'),
                        'status' => $session->status,
                        'attendance_marked' => $session->attendance_marked,
                    ];
                })->values();
            }),

            // Quick Actions
            'quick_actions' => [
                'can_mark_attendance' => $this->hasSessionsNeedingAttendance(),
                'has_upcoming_sessions' => $this->hasUpcomingSessions(),
                'needs_attention' => $this->needsAttention(),
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Check if course has sessions needing attendance marking
     */
    protected function hasSessionsNeedingAttendance(): bool
    {
        if (!$this->relationLoaded('classSessions')) {
            return false;
        }

        return $this->classSessions
            ->where('status', 'completed')
            ->where('attendance_marked', false)
            ->where('session_date', '<', now()->subHours(2))
            ->isNotEmpty();
    }

    /**
     * Check if course has upcoming sessions
     */
    protected function hasUpcomingSessions(): bool
    {
        if (!$this->relationLoaded('classSessions')) {
            return false;
        }

        return $this->classSessions
            ->where('session_date', '>=', now())
            ->where('status', 'scheduled')
            ->isNotEmpty();
    }

    /**
     * Check if course needs attention
     */
    protected function needsAttention(): bool
    {
        // Course needs attention if:
        // 1. Has sessions with unmarked attendance
        // 2. Low enrollment (less than 50% capacity)
        // 3. Overenrolled

        $hasUnmarkedAttendance = $this->hasSessionsNeedingAttendance();
        $lowEnrollment = $this->max_capacity > 0 && ($this->current_enrollment / $this->max_capacity) < 0.5;
        $overenrolled = $this->current_enrollment > $this->max_capacity;

        return $hasUnmarkedAttendance || $lowEnrollment || $overenrolled;
    }
}
