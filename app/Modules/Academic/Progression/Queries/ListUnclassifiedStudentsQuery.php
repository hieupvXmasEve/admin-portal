<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\Student;
use App\Shared\Support\Academic\StudentLifecycleProjection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approved-but-unclassified students for the placement worklist: the primary
 * program enrollment exists (approve flow always materializes one) but has no
 * study_stage yet, meaning Academic staff still need to classify the student
 * into EGC or straight major.
 */
class ListUnclassifiedStudentsQuery
{
    public function handle(array $filters, int $campusId): LengthAwarePaginator
    {
        return Student::query()
            ->select([
                'students.id',
                'students.student_id',
                'students.full_name',
                'students.email',
                'students.program_id',
                'students.intake_semester_id',
                'students.admission_date',
            ])
            ->with(['program:id,name,code', 'intakeSemester:id,name,code'])
            // Join the tie-break-winning primary row only (binding convention:
            // a student may hold several is_primary rows, highest id wins) —
            // joining on the flag alone would duplicate rows and could show a
            // student whose canonical enrollment is already classified.
            ->join('program_enrollments', function ($join): void {
                $join->on('program_enrollments.student_id', '=', 'students.id')
                    ->whereRaw('program_enrollments.id = '.StudentLifecycleProjection::primaryEnrollmentIdSubquery());
            })
            ->whereNull('program_enrollments.study_stage')
            ->whereNotIn('program_enrollments.enrollment_status', ['withdrawn', 'graduated'])
            ->where('students.campus_id', $campusId)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('students.student_id', 'like', "%{$search}%")
                        ->orWhere('students.full_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['program_id'] ?? null, fn (Builder $query, int|string $programId) => $query->where('students.program_id', (int) $programId))
            ->orderByDesc('students.admission_date')
            ->orderByDesc('students.id')
            ->paginate(
                (int) ($filters['per_page'] ?? 15),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            )
            ->withQueryString();
    }
}
