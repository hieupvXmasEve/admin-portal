<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries\Reporting;

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Progression\Support\LifecycleSemesterAnchor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cohort × semester lifecycle matrix.
 *
 * A cohort is the set of students who entered in one intake semester. Cohort
 * membership is fixed at intake and never moves — a campus transfer is an event
 * inside the cohort, not a change of cohort, so every column of the matrix keeps
 * summing to the cohort headcount.
 *
 * Each cell is a headcount by status, replayed from student_action_logs rather
 * than read off students.status, so the matrix shows where a student stood in
 * each term instead of only where they stand today.
 *
 * The two margins are derived from the matrix, never counted separately:
 *  - `current` (right margin)  = the last column, plus cohort-consistent rates
 *  - `events`  (bottom margin) = the difference between consecutive columns
 *
 * Counting a margin independently is what let the previous report disagree with
 * itself, so the difference is computed here on purpose.
 */
class GetStudentLifecycleCohortMatrixQuery
{
    /** Status a student holds before any lifecycle action is recorded. */
    private const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly LifecycleSemesterAnchor $anchor,
    ) {}

    /**
     * @return array{
     *     semesters: list<array{id:int, code:string, name:string, start_date:string}>,
     *     statuses: list<string>,
     *     cohorts: list<array<string, mixed>>,
     *     events: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     * }
     */
    public function handle(?int $campusId = null): array
    {
        $semesters = $this->timeline();

        if ($semesters->isEmpty()) {
            return ['semesters' => [], 'statuses' => [], 'cohorts' => [], 'events' => [], 'totals' => $this->emptyTotals()];
        }

        $students = $this->students($campusId);

        if ($students->isEmpty()) {
            return [
                'semesters' => $semesters->map(fn (Semester $s) => $this->semesterPayload($s))->values()->all(),
                'statuses' => [],
                'cohorts' => [],
                'events' => [],
                'totals' => $this->emptyTotals(),
            ];
        }

        $timelines = $this->replayTimelines($students, $semesters);
        $statuses = $this->statusesPresent($timelines);

        $cohorts = $students
            ->groupBy(fn (Student $student) => (int) $student->intake_semester_id)
            ->map(fn (Collection $members, $intakeSemesterId) => $this->buildCohort(
                $semesters,
                $statuses,
                $members,
                $timelines,
                (int) $intakeSemesterId,
            ))
            ->sortBy('intake_semester_start')
            ->values()
            ->all();

        return [
            'semesters' => $semesters->map(fn (Semester $s) => $this->semesterPayload($s))->values()->all(),
            'statuses' => $statuses,
            'cohorts' => $cohorts,
            'events' => $this->events($semesters, $cohorts),
            'totals' => $this->totals($cohorts, $statuses),
        ];
    }

    /**
     * Semesters up to and including the one in progress. Future terms hold no
     * observed lifecycle state, so projecting the matrix into them would invent
     * headcounts that nobody has recorded yet.
     *
     * @return Collection<int, Semester>
     */
    private function timeline(): Collection
    {
        $today = Carbon::now()->startOfDay();

        return Semester::query()
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $today)
            ->orderBy('start_date')
            ->get();
    }

    /** @return Collection<int, Student> */
    private function students(?int $campusId): Collection
    {
        return Student::query()
            ->whereNotNull('intake_semester_id')
            // Cohort A: a student belongs to the campus they entered through, so
            // filter on the intake campus and let transfers show up as events.
            ->when($campusId, fn ($query, $id) => $query->where(function ($scoped) use ($id) {
                $scoped->where('campus_id', $id)
                    ->orWhereHas('actionLogs', fn ($logs) => $logs->where('from_campus_id', $id));
            }))
            ->get(['id', 'campus_id', 'user_id', 'status', 'intake_semester_id']);
    }

    /**
     * Replay every student's action log into a per-semester status series.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Semester>  $semesters
     * @return array<int, array<int, string>> [student_id => [semester_id => status]]
     */
    private function replayTimelines(Collection $students, Collection $semesters): array
    {
        $logs = StudentActionLog::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->with(LifecycleSemesterAnchor::RELATIONS)
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        $timelines = [];

        foreach ($students as $student) {
            $transitions = $this->transitionsBySemesterStart($logs->get($student->id) ?? collect());
            $status = self::STATUS_PENDING;
            $series = [];

            foreach ($semesters as $semester) {
                $start = Carbon::parse($semester->start_date)->startOfDay();

                foreach ($transitions as $transition) {
                    if ($transition['start']->lte($start)) {
                        $status = $transition['status'];
                    }
                }

                $series[(int) $semester->id] = $status;
            }

            $timelines[(int) $student->id] = $series;
        }

        return $timelines;
    }

    /**
     * @param  Collection<int, StudentActionLog>  $logs
     * @return list<array{start: Carbon, status: string}>
     */
    private function transitionsBySemesterStart(Collection $logs): array
    {
        $transitions = [];

        foreach ($logs as $log) {
            $semester = $this->anchor->forAction($log);
            $newStatus = $log->new_status;

            // An action that changed nothing, or that never got anchored to a
            // semester, cannot be placed on the timeline.
            if ($semester?->start_date === null || $newStatus === null || $newStatus === '') {
                continue;
            }

            $transitions[] = [
                'start' => Carbon::parse($semester->start_date)->startOfDay(),
                'status' => (string) $newStatus,
            ];
        }

        usort($transitions, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $transitions;
    }

    /**
     * @param  array<int, array<int, string>>  $timelines
     * @return list<string>
     */
    private function statusesPresent(array $timelines): array
    {
        $seen = [];

        foreach ($timelines as $series) {
            foreach ($series as $status) {
                $seen[$status] = true;
            }
        }

        $ordered = array_values(array_filter(
            [self::STATUS_PENDING, 'intake_pre_uni_gc', 'intake_course', 'deferred', 'dropout', 'dropout_transfer', 'graduated'],
            fn (string $status) => isset($seen[$status]),
        ));

        // Anything the enum grows later still shows up rather than vanishing.
        foreach (array_keys($seen) as $status) {
            if (! in_array($status, $ordered, true)) {
                $ordered[] = (string) $status;
            }
        }

        return $ordered;
    }

    /**
     * @param  Collection<int, Semester>  $semesters
     * @param  list<string>  $statuses
     * @param  Collection<int, Student>  $members
     * @param  array<int, array<int, string>>  $timelines
     * @return array<string, mixed>
     */
    private function buildCohort(
        Collection $semesters,
        array $statuses,
        Collection $members,
        array $timelines,
        int $intakeSemesterId,
    ): array {
        $intakeSemester = $semesters->firstWhere('id', $intakeSemesterId);
        $size = $members->count();
        $ne = $members->filter(fn (Student $s) => $s->user_id !== null)->count();

        $cells = [];
        foreach ($semesters as $semester) {
            $semesterId = (int) $semester->id;
            $byStatus = array_fill_keys($statuses, 0);

            foreach ($members as $member) {
                $status = $timelines[(int) $member->id][$semesterId] ?? self::STATUS_PENDING;
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            }

            $cells[$semesterId] = $byStatus;
        }

        $lastSemesterId = (int) $semesters->last()->id;
        $current = $cells[$lastSemesterId];

        $everDeferred = $this->everInStatus($members, $timelines, 'deferred');
        $everDropped = $this->everInStatus($members, $timelines, ['dropout', 'dropout_transfer']);

        return [
            'intake_semester_id' => $intakeSemesterId,
            'intake_semester_code' => $intakeSemester?->code ?? (string) $intakeSemesterId,
            'intake_semester_start' => $intakeSemester?->start_date instanceof Carbon
                ? $intakeSemester->start_date->toDateString()
                : (string) $intakeSemester?->start_date,
            'size' => $size,
            'ne' => $ne,
            'cells' => $cells,
            'current' => $current,
            'ever_deferred' => $everDeferred,
            'ever_dropped' => $everDropped,
            'df_rate' => $this->rate($everDeferred, $ne),
            'do_rate' => $this->rate($everDropped, $ne),
            'graduated_rate' => $this->rate($current['graduated'] ?? 0, $ne),
            'pending_rate' => $this->rate($current[self::STATUS_PENDING] ?? 0, $ne),
        ];
    }

    /**
     * @param  Collection<int, Student>  $members
     * @param  array<int, array<int, string>>  $timelines
     * @param  string|list<string>  $status
     */
    private function everInStatus(Collection $members, array $timelines, string|array $status): int
    {
        $wanted = (array) $status;
        $count = 0;

        foreach ($members as $member) {
            foreach ($timelines[(int) $member->id] ?? [] as $seen) {
                if (in_array($seen, $wanted, true)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Bottom margin: what changed between one semester and the next. Derived
     * from the matrix so it can never disagree with the cells above it.
     *
     * @param  Collection<int, Semester>  $semesters
     * @param  list<array<string, mixed>>  $cohorts
     * @return list<array<string, mixed>>
     */
    private function events(Collection $semesters, array $cohorts): array
    {
        $events = [];
        $previousId = null;

        foreach ($semesters as $semester) {
            $semesterId = (int) $semester->id;
            $entered = 0;
            $movements = [];

            foreach ($cohorts as $cohort) {
                if ((int) $cohort['intake_semester_id'] === $semesterId) {
                    $entered += (int) $cohort['size'];
                }

                $now = $cohort['cells'][$semesterId] ?? [];
                $before = $previousId === null ? [] : ($cohort['cells'][$previousId] ?? []);

                foreach ($now as $status => $count) {
                    $delta = (int) $count - (int) ($before[$status] ?? 0);

                    if ($delta > 0 && $status !== self::STATUS_PENDING) {
                        $movements[$status] = ($movements[$status] ?? 0) + $delta;
                    }
                }
            }

            $events[] = [
                'semester_id' => $semesterId,
                'semester_code' => $semester->code,
                'entered' => $entered,
                'moved_in' => $movements,
            ];

            $previousId = $semesterId;
        }

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $cohorts
     * @param  list<string>  $statuses
     * @return array<string, mixed>
     */
    private function totals(array $cohorts, array $statuses): array
    {
        $size = array_sum(array_column($cohorts, 'size'));
        $ne = array_sum(array_column($cohorts, 'ne'));
        $current = array_fill_keys($statuses, 0);

        foreach ($cohorts as $cohort) {
            foreach ($cohort['current'] as $status => $count) {
                $current[$status] = ($current[$status] ?? 0) + (int) $count;
            }
        }

        $everDeferred = array_sum(array_column($cohorts, 'ever_deferred'));
        $everDropped = array_sum(array_column($cohorts, 'ever_dropped'));

        return [
            'size' => $size,
            'ne' => $ne,
            'current' => $current,
            'ever_deferred' => $everDeferred,
            'ever_dropped' => $everDropped,
            'df_rate' => $this->rate($everDeferred, $ne),
            'do_rate' => $this->rate($everDropped, $ne),
            'graduated_rate' => $this->rate($current['graduated'] ?? 0, $ne),
            'pending_rate' => $this->rate($current[self::STATUS_PENDING] ?? 0, $ne),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyTotals(): array
    {
        return [
            'size' => 0, 'ne' => 0, 'current' => [], 'ever_deferred' => 0, 'ever_dropped' => 0,
            'df_rate' => 0.0, 'do_rate' => 0.0, 'graduated_rate' => 0.0, 'pending_rate' => 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function semesterPayload(Semester $semester): array
    {
        return [
            'id' => (int) $semester->id,
            'code' => (string) $semester->code,
            'name' => (string) $semester->name,
            'start_date' => $semester->start_date instanceof Carbon
                ? $semester->start_date->toDateString()
                : (string) $semester->start_date,
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator <= 0 ? 0.0 : round(($numerator / $denominator) * 100, 2);
    }
}
