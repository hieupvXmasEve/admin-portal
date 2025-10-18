<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseOffering;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CourseStatisticsService
{
    public function getStatistics(array $filters): LengthAwarePaginator
    {
        $campusId = session('current_campus_id');
        $perPage = $filters['per_page'] ?? 15;
        $search = $filters['search'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;
        $sortBy = $filters['sort'] ?? 'course_code';
        $direction = $filters['direction'] ?? 'asc';

        $query = CourseOffering::query()
            ->with([
                'semester:id,name,code',
                'unit:id,code,name,credit_points',
                'lecture:id,first_name,last_name',
            ])
            ->where('course_offerings.campus_id', $campusId)
            ->where('course_offerings.is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
                    ->orWhere('course_offerings.section_code', 'like', "%{$search}%");
            });
        }

        $query->leftJoin('units', 'course_offerings.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->leftJoin('academic_records', 'course_offerings.id', '=', 'academic_records.course_offering_id');

        if ($semesterId) {
            $query->where('course_offerings.semester_id', $semesterId);
        }

        $query
            ->select([
                'course_offerings.id',
                'course_offerings.semester_id',
                'course_offerings.unit_id',
                'course_offerings.lecture_id',
                'course_offerings.campus_id',
                'course_offerings.section_code',
                'course_offerings.max_capacity',
                'course_offerings.current_enrollment',
                'course_offerings.delivery_mode',
                DB::raw('COUNT(DISTINCT academic_records.student_id) as total_students'),
                DB::raw('SUM(CASE WHEN academic_records.meets_attendance_requirement = 0 THEN 1 ELSE 0 END) as students_absent_exceeded'),
                DB::raw('AVG(academic_records.attendance_percentage) as average_attendance'),
                DB::raw('AVG(academic_records.final_percentage) as average_grade'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "A+" THEN 1 END) as grade_a_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "A" THEN 1 END) as grade_a'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "B+" THEN 1 END) as grade_b_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "B" THEN 1 END) as grade_b'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "C+" THEN 1 END) as grade_c_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "C" THEN 1 END) as grade_c'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "D+" THEN 1 END) as grade_d_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "D" THEN 1 END) as grade_d'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "F" THEN 1 END) as grade_f'),
                DB::raw('SUM(CASE WHEN academic_records.completion_status = "completed" AND academic_records.grade_points > 0 THEN 1 ELSE 0 END) as students_passed'),
            ])
            ->groupBy(
                'course_offerings.id',
                'course_offerings.semester_id',
                'course_offerings.unit_id',
                'course_offerings.lecture_id',
                'course_offerings.campus_id',
                'course_offerings.section_code',
                'course_offerings.max_capacity',
                'course_offerings.current_enrollment',
                'course_offerings.delivery_mode'
            );

        $sortColumn = match ($sortBy) {
            'course_name' => 'units.name',
            'semester' => 'semesters.name',
            'total_students' => 'total_students',
            'average_attendance' => 'average_attendance',
            'average_grade' => 'average_grade',
            default => 'units.code',
        };

        $query->orderBy($sortColumn, $direction);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getCourseDetail(int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with([
            'semester',
            'unit',
            'lecture',
            'academicRecords' => function ($query) {
                $query->with('student:id,student_id,full_name,email');
            },
        ])->findOrFail($courseOfferingId);

        $statistics = [
            'course_offering' => $courseOffering,
            'total_students' => $courseOffering->academicRecords->count(),
            'students_absent_exceeded' => $courseOffering->academicRecords->where('meets_attendance_requirement', false)->count(),
            'average_attendance' => round($courseOffering->academicRecords->avg('attendance_percentage') ?? 0, 2),
            'average_grade' => round($courseOffering->academicRecords->avg('final_percentage') ?? 0, 2),
            'grade_distribution' => $this->calculateGradeDistribution($courseOffering->academicRecords),
            'pass_rate' => $this->calculatePassRate($courseOffering->academicRecords),
            'students' => $courseOffering->academicRecords,
        ];

        return $statistics;
    }

    private function calculateGradeDistribution($academicRecords): array
    {
        $distribution = [
            'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0,
            'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'F' => 0,
        ];

        foreach ($academicRecords as $record) {
            if ($record->final_letter_grade && isset($distribution[$record->final_letter_grade])) {
                $distribution[$record->final_letter_grade]++;
            }
        }

        return $distribution;
    }

    private function calculatePassRate($academicRecords): float
    {
        $total = $academicRecords->count();
        if ($total === 0) {
            return 0.0;
        }

        $passed = $academicRecords->filter(function ($record) {
            return $record->completion_status === 'completed' && $record->grade_points > 0;
        })->count();

        return round(($passed / $total) * 100, 2);
    }

    public function getAttendanceGrid(int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with([
            'semester',
            'unit',
            'lecture',
            'campus',
            'classSessions' => function ($query) {
                $query->orderBy('session_date', 'asc')
                    ->orderBy('start_time', 'asc');
            },
            'classSessions.attendances.student',
        ])->findOrFail($courseOfferingId);

        $students = $courseOffering->courseRegistrations()
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy('student_id');

        $sessions = $courseOffering->classSessions;

        $attendanceGrid = [];
        foreach ($students as $student) {
            $studentData = [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'sessions' => [],
            ];

            foreach ($sessions as $session) {
                $attendance = $session->attendances->firstWhere('student_id', $student->id);

                $studentData['sessions'][] = [
                    'session_id' => $session->id,
                    'session_number' => $session->sequence_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'status' => $attendance?->status ?? 'not_recorded',
                    'check_in_time' => $attendance?->check_in_time?->format('H:i'),
                    'minutes_late' => $attendance?->minutes_late,
                ];
            }

            $academicRecord = $student->academicRecords()
                ->where('course_offering_id', $courseOfferingId)
                ->first();

            $studentData['total_present'] = $academicRecord?->total_present ?? 0;
            $studentData['total_absences'] = $academicRecord?->total_absences ?? 0;
            $studentData['total_late'] = $academicRecord?->total_late ?? 0;
            $studentData['attendance_percentage'] = $academicRecord?->attendance_percentage ?? 0;
            $studentData['meets_attendance_requirement'] = $academicRecord?->meets_attendance_requirement ?? true;

            $attendanceGrid[] = $studentData;
        }

        $statistics = [
            'course_code' => $courseOffering->unit->code,
            'course_name' => $courseOffering->unit->name,
            'section_code' => $courseOffering->section_code,
            'semester' => $courseOffering->semester->name,
            'instructor_name' => $courseOffering->lecture ? trim($courseOffering->lecture->first_name.' '.$courseOffering->lecture->last_name) : null,
            'total_students' => $students->count(),
            'total_sessions' => $sessions->count(),
            'students_absent_exceeded' => $students->filter(function ($student) use ($courseOfferingId) {
                $record = $student->academicRecords()->where('course_offering_id', $courseOfferingId)->first();

                return $record && ! $record->meets_attendance_requirement;
            })->count(),
        ];

        return [
            'course_offering' => $courseOffering,
            'statistics' => $statistics,
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_number' => $session->sequence_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'session_title' => $session->session_title,
                    'session_time_start' => $session->start_time?->format('H:i'),
                    'session_time_end' => $session->end_time?->format('H:i'),
                ];
            }),
            'attendance_grid' => $attendanceGrid,
        ];
    }
}
