<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Replays student_action_logs into the status each student held in each semester.
 *
 * `students.status` is the live value and jumps ahead as soon as a future-dated
 * action is recorded, so it cannot answer "where did this student stand in term
 * X". Replaying the log can, and every surface that asks that question resolves
 * it here — two implementations would be free to drift apart, which is the
 * defect this reporting stack was rebuilt to remove.
 */
class LifecycleStatusTimeline
{
    /** Status a student holds before any lifecycle action takes effect. */
    public const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly LifecycleSemesterAnchor $anchor,
    ) {}

    /**
     * @param  Collection<int, Student>|list<int>  $students
     * @param  Collection<int, Semester>  $semesters
     * @return array<int, array<int, string>> [student_id => [semester_id => status]]
     */
    public function forStudents(Collection|array $students, Collection $semesters): array
    {
        $studentIds = $students instanceof Collection
            ? $students->pluck('id')->map(static fn ($id): int => (int) $id)->all()
            : array_map('intval', $students);

        if ($studentIds === [] || $semesters->isEmpty()) {
            return [];
        }

        $logsByStudent = StudentActionLog::query()
            ->whereIn('student_id', $studentIds)
            ->with(LifecycleSemesterAnchor::RELATIONS)
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        $timelines = [];

        foreach ($studentIds as $studentId) {
            $transitions = $this->transitions($logsByStudent->get($studentId) ?? collect());
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

            $timelines[$studentId] = $series;
        }

        return $timelines;
    }

    /**
     * Student ids whose replayed status in `$semester` matches `$status`.
     *
     * @param  Collection<int, Student>|list<int>  $students
     * @return list<int>
     */
    public function idsInStatusAt(Collection|array $students, Semester $semester, string $status): array
    {
        $timelines = $this->forStudents($students, collect([$semester]));
        $semesterId = (int) $semester->id;

        return array_values(array_keys(array_filter(
            $timelines,
            static fn (array $series): bool => ($series[$semesterId] ?? self::STATUS_PENDING) === $status,
        )));
    }

    /**
     * @param  Collection<int, StudentActionLog>  $logs
     * @return list<array{start: Carbon, status: string}>
     */
    private function transitions(Collection $logs): array
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

        usort($transitions, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $transitions;
    }
}
