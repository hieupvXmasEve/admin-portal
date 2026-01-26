<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class GetStudentAttendanceQuery
{
    public function execute(Student $student): array
    {
        // Get attendance records with related data
        // Fixed: Joined directly to units instead of via curriculum_units
        $attendanceData = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->join('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->where('attendances.student_id', $student->id)
            ->select([
                'units.id as unit_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.name as semester_name',
                'course_offerings.id as course_offering_id',
                'attendances.status',
                'attendances.check_in_time',
                'attendances.minutes_late',
                'class_sessions.session_date',
                'class_sessions.start_time',
                'class_sessions.end_time',
            ])
            ->orderBy('class_sessions.session_date', 'desc')
            ->get()
            ->groupBy('unit_id');

        $attendanceSummary = $attendanceData->map(function ($unitAttendance, $unitId) {
            $totalSessions = $unitAttendance->count();
            $presentCount = $unitAttendance->where('status', 'present')->count();
            $lateCount = $unitAttendance->where('status', 'late')->count();
            $absentCount = $unitAttendance->where('status', 'absent')->count();
            $excusedCount = $unitAttendance->where('status', 'excused')->count();

            $attendedCount = $presentCount + $lateCount; // Late is still considered attended
            $attendancePercentage = $totalSessions > 0 ? ($attendedCount / $totalSessions) * 100 : 0;

            $firstRecord = $unitAttendance->first();

            return [
                'unit_id' => $unitId,
                'unit_name' => $firstRecord->unit_name,
                'unit_code' => $firstRecord->unit_code,
                'semester' => $firstRecord->semester_name,
                'course_offering_id' => $firstRecord->course_offering_id,
                'total_sessions' => $totalSessions,
                'attended_count' => $attendedCount,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'excused_count' => $excusedCount,
                'attendance_percentage' => round($attendancePercentage, 2),
                'attendance_status' => $this->getAttendanceStatus($attendancePercentage),
                'sessions' => $unitAttendance->map(function ($session) {
                    return [
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

        return [
            'data' => $attendanceSummary,
            'summary' => [
                'total_units' => $attendanceSummary->count(),
                'total_sessions' => $attendanceSummary->sum('total_sessions'),
                'total_attended' => $attendanceSummary->sum('attended_count'),
                'total_absent' => $attendanceSummary->sum('absent_count'),
                'overall_percentage' => $attendanceSummary->count() > 0
                    ? round($attendanceSummary->avg('attendance_percentage'), 2)
                    : 0,
                'units_at_risk' => $attendanceSummary->where('attendance_percentage', '<', 80)->count(),
            ],
        ];
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
