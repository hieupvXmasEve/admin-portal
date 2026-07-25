<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Shared\Contracts\Academic\DTO\StudentDeferActionSummary;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detects deferred students who still retain course registrations for the semester.
 */
final class BillingExceptionDeferredEnrollmentMatcher
{
    /** @var array<string, list<StudentDeferActionSummary>> */
    private array $actionsByScope = [];

    public function __construct(
        private readonly StudentDeferLifecycleReader $deferLifecycle,
    ) {}

    public function applyMatchingConstraint(Builder $query, ?int $filterSemesterId = null): void
    {
        [$activeStudentIds, $sql, $bindings] = $this->activeDeferEvidence($filterSemesterId);
        if ($activeStudentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->whereIn('course_registrations.student_id', $activeStudentIds)
            ->where(function (Builder $evidenceQuery) use ($filterSemesterId, $sql, $bindings): void {
                $evidenceQuery->whereExists(
                    fn (Builder $caseQuery) => $this->applyDeferCaseExistsConstraint($caseQuery, $filterSemesterId),
                );

                if ($sql !== null) {
                    $evidenceQuery->orWhereRaw($sql, $bindings);
                }
            });
    }

    public function applyNonMatchingConstraint(Builder $query, ?int $filterSemesterId = null): void
    {
        [$activeStudentIds, $sql, $bindings] = $this->activeDeferEvidence($filterSemesterId);
        if ($activeStudentIds === []) {
            return;
        }

        $query->where(function (Builder $nonMatchingQuery) use ($activeStudentIds, $filterSemesterId, $sql, $bindings): void {
            $nonMatchingQuery
                ->whereNotIn('course_registrations.student_id', $activeStudentIds)
                ->orWhere(function (Builder $missingEvidenceQuery) use ($filterSemesterId, $sql, $bindings): void {
                    $missingEvidenceQuery->whereNotExists(
                        fn (Builder $caseQuery) => $this->applyDeferCaseExistsConstraint($caseQuery, $filterSemesterId),
                    );

                    if ($sql !== null) {
                        $missingEvidenceQuery->whereRaw("NOT ({$sql})", $bindings);
                    }
                });
        });
    }

    private function applyDeferCaseExistsConstraint(Builder $query, ?int $filterSemesterId): void
    {
        $query->select(DB::raw(1))
            ->from('defer_cases')
            ->whereColumn('defer_cases.student_id', 'course_registrations.student_id')
            ->whereColumn('defer_cases.semester_id', 'course_registrations.semester_id');

        if ($filterSemesterId !== null) {
            $query->where('defer_cases.semester_id', $filterSemesterId);
        }
    }

    /** @return array{0: list<int>, 1: string|null, 2: list<int>} */
    private function activeDeferEvidence(?int $filterSemesterId): array
    {
        $scopeKey = $filterSemesterId === null ? 'all' : (string) $filterSemesterId;
        $actions = $this->actionsByScope[$scopeKey]
            ??= $this->deferLifecycle->listDeferActions($filterSemesterId);

        $activeActions = collect($actions)
            ->filter(static fn (StudentDeferActionSummary $action): bool => $action->currentlyDeferred);
        $activeStudentIds = $activeActions
            ->pluck('studentId')
            ->unique()
            ->values()
            ->all();
        $pairs = $activeActions
            ->filter(static fn (StudentDeferActionSummary $action): bool => $action->fromSemesterId !== null)
            ->map(static fn (StudentDeferActionSummary $action): array => [
                $action->studentId,
                $action->fromSemesterId,
            ])
            ->unique(static fn (array $pair): string => "{$pair[0]}:{$pair[1]}")
            ->values();

        if ($pairs->isEmpty()) {
            return [$activeStudentIds, null, []];
        }

        $sql = $pairs
            ->map(static fn (): string => '(course_registrations.student_id = ? AND course_registrations.semester_id = ?)')
            ->implode(' OR ');

        return [$activeStudentIds, $sql, $pairs->flatten()->all()];
    }
}
