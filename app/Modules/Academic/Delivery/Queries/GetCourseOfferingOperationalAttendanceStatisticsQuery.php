<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseRosterReader;
use Illuminate\Support\Facades\DB;

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

        $attendanceBaseQuery = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->whereIn('attendances.student_id', $activeStudentIds);
        $totalRecordedAttendances = (clone $attendanceBaseQuery)->count();
        $attendedRecords = (clone $attendanceBaseQuery)
            ->whereIn('attendances.status', ['present', 'late'])
            ->count();
        $operationalPresenceRate = $totalRecordedAttendances > 0
            ? round(($attendedRecords / $totalRecordedAttendances) * 100, 1)
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
