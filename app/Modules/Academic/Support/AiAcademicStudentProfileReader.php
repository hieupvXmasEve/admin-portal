<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Enums\StudentActionType;
use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\GpaCalculation;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Delivery\Queries\GetStudentAcademicAttendanceSummaryQuery;
use App\Modules\Academic\Progression\Queries\GetStudentActionHistoryQuery;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\AiAcademicStudentProfileReader as AiAcademicStudentProfileReaderContract;
use Illuminate\Database\Eloquent\Collection;

class AiAcademicStudentProfileReader implements AiAcademicStudentProfileReaderContract
{
    public function __construct(
        private readonly GetStudentAcademicAttendanceSummaryQuery $attendanceQuery,
        private readonly GetStudentActionHistoryQuery $actionHistoryQuery,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    public function identity(int $studentId): ?array
    {
        $student = Student::query()
            ->select([
                'id',
                'student_id',
                'full_name',
                'campus_id',
                'program_id',
                'specialization_id',
                'curriculum_version_id',
                'intake_semester_id',
                'intake_gc',
                'intake_major',
                'admission_date',
                'expected_graduation_date',
                'status',
                'academic_status',
                'gc_starting_level',
                'gc_current_level',
                'gc_total_levels',
            ])
            ->with([
                'campus:id,code,name',
                'program:id,code,name',
                'specialization:id,code,name',
                'curriculumVersion:id,version_code',
                'intakeSemester:id,code,name',
                'intakeGcSemester:id,code,name',
                'intakeMajorSemester:id,code,name',
            ])
            ->find($studentId);

        if (! $student instanceof Student) {
            return null;
        }

        return [
            'student_code' => (string) $student->student_id,
            'display_name' => (string) $student->full_name,
            'campus_code' => $student->campus?->code,
            'campus_name' => $student->campus?->name,
            'program_code' => $student->program?->code,
            'program_name' => $student->program?->name,
            'specialization_code' => $student->specialization?->code,
            'specialization_name' => $student->specialization?->name,
            'curriculum_version_code' => $student->curriculumVersion?->version_code,
            'intake_semester_code' => $student->intakeSemester?->code,
            'intake_gc_semester_code' => $student->intakeGcSemester?->code,
            'intake_major_semester_code' => $student->intakeMajorSemester?->code,
            'admission_date' => $student->admission_date?->toDateString(),
            'expected_graduation_date' => $student->expected_graduation_date?->toDateString(),
            'status' => $student->status,
            'academic_status' => $student->academic_status,
            'gc_level_snapshot' => [
                'starting_level' => $student->gc_starting_level,
                'current_level' => $student->gc_current_level,
                'total_levels' => $student->gc_total_levels,
            ],
        ];
    }

    public function academicSummary(int $studentId): array
    {
        $currentGpa = GpaCalculation::query()
            ->with('semester:id,code,name')
            ->where('student_id', $studentId)
            ->orderByDesc('is_current')
            ->latest('id')
            ->first();

        $registrationQuery = CourseRegistration::query()->where('student_id', $studentId);
        $recordQuery = AcademicRecord::query()->where('student_id', $studentId);
        $currentPeriod = $this->academicPeriods->current();

        return [
            'current_semester_code' => $currentGpa?->semester?->code ?? $currentPeriod?->code,
            'semester_gpa' => $this->nullableFloat($currentGpa?->semester_gpa),
            'cumulative_gpa' => $this->nullableFloat($currentGpa?->cumulative_gpa),
            'academic_standing' => $currentGpa?->academic_standing,
            'total_registrations' => (clone $registrationQuery)->count(),
            'active_registrations' => (clone $registrationQuery)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->count(),
            'completed_courses' => (clone $recordQuery)
                ->where('completion_status', 'completed')
                ->count(),
            'current_semester_registrations' => $currentPeriod !== null
                ? (clone $registrationQuery)->where('semester_id', $currentPeriod->id)->count()
                : 0,
            'attempted_credit_points' => $this->sumAsFloat((clone $recordQuery), 'credit_points'),
            'earned_credit_points' => $this->sumAsFloat((clone $recordQuery), 'credit_points_earned'),
            'active_holds_count' => Student::query()->find($studentId)?->academicHolds()->where('status', 'active')->count() ?? 0,
            'retake_count' => (clone $registrationQuery)->where('is_retake', true)->count(),
        ];
    }

    public function enrollments(int $studentId, int $limit): array
    {
        $items = CourseRegistration::query()
            ->with([
                'courseOffering:id,semester_id,unit_id,campus_id,section_code,course_status',
                'courseOffering.unit:id,code,name,credit_points',
                'semester:id,code,name',
            ])
            ->where('student_id', $studentId)
            ->latest('registration_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (CourseRegistration $registration): array => [
                'semester_code' => $registration->semester?->code,
                'unit_code' => $registration->courseOffering?->unit?->code,
                'unit_name' => $registration->courseOffering?->unit?->name,
                'section_code' => $registration->courseOffering?->section_code,
                'course_status' => $registration->courseOffering?->course_status,
                'registration_status' => $registration->registration_status,
                'credit_points' => $this->nullableFloat($registration->credit_points),
                'credit_hours' => $this->nullableFloat($registration->credit_hours),
                'final_grade' => $registration->final_grade,
                'grade_points' => $this->nullableFloat($registration->grade_points),
                'attempt_number' => $registration->attempt_number,
                'is_retake' => (bool) $registration->is_retake,
                'registration_date' => $registration->registration_date?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'result_count' => count($items),
            'limit' => $limit,
        ];
    }

    public function attendanceSummary(int $studentId, int $limit): array
    {
        $student = Student::query()->find($studentId);

        if (! $student instanceof Student) {
            return [
                'summary' => [],
                'courses' => [],
                'result_count' => 0,
                'limit' => $limit,
            ];
        }

        $attendance = $this->attendanceQuery->execute((int) $student->id);
        $courses = collect($attendance['data'] ?? [])
            ->take($limit)
            ->map(fn (array $course): array => [
                'unit_code' => $course['unit_code'] ?? null,
                'unit_name' => $course['unit_name'] ?? null,
                'semester' => $course['semester'] ?? null,
                'section_code' => $course['section_code'] ?? null,
                'attempt_number' => $course['attempt_number'] ?? null,
                'is_retake' => (bool) ($course['is_retake'] ?? false),
                'total_sessions' => (int) ($course['total_sessions'] ?? 0),
                'attended_count' => (int) ($course['attended_count'] ?? 0),
                'present_count' => (int) ($course['present_count'] ?? 0),
                'late_count' => (int) ($course['late_count'] ?? 0),
                'absent_count' => (int) ($course['absent_count'] ?? 0),
                'excused_count' => (int) ($course['excused_count'] ?? 0),
                'attendance_percentage' => $this->nullableFloat($course['attendance_percentage'] ?? null),
                'attendance_status' => $course['attendance_status'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'summary' => $attendance['summary'] ?? [],
            'courses' => $courses,
            'result_count' => count($courses),
            'limit' => $limit,
        ];
    }

    public function lifecycleActions(int $studentId, int $limit): array
    {
        /** @var Collection<int, StudentActionLog> $logs */
        $logs = $this->actionHistoryQuery->handle($studentId, [], false);

        $items = $logs
            ->take($limit)
            ->map(fn (StudentActionLog $log): array => [
                'action_type' => $log->action_type instanceof StudentActionType ? $log->action_type->value : (string) $log->action_type,
                'signed_at' => $log->signed_at?->toDateString(),
                'effective_at' => $log->effective_at?->toISOString(),
                'from_semester_code' => $log->fromSemester?->code,
                'return_semester_code' => $log->returnSemester?->code,
                'intended_intake_semester_code' => $log->intendedIntakeSemester?->code,
                'dropout_semester_code' => $log->dropoutSemester?->code,
                'effective_semester_code' => $log->effectiveSemester?->code,
                'from_campus_code' => $log->fromCampus?->code,
                'to_campus_code' => $log->toCampus?->code,
                'previous_status' => $log->previous_status,
                'new_status' => $log->new_status,
                'decision_number' => $log->decision_number ?? $log->decision?->decision_number,
                'decision_name' => $log->decision?->decision_name,
                'created_at' => $log->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'result_count' => count($items),
            'limit' => $limit,
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function sumAsFloat(mixed $query, string $column): float
    {
        return (float) $query->sum($column);
    }
}
