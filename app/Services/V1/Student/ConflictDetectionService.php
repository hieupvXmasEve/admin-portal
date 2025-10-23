<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ConflictDetectionService
{
    /**
     * Detect schedule conflicts for a student with a new course offering
     */
    public function detectConflicts(Student $student, CourseOffering $newCourseOffering): Collection
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return collect();
        }

        // Get student's current registrations for the semester
        $currentRegistrations = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with(['courseOffering.classSessions'])
            ->get();

        // Get class sessions for the new course
        $newSessions = $newCourseOffering->classSessions;

        $conflicts = collect();

        foreach ($newSessions as $newSession) {
            foreach ($currentRegistrations as $registration) {
                foreach ($registration->courseOffering->classSessions as $existingSession) {
                    if ($this->sessionsConflict($newSession, $existingSession)) {
                        $conflicts->push([
                            'type' => 'schedule_conflict',
                            'new_session' => $this->formatSessionForConflict($newSession, $newCourseOffering),
                            'existing_session' => $this->formatSessionForConflict($existingSession, $registration->courseOffering),
                            'conflict_details' => $this->getConflictDetails($newSession, $existingSession),
                        ]);
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two class sessions conflict
     */
    protected function sessionsConflict(ClassSession $session1, ClassSession $session2): bool
    {
        // Different days don't conflict
        if ($session1->day_of_week !== $session2->day_of_week) {
            return false;
        }

        // Convert times to Carbon instances for comparison
        $start1 = Carbon::createFromTimeString($session1->start_time);
        $end1 = Carbon::createFromTimeString($session1->end_time);
        $start2 = Carbon::createFromTimeString($session2->start_time);
        $end2 = Carbon::createFromTimeString($session2->end_time);

        // Check for time overlap
        return $start1->lt($end2) && $start2->lt($end1);
    }

    /**
     * Get detailed conflict information
     */
    protected function getConflictDetails(ClassSession $session1, ClassSession $session2): array
    {
        $start1 = Carbon::createFromTimeString($session1->start_time);
        $end1 = Carbon::createFromTimeString($session1->end_time);
        $start2 = Carbon::createFromTimeString($session2->start_time);
        $end2 = Carbon::createFromTimeString($session2->end_time);

        // Calculate overlap period
        $overlapStart = $start1->gt($start2) ? $start1 : $start2;
        $overlapEnd = $end1->lt($end2) ? $end1 : $end2;

        return [
            'day' => $session1->day_of_week,
            'overlap_start' => $overlapStart->format('H:i'),
            'overlap_end' => $overlapEnd->format('H:i'),
            'overlap_duration_minutes' => $overlapStart->diffInMinutes($overlapEnd),
            'conflict_severity' => $this->calculateConflictSeverity($session1, $session2),
        ];
    }

    /**
     * Calculate conflict severity
     */
    protected function calculateConflictSeverity(ClassSession $session1, ClassSession $session2): string
    {
        $start1 = Carbon::createFromTimeString($session1->start_time);
        $end1 = Carbon::createFromTimeString($session1->end_time);
        $start2 = Carbon::createFromTimeString($session2->start_time);
        $end2 = Carbon::createFromTimeString($session2->end_time);

        // Calculate overlap percentage
        $session1Duration = $start1->diffInMinutes($end1);
        $session2Duration = $start2->diffInMinutes($end2);
        $overlapStart = $start1->gt($start2) ? $start1 : $start2;
        $overlapEnd = $end1->lt($end2) ? $end1 : $end2;
        $overlapDuration = $overlapStart->diffInMinutes($overlapEnd);

        $overlapPercentage1 = $session1Duration > 0 ? ($overlapDuration / $session1Duration) * 100 : 0;
        $overlapPercentage2 = $session2Duration > 0 ? ($overlapDuration / $session2Duration) * 100 : 0;
        $maxOverlapPercentage = max($overlapPercentage1, $overlapPercentage2);

        return match (true) {
            $maxOverlapPercentage >= 80 => 'critical',
            $maxOverlapPercentage >= 50 => 'major',
            $maxOverlapPercentage >= 20 => 'moderate',
            default => 'minor',
        };
    }

    /**
     * Format session for conflict display
     */
    protected function formatSessionForConflict(ClassSession $session, CourseOffering $courseOffering): array
    {
        return [
            'course_code' => $courseOffering->unit->code,
            'course_name' => $courseOffering->unit->name,
            'day_of_week' => $session->day_of_week,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'room' => [
                'code' => $session->room?->code,
                'name' => $session->room?->name,
                'building' => $session->room?->building,
            ],
            'session_type' => $session->session_type,
        ];
    }

    /**
     * Check for potential conflicts across multiple course offerings
     */
    public function detectMultipleCourseConflicts(Student $student, array $courseOfferingIds): array
    {
        $courseOfferings = CourseOffering::whereIn('id', $courseOfferingIds)
            ->with('classSessions')
            ->get();

        $conflicts = [];
        $allSessions = [];

        // Collect all sessions from the course offerings
        foreach ($courseOfferings as $offering) {
            foreach ($offering->classSessions as $session) {
                $allSessions[] = [
                    'session' => $session,
                    'offering' => $offering,
                ];
            }
        }

        // Check for conflicts between all combinations
        for ($i = 0; $i < count($allSessions); $i++) {
            for ($j = $i + 1; $j < count($allSessions); $j++) {
                $session1Data = $allSessions[$i];
                $session2Data = $allSessions[$j];

                if ($this->sessionsConflict($session1Data['session'], $session2Data['session'])) {
                    $conflicts[] = [
                        'type' => 'multiple_course_conflict',
                        'session1' => $this->formatSessionForConflict($session1Data['session'], $session1Data['offering']),
                        'session2' => $this->formatSessionForConflict($session2Data['session'], $session2Data['offering']),
                        'conflict_details' => $this->getConflictDetails($session1Data['session'], $session2Data['session']),
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get student's weekly schedule
     */
    public function getStudentSchedule(Student $student, ?int $semesterId = null): array
    {
        $semester = $semesterId
            ? Semester::find($semesterId)
            : Semester::where('is_active', true)->first();

        if (! $semester) {
            return [];
        }

        $registrations = $student->courseRegistrations()
            ->where('semester_id', $semester->id)
            ->where('registration_status', 'registered')
            ->with(['courseOffering.classSessions.room', 'courseOffering.unit', 'courseOffering.lecturer'])
            ->get();

        $schedule = [];
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($daysOfWeek as $day) {
            $schedule[$day] = [];
        }

        foreach ($registrations as $registration) {
            foreach ($registration->courseOffering->classSessions as $session) {
                $schedule[$session->day_of_week][] = [
                    'course_code' => $registration->courseOffering->unit->code,
                    'course_name' => $registration->courseOffering->unit->name,
                    'lecturer' => $registration->courseOffering->lecturer?->full_name,
                    'start_time' => $session->start_time,
                    'end_time' => $session->end_time,
                    'room' => [
                        'code' => $session->room?->code,
                        'name' => $session->room?->name,
                        'building' => $session->room?->building,
                    ],
                    'session_type' => $session->session_type,
                    'registration_id' => $registration->id,
                ];
            }
        }

        // Sort sessions by start time for each day
        foreach ($schedule as $day => $sessions) {
            usort($schedule[$day], function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });
        }

        return $schedule;
    }

    /**
     * Check for back-to-back classes (potential travel time conflicts)
     */
    public function detectTravelTimeConflicts(Student $student, CourseOffering $newCourseOffering, int $minimumBreakMinutes = 15): Collection
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return collect();
        }

        $schedule = $this->getStudentSchedule($student, $currentSemester->id);
        $newSessions = $newCourseOffering->classSessions;

        $travelConflicts = collect();

        foreach ($newSessions as $newSession) {
            $daySchedule = $schedule[$newSession->day_of_week] ?? [];

            foreach ($daySchedule as $existingSession) {
                $conflict = $this->checkTravelTimeConflict(
                    $newSession,
                    $existingSession,
                    $minimumBreakMinutes
                );

                if ($conflict) {
                    $travelConflicts->push($conflict);
                }
            }
        }

        return $travelConflicts;
    }

    /**
     * Check travel time conflict between two sessions
     */
    protected function checkTravelTimeConflict(ClassSession $newSession, array $existingSession, int $minimumBreakMinutes): ?array
    {
        $newStart = Carbon::createFromTimeString($newSession->start_time);
        $newEnd = Carbon::createFromTimeString($newSession->end_time);
        $existingStart = Carbon::createFromTimeString($existingSession['start_time']);
        $existingEnd = Carbon::createFromTimeString($existingSession['end_time']);

        // Check if new session ends just before existing session starts
        $breakAfterNew = $existingStart->diffInMinutes($newEnd, false);
        if ($breakAfterNew >= 0 && $breakAfterNew < $minimumBreakMinutes) {
            return [
                'type' => 'insufficient_break_time',
                'break_duration_minutes' => $breakAfterNew,
                'minimum_required_minutes' => $minimumBreakMinutes,
                'first_session' => $this->formatSessionForConflict($newSession, $newSession->courseOffering),
                'second_session' => $existingSession,
            ];
        }

        // Check if existing session ends just before new session starts
        $breakAfterExisting = $newStart->diffInMinutes($existingEnd, false);
        if ($breakAfterExisting >= 0 && $breakAfterExisting < $minimumBreakMinutes) {
            return [
                'type' => 'insufficient_break_time',
                'break_duration_minutes' => $breakAfterExisting,
                'minimum_required_minutes' => $minimumBreakMinutes,
                'first_session' => $existingSession,
                'second_session' => $this->formatSessionForConflict($newSession, $newSession->courseOffering),
            ];
        }

        return null;
    }
}
