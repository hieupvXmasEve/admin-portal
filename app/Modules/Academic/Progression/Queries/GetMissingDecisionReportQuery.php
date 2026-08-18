<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\AcademicProgressionEventType;
use App\Enums\StudentActionType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Missing-decision report (ADR-0048).
 *
 * Lists requires-decision transitions — drawn from BOTH lifecycle streams
 * (status actions and EGC progression events) — that still lack an authorizing
 * Decision. Attaching a Decision (setting decision_id) removes the row.
 *
 * Mirrors the missing-documents report under academic-progression: a single
 * campus-scoped, searchable, paginated list. admission_deferral and pure
 * progression records (English-level change, IELTS) never appear here because
 * they are excluded by the requires-decision classification on the enums.
 */
class GetMissingDecisionReportQuery
{
    /**
     * @param  array{search?: string|null, per_page?: int|null}  $filters
     */
    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        $paginator = $this->baseQuery($filters, $campusId)
            ->orderByDesc('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        return $paginator->through(fn (object $row): array => $this->mapRow($row));
    }

    /**
     * The unioned, campus-scoped, searchable query of missing-decision rows.
     *
     * Exposed for tests and reuse; callers normally use handle().
     *
     * @param  array{search?: string|null}  $filters
     */
    public function baseQuery(array $filters = [], ?int $campusId = null): Builder
    {
        $search = $filters['search'] ?? null;

        return DB::query()
            ->fromSub($this->unionSub(), 'rows')
            ->leftJoin('campuses as c', 'c.id', '=', 'rows.campus_id')
            ->when($campusId, fn (Builder $query, $id) => $query->where('rows.campus_id', $id))
            ->when($search, function (Builder $query, $term): void {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('rows.student_name', 'like', "%{$term}%")
                        ->orWhere('rows.student_code', 'like', "%{$term}%");
                });
            })
            ->select([
                'rows.source',
                'rows.source_id',
                'rows.transition_type',
                'rows.occurred_at',
                'rows.student_pk',
                'rows.student_code',
                'rows.student_name',
                'rows.student_status',
                'rows.campus_id',
                'c.name as campus_name',
                'c.code as campus_code',
            ]);
    }

    /**
     * UNION of requires-decision transitions lacking a decision, from both
     * streams, normalised to a common column shape.
     */
    private function unionSub(): Builder
    {
        $actions = DB::table('student_action_logs as sal')
            ->join('students as s', 's.id', '=', 'sal.student_id')
            ->whereIn('sal.action_type', StudentActionType::requiresDecisionValues())
            ->whereNull('sal.decision_id')
            ->whereNull('s.deleted_at')
            ->select([
                DB::raw("'action' as source"),
                'sal.id as source_id',
                'sal.action_type as transition_type',
                DB::raw('COALESCE(sal.effective_at, sal.created_at) as occurred_at'),
                's.id as student_pk',
                's.student_id as student_code',
                's.full_name as student_name',
                's.status as student_status',
                's.campus_id as campus_id',
            ]);

        $progression = DB::table('academic_progression_events as ape')
            ->join('students as s', 's.id', '=', 'ape.student_id')
            ->whereIn('ape.event_type', AcademicProgressionEventType::requiresDecisionValues())
            ->whereNull('ape.decision_id')
            ->whereNull('s.deleted_at')
            ->select([
                DB::raw("'progression' as source"),
                'ape.id as source_id',
                'ape.event_type as transition_type',
                'ape.effective_at as occurred_at',
                's.id as student_pk',
                's.student_id as student_code',
                's.full_name as student_name',
                's.status as student_status',
                's.campus_id as campus_id',
            ]);

        return $actions->unionAll($progression);
    }

    /**
     * @return array{
     *     id: string,
     *     source: string,
     *     source_id: int,
     *     transition_type: string,
     *     transition_label: string,
     *     occurred_at: string|null,
     *     student: array{id: int, student_id: string, full_name: string, status: string, campus: array{id: int, name: string|null, code: string|null}|null}
     * }
     */
    private function mapRow(object $row): array
    {
        return [
            'id' => $row->source.'-'.$row->source_id,
            'source' => $row->source,
            'source_id' => (int) $row->source_id,
            'transition_type' => $row->transition_type,
            'transition_label' => $this->transitionLabel($row->source, $row->transition_type),
            'occurred_at' => $row->occurred_at,
            'student' => [
                'id' => (int) $row->student_pk,
                'student_id' => (string) $row->student_code,
                // Mirror Student::getFullNameAttribute (uppercase, Vietnamese-safe)
                // so this report displays names the same way every other surface does.
                'full_name' => mb_strtoupper((string) $row->student_name, 'UTF-8'),
                'status' => (string) $row->student_status,
                'campus' => $row->campus_id === null ? null : [
                    'id' => (int) $row->campus_id,
                    'name' => $row->campus_name,
                    'code' => $row->campus_code,
                ],
            ],
        ];
    }

    private function transitionLabel(string $source, string $transitionType): string
    {
        return $source === 'action'
            ? StudentActionType::from($transitionType)->labelEn()
            : AcademicProgressionEventType::from($transitionType)->labelEn();
    }
}
