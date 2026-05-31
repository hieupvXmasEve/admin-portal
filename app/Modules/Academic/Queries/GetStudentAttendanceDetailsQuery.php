<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use Illuminate\Support\Facades\DB;

class GetStudentAttendanceDetailsQuery
{
    public function execute(int $studentId, int $unitId, ?int $semesterId = null, ?int $courseOfferingId = null): array
    {
        // Fixed: Joined directly to units instead of via curriculum_units
        $query = DB::table('attendances')
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
            ->where('units.id', $unitId);

        if ($semesterId) {
            $query->where('course_offerings.semester_id', $semesterId);
        }

        if ($courseOfferingId) {
            $query->where('course_offerings.id', $courseOfferingId);
        }

        $attendanceRecords = $query->select([
            'class_sessions.id as session_id',
            'class_sessions.session_date',
            'class_sessions.start_time',
            'class_sessions.end_time',
            'class_sessions.session_type',
            'attendances.status',
            'attendances.check_in_time',
            'attendances.check_out_time',
            'attendances.minutes_late',
            'attendances.excuse_reason',
            'units.name as unit_name',
            'units.code as unit_code',
            'semesters.name as semester_name',
            'course_offerings.id as course_offering_id',
            'course_offerings.section_code',
            'course_registrations.attempt_number',
            'course_registrations.is_retake',
        ])
            ->orderBy('class_sessions.session_date', 'desc')
            ->get();

        $summary = [
            'total_sessions' => $attendanceRecords->count(),
            'present' => $attendanceRecords->where('status', 'present')->count(),
            'late' => $attendanceRecords->where('status', 'late')->count(),
            'absent' => $attendanceRecords->where('status', 'absent')->count(),
            'excused' => $attendanceRecords->where('status', 'excused')->count(),
        ];

        $summary['attended'] = $summary['present'] + $summary['late'];
        $summary['attendance_percentage'] = $summary['total_sessions'] > 0
            ? round(($summary['attended'] / $summary['total_sessions']) * 100, 2)
            : 0;

        // Calculate status
        $percentage = $summary['attendance_percentage'];
        $status = 'critical';
        if ($percentage >= 90) {
            $status = 'excellent';
        } elseif ($percentage >= 80) {
            $status = 'good';
        } elseif ($percentage >= 70) {
            $status = 'warning';
        }

        $summary['attendance_status'] = $status;

        return [
            'unit_info' => [
                'name' => $attendanceRecords->first()->unit_name ?? 'N/A',
                'code' => $attendanceRecords->first()->unit_code ?? 'N/A',
                'semester' => $attendanceRecords->first()->semester_name ?? 'N/A',
                'course_offering_id' => $attendanceRecords->first()->course_offering_id ?? null,
                'section_code' => $attendanceRecords->first()->section_code ?? null,
                'attempt_number' => $attendanceRecords->first()->attempt_number ?? null,
                'is_retake' => (bool) ($attendanceRecords->first()->is_retake ?? false),
            ],
            'summary' => $summary,
            'sessions' => $attendanceRecords->map(function ($record) {
                return [
                    'session_id' => $record->session_id,
                    'session_date' => $record->session_date,
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
                    'session_type' => $record->session_type,
                    'status' => $record->status,
                    'check_in_time' => $record->check_in_time,
                    'check_out_time' => $record->check_out_time,
                    'minutes_late' => $record->minutes_late,
                    'excuse_reason' => $record->excuse_reason,
                ];
            }),
            // Flatten properties for dialog usage if needed, matching the Vue component expectations
            'unit_name' => $attendanceRecords->first()->unit_name ?? 'N/A',
            'unit_code' => $attendanceRecords->first()->unit_code ?? 'N/A',
            'semester' => $attendanceRecords->first()->semester_name ?? 'N/A',
            'course_offering_id' => $attendanceRecords->first()->course_offering_id ?? null,
            'section_code' => $attendanceRecords->first()->section_code ?? null,
            'attempt_number' => $attendanceRecords->first()->attempt_number ?? null,
            'is_retake' => (bool) ($attendanceRecords->first()->is_retake ?? false),
            'total_sessions' => $summary['total_sessions'],
            'attendance_percentage' => $summary['attendance_percentage'],
            'attendance_status' => $status,
        ];
    }
}
