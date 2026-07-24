<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Modules\Academic\Delivery\Support\LecturerAttendanceService;

final class GetLecturerAttendanceSummaryQuery
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(int $lecturerId, array $filters): array
    {
        $sessions = app(LecturerAttendanceService::class)->getAttendanceSessions($lecturerId, $filters, 50);
        $totalSessions = $sessions->total();
        $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();
        $pendingSessions = $sessions->where('attendance_marked', false)->where('session_date', '<', now()->subHours(2))->count();

        return [
            'total_sessions' => $totalSessions,
            'sessions_with_attendance' => $sessionsWithAttendance,
            'pending_sessions' => $pendingSessions,
            'completion_rate' => $totalSessions > 0 ? round(($sessionsWithAttendance / $totalSessions) * 100, 1) : 0,
            'recent_sessions' => $sessions->take(5)->map(static fn ($session): array => [
                'id' => $session->id,
                'course' => $session->courseOffering->unit_code,
                'date' => $session->session_date->format('Y-m-d'),
                'attendance_marked' => $session->attendance_marked,
                'attendance_percentage' => $session->attendance_percentage,
            ]),
        ];
    }
}
