<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportStudentAttendanceReportQuery
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'student_code',
            'student_name',
            'student_email',
            'program_name',
            'campus_name',
            'intake',
            'semester_code',
            'semester_name',
            'unit_code',
            'unit_name',
            'credit_points',
            'section_code',
            'instructor_name',
            'attendance_percentage',
            'total_present',
            'total_absences',
            'total_late',
            'total_not_recorded',
            'total_class_sessions',
            'min_attendance_threshold',
            'meets_attendance_requirement',
            'final_percentage',
            'final_letter_grade',
            'grade_points',
            'min_grade_threshold',
            'grade_status',
            'completion_status',
            'is_passed',
            'failure_reason',
            'override_pass',
            'override_reason',
            'attempt_number',
            'is_repeat_course',
        ];
    }

    public function cursor(): LazyCollection
    {
        return $this->buildQuery()->cursor();
    }

    public function count(): int
    {
        return $this->buildQuery()->count();
    }

    /**
     * @return list<int|string|null>
     */
    public function formatRow(object $row): array
    {
        return [
            $row->student_code,
            $row->student_name,
            $row->student_email,
            $row->program_name,
            $row->campus_name,
            $row->intake,
            $row->semester_code,
            $row->semester_name,
            $row->unit_code,
            $row->unit_name,
            $row->credit_points,
            $row->section_code,
            $this->formatInstructorName($row->instructor_first_name, $row->instructor_last_name),
            $row->attendance_percentage,
            $row->total_present,
            $row->total_absences,
            $row->total_late,
            $row->total_not_recorded,
            $row->total_class_sessions,
            $row->min_attendance_threshold,
            $this->formatBoolean($row->meets_attendance_requirement),
            $row->final_percentage,
            $row->final_letter_grade,
            $row->grade_points,
            $row->min_grade_threshold,
            $row->grade_status,
            $row->completion_status,
            $this->formatPassFail($row->is_passed),
            $row->failure_reason,
            $this->formatBoolean($row->override_pass),
            $row->override_reason,
            $row->attempt_number,
            $this->formatBoolean($row->is_repeat_course),
        ];
    }

    private function buildQuery(): Builder
    {
        return DB::table('academic_records as ar')
            ->join('students as s', 'ar.student_id', '=', 's.id')
            ->join('course_offerings as co', 'ar.course_offering_id', '=', 'co.id')
            ->join('semesters as sem', 'ar.semester_id', '=', 'sem.id')
            ->join('units as u', 'ar.unit_id', '=', 'u.id')
            ->join('programs as p', 'ar.program_id', '=', 'p.id')
            ->join('campuses as c', 'ar.campus_id', '=', 'c.id')
            ->leftJoin('syllabus_templates as st', 'co.syllabus_template_id', '=', 'st.id')
            ->leftJoin('lectures as l', 'co.lecture_id', '=', 'l.id')
            ->whereNull('ar.deleted_at')
            ->whereNull('s.deleted_at')
            ->select([
                's.student_id as student_code',
                's.full_name as student_name',
                's.email as student_email',
                'p.name as program_name',
                'c.name as campus_name',
                's.intake',
                'sem.code as semester_code',
                'sem.name as semester_name',
                'u.code as unit_code',
                'u.name as unit_name',
                'u.credit_points',
                'co.section_code',
                'l.first_name as instructor_first_name',
                'l.last_name as instructor_last_name',
                'ar.attendance_percentage',
                'ar.total_present',
                'ar.total_absences',
                'ar.total_late',
                'ar.total_not_recorded',
                'ar.total_class_sessions',
                'st.min_attendance_threshold',
                'ar.meets_attendance_requirement',
                'ar.final_percentage',
                'ar.final_letter_grade',
                'ar.grade_points',
                'st.min_grade_threshold',
                'ar.grade_status',
                'ar.completion_status',
                'ar.is_passed',
                'ar.failure_reason',
                'ar.override_pass',
                'ar.override_reason',
                'ar.attempt_number',
                'ar.is_repeat_course',
            ])
            ->orderBy('sem.start_date')
            ->orderBy('u.code')
            ->orderBy('s.student_id');
    }

    private function formatInstructorName(?string $firstName, ?string $lastName): ?string
    {
        $name = trim(trim((string) $firstName).' '.trim((string) $lastName));

        return $name !== '' ? $name : null;
    }

    private function formatBoolean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
    }

    private function formatPassFail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'PASS' : 'FAIL';
    }
}
