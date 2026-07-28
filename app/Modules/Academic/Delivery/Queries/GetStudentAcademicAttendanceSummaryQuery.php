<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use Illuminate\Support\Facades\DB;

final class GetStudentAcademicAttendanceSummaryQuery
{
    public function execute(int $studentId): array
    {
        // Get attendance records with related data
        // Fixed: Joined directly to units instead of via curriculum_units
        $attendanceData = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->join('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->leftJoin('course_registrations', function ($join) use ($studentId) {
                $join->on('course_registrations.course_offering_id', '=', 'course_offerings.id')
                    ->on('course_registrations.semester_id', '=', 'course_offerings.semester_id')
                    ->where('course_registrations.student_id', '=', $studentId)
                    ->whereNull('course_registrations.deleted_at');
            })
            ->where('attendances.student_id', $studentId)
            ->select([
                'units.id as unit_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.id as semester_id',
                'semesters.name as semester_name',
                'course_offerings.id as course_offering_id',
                'course_offerings.section_code',
                'course_registrations.attempt_number',
                'course_registrations.is_retake',
                'attendances.status',
                'attendances.check_in_time',
                'attendances.minutes_late',
                'class_sessions.id as session_id',
                'class_sessions.session_date',
                'class_sessions.start_time',
                'class_sessions.end_time',
            ])
            ->orderBy('class_sessions.session_date', 'desc')
            ->get()
            ->groupBy('course_offering_id');

        $attendanceSummary = $attendanceData->map(function ($offeringAttendance, $courseOfferingId) {
            $totalSessions = $offeringAttendance->count();
            $presentCount = $offeringAttendance->where('status', 'present')->count();
            $lateCount = $offeringAttendance->where('status', 'late')->count();
            $absentCount = $offeringAttendance->where('status', 'absent')->count();
            $excusedCount = $offeringAttendance->where('status', 'excused')->count();

            $attendedCount = $presentCount + $lateCount; // Late is still considered attended
            $attendancePercentage = $totalSessions > 0 ? ($attendedCount / $totalSessions) * 100 : 0;

            $firstRecord = $offeringAttendance->first();
            $attemptNumber = $firstRecord->attempt_number !== null ? (int) $firstRecord->attempt_number : null;
            $isRetake = (bool) $firstRecord->is_retake;

            return [
                'unit_id' => (int) $firstRecord->unit_id,
                'unit_name' => $firstRecord->unit_name,
                'unit_code' => $firstRecord->unit_code,
                'semester_id' => (int) $firstRecord->semester_id,
                'semester' => $firstRecord->semester_name,
                'course_offering_id' => (int) $courseOfferingId,
                'section_code' => $firstRecord->section_code,
                'attempt_number' => $attemptNumber,
                'is_retake' => $isRetake,
                'attempt_label' => $this->getAttemptLabel($attemptNumber, $isRetake),
                'total_sessions' => $totalSessions,
                'attended_count' => $attendedCount,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'excused_count' => $excusedCount,
                'attendance_percentage' => round($attendancePercentage, 2),
                'attendance_status' => $this->getAttendanceStatus($attendancePercentage),
                'sessions' => $offeringAttendance->map(function ($session) {
                    return [
                        'session_id' => $session->session_id,
                        'session_date' => $session->session_date,
                        'start_time' => $session->start_time,
                        'end_time' => $session->end_time,
                        'status' => $session->status,
                        'check_in_time' => $session->check_in_time,
                        'minutes_late' => $session->minutes_late,
                    ];
                })->values(),
            ];
        })->values();

        $totalSessions = $attendanceSummary->sum('total_sessions');
        $totalAttended = $attendanceSummary->sum('attended_count');

        return [
            'data' => $attendanceSummary,
            'summary' => [
                'total_units' => $attendanceSummary->pluck('unit_id')->unique()->count(),
                'total_attempts' => $attendanceSummary->count(),
                'total_sessions' => $totalSessions,
                'total_attended' => $totalAttended,
                'total_absent' => $attendanceSummary->sum('absent_count'),
                'overall_percentage' => $totalSessions > 0
                    ? round(($totalAttended / $totalSessions) * 100, 2)
                    : 0,
                'units_at_risk' => $attendanceSummary->where('attendance_percentage', '<', 80)->count(),
            ],
        ];
    }

    private function getAttemptLabel(?int $attemptNumber, bool $isRetake): string
    {
        if ($attemptNumber !== null) {
            return $isRetake ? "Retake attempt {$attemptNumber}" : "Attempt {$attemptNumber}";
        }

        return $isRetake ? 'Retake' : 'Attempt';
    }

    private function getAttendanceStatus(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'excellent';
        }
        if ($percentage >= 80) {
            return 'good';
        }
        if ($percentage >= 70) {
            return 'warning';
        }

        return 'critical';
    }
}
