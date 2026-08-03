<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries\Reporting;

use App\Models\AcademicRecord;
use App\Models\CurriculumUnit;
use App\Models\Student;
use App\Shared\Contracts\Academic\StudentCompletedUnitsReader;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class GetStudentCompletedUnitsQuery implements StudentCompletedUnitsReader
{
    private const SORTABLE_STUDENT_COLUMNS = ['student_id', 'full_name'];

    private const SORTABLE_DERIVED_COLUMNS = ['units_count', 'credits_earned'];

    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 25);

        $paginator = $this->buildStudentsQuery($filters)->paginate($perPage)->withQueryString();
        // The page renders one merged Major column, so the per-semester
        // breakdown would be dead weight in the Inertia payload.
        $rows = $this->attachRegisteredUnits($paginator->getCollection(), $this->semesterFilter($filters), withSemesterBreakdown: false);

        return $paginator->setCollection($rows->values())->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function handleExport(array $filters): Collection
    {
        $students = $this->buildStudentsQuery($filters)->get();

        return $this->attachRegisteredUnits($students, $this->semesterFilter($filters), withSemesterBreakdown: true)->values();
    }

    /** @param array<string, mixed> $filters */
    private function semesterFilter(array $filters): ?int
    {
        return empty($filters['semester_id']) ? null : (int) $filters['semester_id'];
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
            ? $this->applyDerivedSort($query, $sort, $direction, $this->semesterFilter($filters))
            : $this->applyStudentColumnSort($query, $sort, $direction);
    }

    private function applyStudentColumnSort(Builder $query, string $sort, string $direction): Builder
    {
        $column = in_array($sort, self::SORTABLE_STUDENT_COLUMNS, true) ? $sort : 'student_id';

        return $query->orderBy($column, $direction);
    }

    /**
     * Derived sort columns aren't stored on `students`, so pull them via a
     * unique-per-unit subquery rather than sorting after fetching the page —
     * otherwise pagination would slice the wrong rows.
     *
     * Both branches must apply exactly the same scope as the displayed payload
     * (`attachRegisteredUnits`), semester filter included, or the sort order
     * disagrees with what the row shows and pagination duplicates rows.
     */
    private function applyDerivedSort(Builder $query, string $sort, string $direction, ?int $semesterId): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        // Cast to int at the boundary: this value is interpolated into raw SQL.
        $semesterScope = $semesterId === null ? '' : " and ar.semester_id = {$semesterId}";
        $semesterScopeInner = $semesterId === null ? '' : " and ar2.semester_id = {$semesterId}";

        // MySQL rejects a derived table (subquery in FROM) correlated to the
        // outer query without LATERAL, so keeping one row per unit is expressed
        // as a WHERE-clause correlated subquery instead: keep only the row whose
        // id matches the highest (attempt_number, id) pair for that unit.
        // `academic_records` is soft-deleted. The displayed payload goes through
        // Eloquent and so drops trashed rows automatically; this raw SQL does
        // not, and a trashed row that wins the latest-attempt pick would make
        // the sort disagree with the numbers rendered in the row.
        $latestAttemptPerUnit = "ar.deleted_at is null and ar.id = (
                select ar2.id from academic_records ar2
                 where ar2.student_id = ar.student_id
                   and ar2.unit_id = ar.unit_id
                   and ar2.deleted_at is null
                   {$semesterScopeInner}
                 order by ar2.attempt_number desc, ar2.id desc
                 limit 1
            )";

        // Credits count the latest *passing* attempt per unit. Reusing the
        // registration-wide expression above would zero out a unit whose newest
        // attempt is a failed retake of an already-passed unit.
        $latestPassedAttemptPerUnit = "ar.deleted_at is null and ar.id = (
                select ar2.id from academic_records ar2
                 where ar2.student_id = ar.student_id
                   and ar2.unit_id = ar.unit_id
                   and ar2.is_passed = 1
                   and ar2.deleted_at is null
                   {$semesterScopeInner}
                 order by ar2.attempt_number desc, ar2.id desc
                 limit 1
            )";

        $query->addSelect('students.*');

        // `students.id` breaks ties deterministically — many rows tie at 0
        // credits/units, and an untied ORDER BY lets MySQL return a different
        // order per page, duplicating or dropping rows across pagination.
        if ($sort === 'credits_earned') {
            // Credits stay pass-only even though the unit list is registration-
            // based: an in-progress unit has earned nothing yet.
            $query->selectRaw(
                "(select coalesce(sum(ar.credit_points_earned), 0)
                    from academic_records ar
                   where ar.student_id = students.id and ar.is_passed = 1
                     {$semesterScope}
                     and {$latestPassedAttemptPerUnit}) as credits_earned",
            );

            return $query->orderBy('credits_earned', $direction)->orderBy('students.id');
        }

        return $query
            ->orderByRaw(
                "(select count(*)
                    from academic_records ar
                   where ar.student_id = students.id
                     {$semesterScope}
                     and {$latestAttemptPerUnit}) {$direction}",
            )
            ->orderBy('students.id');
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    private function attachRegisteredUnits(Collection $students, ?int $semesterId, bool $withSemesterBreakdown): Collection
    {
        $studentIds = $students->pluck('id')->all();

        $recordsByStudent = empty($studentIds)
            ? collect()
            : $this->registeredRecords($studentIds, $semesterId)->groupBy('student_id');

        $statuses = empty($studentIds) ? [] : $this->lifecycleStatuses->statusesFor($studentIds);

        $curriculumVersionIds = $students->pluck('curriculum_version_id')->filter()->unique()->all();
        $requiredCreditsByCurriculum = $this->requiredCreditsByCurriculumVersion($curriculumVersionIds);

        return $students->map(function (Student $student) use ($recordsByStudent, $requiredCreditsByCurriculum, $statuses, $semesterId, $withSemesterBreakdown): array {
            $records = $recordsByStudent->get($student->id, collect());
            $unique = $this->uniqueByUnit($records);
            $gc = $unique->filter(fn ($r) => $r->unit_type === 'egc')->sortBy('code')->values();
            // Anything other than 'egc' is treated as Major, so a future third
            // unit_type value doesn't silently vanish from the report.
            $major = $unique->filter(fn ($r) => $r->unit_type !== 'egc')->sortBy('code')->values();

            // Only meaningful for an unfiltered export, where each semester
            // becomes its own column. A retake registered in two semesters
            // appears under both, unlike the unique `major` chip list.
            $semesterBreakdown = $withSemesterBreakdown && $semesterId === null
                ? ['units_by_semester' => $this->unitsBySemester($records)]
                : [];

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program' => $student->program?->name,
                'status' => $statuses[$student->id] ?? null,
                'gc' => $gc->map(fn ($r) => $this->unitPayload($r))->all(),
                'major' => $major->map(fn ($r) => $this->unitPayload($r))->all(),
                'units_count' => $unique->count(),
                'credits_earned' => (string) $this->creditsEarned($records),
                'credits_required' => (string) (float) ($requiredCreditsByCurriculum[$student->curriculum_version_id] ?? 0),
                ...$semesterBreakdown,
            ];
        });
    }

    /**
     * Credits stay pass-only even though the unit list is registration-based:
     * an in-progress or failed unit has earned nothing. Counted once per unit,
     * on the latest passing attempt.
     *
     * @param  Collection<int, object>  $records
     */
    private function creditsEarned(Collection $records): float
    {
        return (float) $this->uniqueByUnit($records->filter(fn ($r) => (bool) $r->is_passed))
            ->sum(fn ($r) => (float) $r->credit_points_earned);
    }

    /**
     * Registrations grouped by semester, each already unique per unit within
     * that semester.
     *
     * @param  Collection<int, object>  $records
     * @return array<int, array{code: string, sort: string, units: list<array<string, mixed>>}>
     */
    private function unitsBySemester(Collection $records): array
    {
        return $records
            ->groupBy('semester_id')
            ->map(fn (Collection $group): array => [
                'code' => (string) $group->first()->semester_code,
                // Null start_date sorts last; the id keeps it deterministic.
                'sort' => sprintf('%s|%d', $group->first()->semester_start ?? '9999-12-31', (int) $group->first()->semester_id),
                'units' => $this->uniqueByUnit($group)
                    ->filter(fn ($r) => $r->unit_type !== 'egc')
                    ->sortBy('code')
                    ->map(fn ($r) => $this->unitPayload($r))
                    ->values()
                    ->all(),
            ])
            ->all();
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

    /**
     * Every registration, regardless of outcome — the report lists what a
     * student signed up for, so failed and in-progress units belong here too.
     *
     * @param  list<int>  $studentIds
     */
    private function registeredRecords(array $studentIds, ?int $semesterId): Collection
    {
        return AcademicRecord::query()
            ->whereIn('academic_records.student_id', $studentIds)
            ->when($semesterId !== null, fn ($query) => $query->where('academic_records.semester_id', $semesterId))
            ->join('units', 'units.id', '=', 'academic_records.unit_id')
            ->join('semesters', 'semesters.id', '=', 'academic_records.semester_id')
            ->select([
                'academic_records.id',
                'academic_records.student_id',
                'academic_records.unit_id',
                'academic_records.semester_id',
                'academic_records.attempt_number',
                'academic_records.credit_points_earned',
                'academic_records.is_passed',
                'units.code',
                'units.name',
                'units.unit_type',
                'semesters.code as semester_code',
                'semesters.start_date as semester_start',
            ])
            ->get();
    }

    /**
     * One row per (student_id, unit_id) within the given scope: keep highest
     * attempt_number, tie broken by highest id. attempt_number never leaves
     * this method.
     */
    private function uniqueByUnit(Collection $records): Collection
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
