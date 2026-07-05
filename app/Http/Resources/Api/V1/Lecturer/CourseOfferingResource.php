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
        $rosterStats = $this->rosterEnrollmentStats();
        $visibleRosterEnrollment = $rosterStats['visible_roster_count'];

        return [
            'id' => $this->id,
            'section_code' => $this->section_code,
            'delivery_mode' => $this->delivery_mode,
            'location' => $this->location,
            'max_capacity' => $this->max_capacity,
            'current_enrollment' => $visibleRosterEnrollment,
            'enrollment_status' => $this->enrollment_status,
            'schedule_days' => $this->schedule_days,
            'schedule_time_start' => $this->schedule_time_start?->format('H:i'),
            'schedule_time_end' => $this->schedule_time_end?->format('H:i'),

            // Unit Information
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'code' => $this->unit->code,
                    'name' => $this->unit->name,
                    'credit_points' => $this->unit->credit_points,
                    'description' => $this->unit->description,
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
                'enrolled_count' => $visibleRosterEnrollment,
                'active_roster_count' => $rosterStats['active_roster_count'],
                'visible_roster_count' => $rosterStats['visible_roster_count'],
                'inactive_roster_count' => $rosterStats['inactive_roster_count'],
                'completed_count' => $rosterStats['completed_count'],
                'deferred_count' => $rosterStats['deferred_count'],
                'capacity_utilization' => $this->max_capacity > 0
                    ? round(($visibleRosterEnrollment / $this->max_capacity) * 100, 1)
                    : 0,
                'available_spots' => max(0, $this->max_capacity - $visibleRosterEnrollment),
                'active_available_spots' => max(0, $this->max_capacity - $rosterStats['active_roster_count']),
                'is_full' => $visibleRosterEnrollment >= $this->max_capacity,
            ],

            // Session Statistics
            'session_stats' => $this->whenLoaded('classSessions', function () {
                $sessions = $this->classSessions;
                $activeStudentIds = $this->activeClassRosterStudentIds();
                $totalSessions = $sessions->count();
                $completedSessions = $sessions->where('status', 'completed')->count();
                $upcomingSessions = $sessions->where('session_date', '>=', now())->count();
                $sessionsWithAttendance = $sessions
                    ->filter(fn ($session) => $this->sessionHasActiveRosterAttendance($session, $activeStudentIds))
                    ->count();

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
                        'attendance_marked' => $this->sessionHasActiveRosterAttendance(
                            $session,
                            $this->activeClassRosterStudentIds()
                        ),
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
        if (! $this->relationLoaded('classSessions')) {
            return false;
        }

        return $this->classSessions
            ->where('status', 'completed')
            ->filter(fn ($session) => ! $this->sessionHasActiveRosterAttendance(
                $session,
                $this->activeClassRosterStudentIds()
            ))
            ->where('session_date', '<', now()->subHours(2))
            ->isNotEmpty();
    }

    /**
     * Check if course has upcoming sessions
     */
    protected function hasUpcomingSessions(): bool
    {
        if (! $this->relationLoaded('classSessions')) {
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
        $visibleRosterEnrollment = $this->rosterEnrollmentStats()['visible_roster_count'];
        $lowEnrollment = $this->max_capacity > 0 && ($visibleRosterEnrollment / $this->max_capacity) < 0.5;
        $overenrolled = $visibleRosterEnrollment > $this->max_capacity;

        return $hasUnmarkedAttendance || $lowEnrollment || $overenrolled;
    }

    protected function rosterEnrollmentStats(): array
    {
        $registrations = $this->relationLoaded('classRosterRegistrations')
            ? $this->classRosterRegistrations
            : $this->classRosterRegistrations()->with('student')->get();
        $activeRosterCount = $registrations
            ->filter(fn ($registration) => $registration->isClassRosterActive())
            ->count();
        $visibleRosterCount = $registrations->count();

        return [
            'active_roster_count' => $activeRosterCount,
            'visible_roster_count' => $visibleRosterCount,
            'inactive_roster_count' => $visibleRosterCount - $activeRosterCount,
            'completed_count' => $registrations
                ->where('registration_status', 'completed')
                ->count(),
            'deferred_count' => $registrations
                ->filter(fn ($registration) => in_array($registration->classRosterStatus(), ['defer', 'deferred'], true))
                ->count(),
        ];
    }

    protected function activeRosterEnrollmentCount(): int
    {
        return $this->activeClassRosterEnrollmentCount();
    }

    protected function sessionHasActiveRosterAttendance($session, $activeStudentIds): bool
    {
        if (! $session->relationLoaded('attendances')) {
            return $session->attendance_marked;
        }

        return $session->attendances
            ->whereIn('student_id', $activeStudentIds)
            ->isNotEmpty();
    }
}
