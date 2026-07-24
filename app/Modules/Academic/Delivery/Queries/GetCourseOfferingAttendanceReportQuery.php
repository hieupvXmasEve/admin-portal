<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseRosterReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

final class GetCourseOfferingAttendanceReportQuery
{
    public function __construct(
        private readonly CourseRosterReader $rosters,
        private readonly StudentReferenceReader $students,
    ) {}

    /**
     * Return the operational-presence report used by the staff attendance grid.
     *
     * `attendance_percentage` remains as a compatibility alias for existing
     * templates and exports. New consumers must use `operational_presence_rate`.
     *
     * @return array<string, mixed>
     */
    public function handle(CourseOffering $courseOffering): array
    {
        $courseOffering->load([
            'semester',
            'unit',
            'lecture',
            'classSessions' => static fn ($query) => $query
                ->orderBy('session_date', 'asc')
                ->orderBy('start_time', 'asc'),
            'classSessions.attendances',
        ]);

        $studentIds = $this->rosters->activeStudentIds($courseOffering->id);
        $studentReferences = collect($this->students->findMany($studentIds))
            ->sortBy('studentCode');
        $sessions = $courseOffering->classSessions;
        $totalSessions = $sessions->count();
        $attendanceThreshold = (float) ($courseOffering->syllabusTemplate?->min_attendance_threshold ?? 80.00);
        $allowedAbsences = (int) floor($totalSessions * ((100 - $attendanceThreshold) / 100));

        $attendanceGrid = [];
        foreach ($studentReferences as $student) {
            $totalAbsences = 0;
            $totalPresent = 0;
            $totalLate = 0;
            $studentSessions = [];

            foreach ($sessions as $session) {
                $attendance = $session->attendances->firstWhere('student_id', $student->id);
                $status = $attendance?->status ?? 'not_recorded';

                if ($status === 'absent') {
                    $totalAbsences++;
                } elseif ($status === 'present') {
                    $totalPresent++;
                } elseif ($status === 'late') {
                    $totalLate++;
                }

                $studentSessions[] = [
                    'session_id' => $session->id,
                    'session_number' => $session->sequence_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'status' => $status,
                    'check_in_time' => $attendance?->check_in_time?->format('H:i'),
                    'minutes_late' => $attendance?->minutes_late,
                ];
            }

            $operationalPresenceRate = $totalSessions > 0
                ? round((($totalPresent + $totalLate) / $totalSessions) * 100, 2)
                : 0.0;

            $attendanceGrid[] = [
                'student_id' => $student->studentCode,
                'full_name' => $student->fullName,
                'email' => $student->email,
                'sessions' => $studentSessions,
                'total_present' => $totalPresent,
                'total_absences' => $totalAbsences,
                'total_late' => $totalLate,
                'operational_presence_rate' => $operationalPresenceRate,
                'attendance_percentage' => $operationalPresenceRate,
                'meets_attendance_requirement' => $operationalPresenceRate >= $attendanceThreshold,
                'allowed_absences' => $allowedAbsences,
                'absences_remaining' => max(0, $allowedAbsences - $totalAbsences),
            ];
        }

        return [
            'course_offering' => $courseOffering,
            'statistics' => [
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'section_code' => $courseOffering->section_code,
                'semester' => $courseOffering->semester->name,
                'instructor_name' => $courseOffering->lecture ? trim($courseOffering->lecture->first_name.' '.$courseOffering->lecture->last_name) : null,
                'total_students' => $studentReferences->count(),
                'total_sessions' => $totalSessions,
                'allowed_absences' => $allowedAbsences,
                'students_absent_exceeded' => collect($attendanceGrid)->where('meets_attendance_requirement', false)->count(),
                'attendance_metric' => 'operational_presence_rate',
            ],
            'sessions' => $sessions->map(static fn ($session): array => [
                'id' => $session->id,
                'session_number' => $session->sequence_number,
                'session_date' => $session->session_date->format('Y-m-d'),
                'session_title' => $session->session_title,
                'session_time_start' => $session->start_time?->format('H:i'),
                'session_time_end' => $session->end_time?->format('H:i'),
            ]),
            'attendance_grid' => $attendanceGrid,
        ];
    }
}
