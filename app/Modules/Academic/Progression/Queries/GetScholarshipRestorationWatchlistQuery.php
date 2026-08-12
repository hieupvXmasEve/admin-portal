<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Modules\Academic\Delivery\Queries\GetStudentAcademicAttendanceSummaryQuery;
use App\Modules\Academic\Delivery\Queries\ListStudentCourseRegistrationsQuery;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;
use App\Shared\Contracts\Finance\DTO\ScholarshipRestorationWatchlistRow;
use App\Shared\Contracts\Finance\ScholarshipRestorationWatchlistReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Scholarship restoration watchlist (Phase 3) — every student currently
 * carrying an adjustment (no proposal / pending / rejected / approved-partial;
 * approved-full is excluded upstream by the Finance reader), decorated with
 * the academic evidence staff need to decide: progressive verdict, GPA,
 * attendance, and current course registrations.
 *
 * Population is bounded by "students Finance is still carrying an adjustment
 * for" — never the whole campus — so decorating every row before filtering
 * is acceptable (YAGNI per the phase's own risk note); escalate to a bulk
 * query only if this is measured slow in practice.
 */
class GetScholarshipRestorationWatchlistQuery
{
    /** Bound on how many semesters FROM the target forward the progressive verdict scans. */
    private const MAX_VERDICT_LOOKAHEAD_SEMESTERS = 12;

    /** @var array<int, array{0: Semester, 1: Collection<int, Semester>}> Keyed by target_semester_id — rows commonly share a target. */
    private array $candidateCache = [];

    public function __construct(
        private readonly ScholarshipRestorationWatchlistReader $watchlistReader,
        private readonly ScholarshipRestorationVerdictReader $verdictReader,
        private readonly GetStudentAcademicAttendanceSummaryQuery $attendanceQuery,
        private readonly ListStudentCourseRegistrationsQuery $courseRegistrationsQuery,
        private readonly StudentReferenceReader $students,
    ) {}

    /**
     * @param  array{search?: ?string, semester_id?: ?int, verdict?: ?string, proposal_state?: ?string, per_page?: ?int, page?: ?int}  $filters
     */
    public function handle(int $campusId, array $filters = []): LengthAwarePaginator
    {
        $filtered = $this->decoratedAndFiltered($campusId, $filters, includeCourseDetail: true);

        if ($filtered === null) {
            return $this->emptyPaginator($filters);
        }

        [$perPage, $page] = $this->pagination($filters);

        return new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values()->all(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Every matching row, undecorated by pagination — for the export, which
     * must never silently truncate at handle()'s page-size cap. Skips course
     * registration detail entirely (includeCourseDetail: false) — the export
     * columns never render it, and fetching it per row is the expensive part
     * of decorate() (a per-registration N+1 inside the presenter).
     *
     * @param  array{search?: ?string, semester_id?: ?int, verdict?: ?string, proposal_state?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function handleAll(int $campusId, array $filters = []): Collection
    {
        return $this->decoratedAndFiltered($campusId, $filters, includeCourseDetail: false) ?? collect();
    }

    /**
     * @param  array{search?: ?string, semester_id?: ?int, verdict?: ?string, proposal_state?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>|null Null when the campus has nothing carried at all.
     */
    private function decoratedAndFiltered(int $campusId, array $filters, bool $includeCourseDetail): ?Collection
    {
        $rows = $this->watchlistReader->listCarried($campusId);

        if ($rows->isEmpty()) {
            return null;
        }

        $studentsById = $this->students->findMany($rows->pluck('student_id')->unique()->values()->all());

        $decorated = $rows
            ->map(fn (ScholarshipRestorationWatchlistRow $row) => $this->decorate($row, $studentsById[$row->student_id] ?? null, $includeCourseDetail))
            ->filter(fn (?array $row) => $row !== null)
            ->values();

        return $this->applyFilters($decorated, $filters);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{search?: ?string, semester_id?: ?int, verdict?: ?string, proposal_state?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        $search = $filters['search'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;
        $verdict = $filters['verdict'] ?? null;
        $proposalState = $filters['proposal_state'] ?? null;

        return $rows
            ->when($search, fn (Collection $collection) => $collection->filter(
                fn (array $row) => str_contains(mb_strtolower($row['student']['full_name'] ?? ''), mb_strtolower($search))
                    || str_contains(mb_strtolower($row['student']['student_code'] ?? ''), mb_strtolower($search)),
            ))
            ->when($semesterId, fn (Collection $collection) => $collection->where('target_semester_id', (int) $semesterId))
            ->when($verdict, fn (Collection $collection) => $collection->where('verdict', $verdict))
            ->when($proposalState, fn (Collection $collection) => $collection->where('proposal_state', $proposalState))
            ->values();
    }

    /**
     * @return array<string, mixed>|null Null when the row can't be safely
     *                                   decorated (student reference gone, target semester soft-deleted, or the
     *                                   student's campus no longer matches the adjustment's snapshot) — dropped
     *                                   rather than 404ing the whole watchlist for every other row.
     */
    private function decorate(ScholarshipRestorationWatchlistRow $row, ?StudentReference $student, bool $includeCourseDetail): ?array
    {
        if ($student === null) {
            return null;
        }

        // Student's live campus no longer matches this adjustment's snapshot
        // campus_id — drop the row rather than let ListStudentCourseRegistrations
        // Query 404 the whole page on it further down.
        if ($student->campusId !== $row->campus_id) {
            return null;
        }

        $target = Semester::query()->find($row->target_semester_id);

        if ($target === null) {
            return null;
        }

        [$verdict, $evaluatedSemester] = $this->progressiveVerdict($row->student_id, $target);

        $gpa = GpaCalculation::query()
            ->where('student_id', $row->student_id)
            ->bySemester($evaluatedSemester->id)
            ->first(['semester_gpa', 'cumulative_gpa']);

        $attendance = $this->attendanceForSemester($row->student_id, $evaluatedSemester->id);

        $courses = [];
        $currentRegistrations = [];

        if ($includeCourseDetail) {
            $courses = $attendance->map(fn (array $course) => [
                'unit_code' => $course['unit_code'],
                'unit_name' => $course['unit_name'],
                'attendance_percentage' => $course['attendance_percentage'],
                'absent_count' => $course['absent_count'],
            ])->values()->all();

            // Trimmed to the two fields the UI renders — the presenter's raw
            // payload carries the full course_offering model (capacity,
            // lecturer, enrollment_status...) which is unused report noise.
            $currentRegistrations = collect($this->courseRegistrationsQuery->handle($row->student_id, $evaluatedSemester->id, $row->campus_id))
                ->map(fn (array $registration) => [
                    'course_code' => $registration['course_offering']['course_code'] ?? null,
                    'course_title' => $registration['course_offering']['course_title'] ?? null,
                ])
                ->values()
                ->all();
        }

        return [
            'adjustment_id' => $row->adjustment_id,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->studentCode,
                'full_name' => $student->fullName,
            ],
            'target_semester' => [
                'id' => $target->id,
                'code' => $target->code,
                'name' => $target->name,
            ],
            'target_semester_id' => $row->target_semester_id,
            'original_type' => $row->original_type,
            'original_amount' => $row->original_amount,
            'adjusted_amount' => $row->adjusted_amount,
            'effective_amount' => $row->effective_amount,
            'proposal_state' => $row->proposal_state,
            'latest_proposal_id' => $row->latest_proposal_id,
            'latest_restored_amount' => $row->latest_restored_amount,
            'academic_dossier_id' => $row->academic_dossier_id,
            'verdict' => $verdict,
            'evaluated_semester' => [
                'id' => $evaluatedSemester->id,
                'code' => $evaluatedSemester->code,
                'name' => $evaluatedSemester->name,
            ],
            'semester_gpa' => $gpa?->semester_gpa !== null ? (float) $gpa->semester_gpa : null,
            'cumulative_gpa' => $gpa?->cumulative_gpa !== null ? (float) $gpa->cumulative_gpa : null,
            'attendance_min_percentage' => $attendance->isEmpty() ? null : (float) $attendance->min('attendance_percentage'),
            'courses' => $courses,
            'current_registrations' => $currentRegistrations,
        ];
    }

    /**
     * Latest semester ≥ target with finalized grades — the first one (scanning
     * from the newest candidate backward toward target) ScholarshipRestoration
     * VerdictQuery does NOT report NOT_FINALIZED for. Falls back to
     * NOT_FINALIZED at the target semester itself when nothing in the window
     * qualifies.
     *
     * The candidate window is the MAX_VERDICT_LOOKAHEAD_SEMESTERS semesters
     * NEAREST the target (bounded ascending from target, then scanned
     * descending) — not the newest semesters system-wide, which would drop
     * exactly the semesters adjacent to an old target.
     *
     * @return array{0: string, 1: Semester}
     */
    private function progressiveVerdict(int $studentId, Semester $target): array
    {
        $candidates = $this->candidatesForTarget($target);

        foreach ($candidates as $candidate) {
            $verdict = $this->verdictReader->verdict($studentId, (int) $candidate->id);

            if ($verdict !== ScholarshipRestorationVerdictReader::NOT_FINALIZED) {
                return [$verdict, $candidate];
            }
        }

        return [ScholarshipRestorationVerdictReader::NOT_FINALIZED, $target];
    }

    /**
     * Candidate semesters for one target, nearest-first bound then scanned
     * newest-first, cached per target — rows commonly share a target semester.
     *
     * @return Collection<int, Semester>
     */
    private function candidatesForTarget(Semester $target): Collection
    {
        if (isset($this->candidateCache[$target->id])) {
            return $this->candidateCache[$target->id][1];
        }

        $candidates = Semester::query()
            ->where('start_date', '>=', $target->start_date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->limit(self::MAX_VERDICT_LOOKAHEAD_SEMESTERS)
            ->get()
            ->reverse()
            ->values();

        $this->candidateCache[$target->id] = [$target, $candidates];

        return $candidates;
    }

    /**
     * @return Collection<int, array{unit_code: string|null, unit_name: string|null, attendance_percentage: float, absent_count: int}>
     */
    private function attendanceForSemester(int $studentId, int $semesterId): Collection
    {
        $summary = $this->attendanceQuery->execute($studentId);

        return collect($summary['data'] ?? [])
            ->filter(fn (array $course) => (int) $course['semester_id'] === $semesterId)
            ->values();
    }

    private function emptyPaginator(array $filters): LengthAwarePaginator
    {
        [$perPage, $page] = $this->pagination($filters);

        return new LengthAwarePaginator([], 0, $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
    }

    /** @return array{0: int, 1: int} */
    private function pagination(array $filters): array
    {
        return [
            max(1, min((int) ($filters['per_page'] ?? 25), 100)),
            max(1, (int) ($filters['page'] ?? 1)),
        ];
    }
}
