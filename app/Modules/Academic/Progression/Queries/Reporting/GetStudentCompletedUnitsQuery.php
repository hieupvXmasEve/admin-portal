<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries\Reporting;

use App\Models\AcademicRecord;
use App\Models\CurriculumUnit;
use App\Models\Student;
use App\Shared\Contracts\Academic\StudentCompletedUnitsReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class GetStudentCompletedUnitsQuery implements StudentCompletedUnitsReader
{
    private const SORTABLE_STUDENT_COLUMNS = ['student_id', 'full_name'];

    private const SORTABLE_DERIVED_COLUMNS = ['units_count', 'credits_earned'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 25);

        $paginator = $this->buildStudentsQuery($filters)->paginate($perPage)->withQueryString();
        $rows = $this->attachCompletedUnits($paginator->getCollection());

        return $paginator->setCollection($rows->values())->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function handleExport(array $filters): Collection
    {
        $students = $this->buildStudentsQuery($filters)->get();

        return $this->attachCompletedUnits($students)->values();
    }

    private function buildStudentsQuery(array $filters): Builder
    {
        $campusId = $filters['campus_id'] ?? session('current_campus_id');
        $sort = (string) ($filters['sort'] ?? 'student_id');
        $direction = (string) ($filters['direction'] ?? 'asc');

        $query = Student::query()
            ->where('campus_id', $campusId)
            ->with('program:id,name')
            ->when(
                ! empty($filters['program_id']),
                fn (Builder $query) => $query->where('program_id', $filters['program_id']),
            )
            ->when(! empty($filters['keyword']), function (Builder $query) use ($filters): void {
                $keyword = (string) $filters['keyword'];
                $query->where(function (Builder $nested) use ($keyword): void {
                    $nested
                        ->where('full_name', 'like', "%{$keyword}%")
                        ->orWhere('student_id', 'like', "%{$keyword}%");
                });
            });

        return in_array($sort, self::SORTABLE_DERIVED_COLUMNS, true)
            ? $this->applyDerivedSort($query, $sort, $direction)
            : $this->applyStudentColumnSort($query, $sort, $direction);
    }

    private function applyStudentColumnSort(Builder $query, string $sort, string $direction): Builder
    {
        $column = in_array($sort, self::SORTABLE_STUDENT_COLUMNS, true) ? $sort : 'student_id';

        return $query->orderBy($column, $direction);
    }

    /**
     * Derived sort columns aren't stored on `students`, so pull them via a
     * dedupe-then-sum subquery rather than sorting after fetching the page —
     * otherwise pagination would slice the wrong rows.
     */
    private function applyDerivedSort(Builder $query, string $sort, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        // MySQL rejects a derived table (subquery in FROM) correlated to the
        // outer query without LATERAL, so the dedupe-per-unit is expressed as
        // a WHERE-clause correlated subquery instead: keep only the row whose
        // id matches the highest (attempt_number, id) pair for that unit.
        $latestAttemptPerUnit = 'ar.id = (
                select ar2.id from academic_records ar2
                 where ar2.student_id = ar.student_id
                   and ar2.unit_id = ar.unit_id
                   and ar2.is_passed = 1
                 order by ar2.attempt_number desc, ar2.id desc
                 limit 1
            )';

        $query->addSelect('students.*');

        // `students.id` breaks ties deterministically — most rows tie at 0
        // credits/units (only ~40% of students have any passed record), and
        // an untied ORDER BY lets MySQL return a different order per page,
        // duplicating or dropping rows across pagination.
        if ($sort === 'credits_earned') {
            $query->selectRaw(
                "(select coalesce(sum(ar.credit_points_earned), 0)
                    from academic_records ar
                   where ar.student_id = students.id and ar.is_passed = 1
                     and {$latestAttemptPerUnit}) as credits_earned",
            );

            return $query->orderBy('credits_earned', $direction)->orderBy('students.id');
        }

        return $query
            ->orderByRaw(
                "(select count(*)
                    from academic_records ar
                   where ar.student_id = students.id and ar.is_passed = 1
                     and {$latestAttemptPerUnit}) {$direction}",
            )
            ->orderBy('students.id');
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    private function attachCompletedUnits(Collection $students): Collection
    {
        $studentIds = $students->pluck('id')->all();

        $recordsByStudent = empty($studentIds)
            ? collect()
            : $this->passedRecords($studentIds)->groupBy('student_id');

        $curriculumVersionIds = $students->pluck('curriculum_version_id')->filter()->unique()->all();
        $requiredCreditsByCurriculum = $this->requiredCreditsByCurriculumVersion($curriculumVersionIds);

        return $students->map(function (Student $student) use ($recordsByStudent, $requiredCreditsByCurriculum): array {
            $deduped = $this->dedupe($recordsByStudent->get($student->id, collect()));
            $gc = $deduped->filter(fn ($r) => $r->unit_type === 'egc')->sortBy('code')->values();
            // Anything other than 'egc' is treated as Major, so a future third
            // unit_type value doesn't silently vanish from the report.
            $major = $deduped->filter(fn ($r) => $r->unit_type !== 'egc')->sortBy('code')->values();

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program' => $student->program?->name,
                'gc' => $gc->map(fn ($r) => $this->unitPayload($r))->all(),
                'major' => $major->map(fn ($r) => $this->unitPayload($r))->all(),
                'units_count' => $deduped->count(),
                'credits_earned' => (string) $deduped->sum(fn ($r) => (float) $r->credit_points_earned),
                'credits_required' => (string) (float) ($requiredCreditsByCurriculum[$student->curriculum_version_id] ?? 0),
            ];
        });
    }

    /**
     * Total credits the whole curriculum requires, per curriculum_version_id
     * — batched once per page/export rather than per student.
     *
     * @param  list<int>  $curriculumVersionIds
     * @return Collection<int, float>
     */
    private function requiredCreditsByCurriculumVersion(array $curriculumVersionIds): Collection
    {
        if (empty($curriculumVersionIds)) {
            return collect();
        }

        return CurriculumUnit::query()
            ->whereIn('curriculum_units.curriculum_version_id', $curriculumVersionIds)
            ->join('units', 'units.id', '=', 'curriculum_units.unit_id')
            ->groupBy('curriculum_units.curriculum_version_id')
            ->selectRaw('curriculum_units.curriculum_version_id, sum(units.credit_points) as total_credits')
            ->pluck('total_credits', 'curriculum_version_id');
    }

    private function unitPayload(object $record): array
    {
        return [
            'code' => $record->code,
            'name' => $record->name,
            'credits' => $record->credit_points_earned,
        ];
    }

    /** @param  list<int>  $studentIds */
    private function passedRecords(array $studentIds): Collection
    {
        return AcademicRecord::query()
            ->whereIn('academic_records.student_id', $studentIds)
            ->where('academic_records.is_passed', true)
            ->join('units', 'units.id', '=', 'academic_records.unit_id')
            ->select([
                'academic_records.id',
                'academic_records.student_id',
                'academic_records.unit_id',
                'academic_records.attempt_number',
                'academic_records.credit_points_earned',
                'units.code',
                'units.name',
                'units.unit_type',
            ])
            ->get();
    }

    /**
     * Dedupe by (student_id, unit_id): keep highest attempt_number, tie broken
     * by highest id. attempt_number never leaves this method.
     */
    private function dedupe(Collection $records): Collection
    {
        return $records
            ->groupBy('unit_id')
            ->map(fn (Collection $group) => $group
                ->sortByDesc(fn ($r) => [(int) $r->attempt_number, (int) $r->id])
                ->first())
            ->values();
    }

    public function freshness(): string
    {
        return 'computed_at_request_time';
    }

    public function permissionScope(): string
    {
        return 'view_academic_report_current_campus';
    }

    public function fieldOwnership(): array
    {
        return [
            'data' => StudentCompletedUnitsReader::class,
        ];
    }
}
