<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\Attendance;

final class GetStudentCourseOperationalAttendanceQuery
{
    /** @return array{total: int, attended: int, operational_presence_rate: float, attendance_percentage: float, last_attendance: string|null} */
    public function handle(int $studentId, int $courseOfferingId): array
    {
        $attendances = Attendance::query()
            ->where('student_id', $studentId)
            ->whereHas('classSession', static fn ($sessions) => $sessions->where('course_offering_id', $courseOfferingId))
            ->get();
        $totalSessions = $attendances->pluck('class_session_id')->unique()->count();
        $attendedSessions = $attendances
            ->whereIn('status', ['present', 'late'])
            ->pluck('class_session_id')
            ->unique()
            ->count();
        $operationalPresenceRate = $totalSessions > 0
            ? round(($attendedSessions / $totalSessions) * 100, 1)
            : 0.0;

        return [
            'total' => $totalSessions,
            'attended' => $attendedSessions,
            'operational_presence_rate' => $operationalPresenceRate,
            'attendance_percentage' => $operationalPresenceRate,
            'last_attendance' => $attendances->sortByDesc('created_at')->first()?->created_at?->format('Y-m-d'),
        ];
    }
}
