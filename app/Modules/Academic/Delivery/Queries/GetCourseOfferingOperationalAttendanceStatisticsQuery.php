<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseRosterReader;

final class GetCourseOfferingOperationalAttendanceStatisticsQuery
{
    public function __construct(private readonly CourseRosterReader $rosters) {}

    /** @return array<string, int|float> */
    public function handle(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing('classSessions.attendances');

        $sessions = $courseOffering->classSessions;
        $activeStudentIds = $this->rosters->activeStudentIds($courseOffering->id);
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $sessionsWithAttendance = $sessions
            ->filter(static function ($session) use ($activeStudentIds): bool {
                if (! $session->relationLoaded('attendances')) {
                    return $session->attendance_marked;
                }

                return $session->attendances->whereIn('student_id', $activeStudentIds)->isNotEmpty();
            })
            ->count();

        $expectedActiveAttendances = $totalSessions * count($activeStudentIds);
        $attendedRecords = $sessions
            ->flatMap(static fn ($session) => $session->attendances)
            ->whereIn('student_id', $activeStudentIds)
            ->whereIn('status', ['present', 'late'])
            ->count();
        $operationalPresenceRate = $expectedActiveAttendances > 0
            ? round(($attendedRecords / $expectedActiveAttendances) * 100, 1)
            : 0.0;

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'sessions_with_attendance' => $sessionsWithAttendance,
            'pending_attendance' => $completedSessions - $sessionsWithAttendance,
            'operational_presence_rate' => $operationalPresenceRate,
            'overall_attendance_rate' => $operationalPresenceRate,
        ];
    }
}
