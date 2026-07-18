<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\AiAcademicEntitySearchReader as AiAcademicEntitySearchReaderContract;
use Illuminate\Database\Eloquent\Builder;

class AiAcademicEntitySearchReader implements AiAcademicEntitySearchReaderContract
{
    public function __construct(
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    public function searchStudents(string $query, ?int $campusId, array $filters, int $limit): array
    {
        $normalized = $this->normalize($query);
        $semesterId = $this->semesterIdFromFilters($filters);

        return Student::query()
            ->select(['id', 'student_id', 'full_name', 'program_id', 'intake_semester_id', 'status', 'campus_id'])
            ->with([
                'program:id,code,name',
                'intakeSemester:id,code,name',
            ])
            ->when($campusId !== null, fn (Builder $builder) => $builder->where('campus_id', $campusId))
            ->when($semesterId !== null, fn (Builder $builder) => $builder->where('intake_semester_id', $semesterId))
            ->when(isset($filters['program_id']), fn (Builder $builder) => $builder->where('program_id', (int) $filters['program_id']))
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('student_id', $query)
                    ->orWhere('student_id', 'like', $this->likePrefix($query))
                    ->orWhere('full_name', 'like', $this->likeContains($query));
            })
            ->orderByRaw('case when lower(student_id) = ? then 0 when lower(student_id) like ? then 1 else 2 end', [
                $normalized,
                $this->likePrefix($normalized),
            ])
            ->orderBy('student_id')
            ->limit($limit)
            ->get()
            ->map(fn (Student $student): array => [
                'source_id' => (int) $student->id,
                'entity_type' => 'student',
                'label' => "{$student->student_id} - {$student->full_name}",
                'safe_identifiers' => [
                    'student_code' => (string) $student->student_id,
                    'program_code' => $student->program?->code,
                    'intake_semester_code' => $student->intakeSemester?->code,
                    'status' => $student->status,
                ],
                'match_reason' => $this->studentMatchReason($query, (string) $student->student_id),
            ])
            ->values()
            ->all();
    }

    public function searchPrograms(string $query, array $filters, int $limit): array
    {
        $normalized = $this->normalize($query);

        return Program::query()
            ->select(['id', 'code', 'name'])
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('code', $query)
                    ->orWhere('code', 'like', $this->likePrefix($query))
                    ->orWhere('name', 'like', $this->likeContains($query));
            })
            ->orderByRaw('case when lower(code) = ? then 0 when lower(code) like ? then 1 else 2 end', [
                $normalized,
                $this->likePrefix($normalized),
            ])
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->map(fn (Program $program): array => [
                'source_id' => (int) $program->id,
                'entity_type' => 'program',
                'label' => "{$program->code} - {$program->name}",
                'safe_identifiers' => [
                    'code' => (string) $program->code,
                    'name' => (string) $program->name,
                ],
                'match_reason' => $this->codeMatchReason($query, (string) $program->code, 'program_code'),
            ])
            ->values()
            ->all();
    }

    public function searchSemesters(string $query, array $filters, int $limit): array
    {
        $normalized = $this->normalize($query);

        return Semester::query()
            ->select(['id', 'code', 'name', 'is_active', 'start_date', 'end_date'])
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('code', $query)
                    ->orWhere('code', 'like', $this->likePrefix($query))
                    ->orWhere('name', 'like', $this->likeContains($query));
            })
            ->orderByRaw('case when lower(code) = ? then 0 when lower(code) like ? then 1 else 2 end', [
                $normalized,
                $this->likePrefix($normalized),
            ])
            ->latest('is_active')
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->map(fn (Semester $semester): array => [
                'source_id' => (int) $semester->id,
                'entity_type' => 'semester',
                'label' => "{$semester->code} - {$semester->name}",
                'safe_identifiers' => [
                    'code' => (string) $semester->code,
                    'name' => (string) $semester->name,
                    'is_active' => (bool) $semester->is_active,
                    'start_date' => $semester->start_date?->toDateString(),
                    'end_date' => $semester->end_date?->toDateString(),
                ],
                'match_reason' => $this->codeMatchReason($query, (string) $semester->code, 'semester_code'),
            ])
            ->values()
            ->all();
    }

    public function searchCourseOfferings(string $query, ?int $campusId, array $filters, int $limit): array
    {
        $normalized = $this->normalize($query);
        $semesterId = $this->semesterIdFromFilters($filters);

        return CourseOffering::query()
            ->select(['id', 'section_code', 'unit_id', 'semester_id', 'campus_id', 'course_status'])
            ->with([
                'unit:id,code,name',
                'semester:id,code,name',
            ])
            ->when($campusId !== null, fn (Builder $builder) => $builder->where('campus_id', $campusId))
            ->when($semesterId !== null, fn (Builder $builder) => $builder->where('semester_id', $semesterId))
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('section_code', $query)
                    ->orWhere('section_code', 'like', $this->likePrefix($query))
                    ->orWhereHas('unit', function (Builder $unitQuery) use ($query): void {
                        $unitQuery->where('code', $query)
                            ->orWhere('code', 'like', $this->likePrefix($query))
                            ->orWhere('name', 'like', $this->likeContains($query));
                    })
                    ->orWhereHas('semester', function (Builder $semesterQuery) use ($query): void {
                        $semesterQuery->where('code', $query)
                            ->orWhere('code', 'like', $this->likePrefix($query));
                    });
            })
            ->orderByRaw('case when lower(section_code) = ? then 0 when lower(section_code) like ? then 1 else 2 end', [
                $normalized,
                $this->likePrefix($normalized),
            ])
            ->orderBy('section_code')
            ->limit($limit)
            ->get()
            ->map(fn (CourseOffering $offering): array => [
                'source_id' => (int) $offering->id,
                'entity_type' => 'course_offering',
                'label' => trim(($offering->section_code ?? 'Section').' - '.($offering->unit?->code ?? 'Unit')),
                'safe_identifiers' => [
                    'section_code' => $offering->section_code,
                    'unit_code' => $offering->unit?->code,
                    'unit_title' => $offering->unit?->name,
                    'semester_code' => $offering->semester?->code,
                    'course_status' => $offering->course_status,
                ],
                'match_reason' => $this->codeMatchReason($query, (string) $offering->section_code, 'section_code'),
            ])
            ->values()
            ->all();
    }

    private function studentMatchReason(string $query, string $studentCode): string
    {
        return $this->normalize($query) === $this->normalize($studentCode)
            ? 'student_id_exact'
            : 'student_search_match';
    }

    private function codeMatchReason(string $query, string $code, string $exactReason): string
    {
        return $this->normalize($query) === $this->normalize($code)
            ? $exactReason.'_exact'
            : $exactReason.'_match';
    }

    private function normalize(string $value): string
    {
        return str($value)->lower()->squish()->toString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function semesterIdFromFilters(array $filters): ?int
    {
        if (! array_key_exists('semester', $filters)) {
            return null;
        }

        if ($this->normalize((string) $filters['semester']) === 'current') {
            return $this->academicPeriods->current()?->id;
        }

        return (int) $filters['semester'];
    }

    private function likePrefix(string $value): string
    {
        return $this->escapeLike($value).'%';
    }

    private function likeContains(string $value): string
    {
        return '%'.$this->escapeLike($value).'%';
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}
