<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\StudentActionType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Defer-return watchlist report.
 *
 * Lists every student still on hold (`deferred` via ACADEMIC_DEFER, or
 * `pending_course_opening` via WAITING_COURSE_OPENING), bucketed by whether
 * their return semester is overdue, upcoming, or absent (waiting leg has no
 * return semester by design). A row self-clears once resume/dropout/transfer
 * moves the student off the status that put them here — see
 * `StudentActionType::targetStatus()`, the single source of the
 * action-to-status coupling.
 */
class GetDeferReturnWatchlistQuery
{
    private const BUCKETS = ['overdue', 'upcoming', 'waiting'];

    /**
     * @param  array{search?: string|null, bucket?: string|null, semester_id?: int|null, per_page?: int|null}  $filters
     */
    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        $paginator = $this->baseQuery($filters, $campusId)
            ->orderByRaw("field(rows.bucket, 'overdue', 'upcoming', 'waiting')")
            ->orderByDesc('rows.days_elapsed')
            ->paginate($perPage)
            ->withQueryString();

        return $paginator->through(fn (object $row): array => $this->mapRow($row));
    }

    /**
     * Bucket counts, independent of the current page and of the bucket filter.
     *
     * @return array{overdue: int, upcoming: int, waiting: int}
     */
    public function counts(?int $campusId = null): array
    {
        $rows = DB::query()
            ->fromSub($this->unionSub(), 'rows')
            ->when($campusId, fn (Builder $query, $id) => $query->where('rows.campus_id', $id))
            ->groupBy('rows.bucket')
            ->select(['rows.bucket', DB::raw('count(*) as total')])
            ->get();

        $counts = array_fill_keys(self::BUCKETS, 0);
        foreach ($rows as $row) {
            $counts[$row->bucket] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * The unioned, campus-scoped, searchable, bucket-filterable query.
     *
     * Exposed for tests, the export, and reuse; callers normally use handle().
     *
     * @param  array{search?: string|null, bucket?: string|null, semester_id?: int|null}  $filters
     */
    public function baseQuery(array $filters = [], ?int $campusId = null): Builder
    {
        $search = $filters['search'] ?? null;
        $bucket = $filters['bucket'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;

        return DB::query()
            ->fromSub($this->unionSub(), 'rows')
            ->leftJoin('campuses as c', 'c.id', '=', 'rows.campus_id')
            ->when($campusId, fn (Builder $query, $id) => $query->where('rows.campus_id', $id))
            ->when($bucket, fn (Builder $query, $value) => $query->where('rows.bucket', $value))
            // anchor_semester_id is the return semester on the defer leg and the
            // from_semester (on-hold anchor) on the waiting leg — see unionSub().
            // Waiting rows never match a specific return semester by design, so
            // this filter naturally excludes them once a semester is chosen.
            ->when($semesterId, fn (Builder $query, $id) => $query->where('rows.anchor_semester_id', $id))
            ->when($search, function (Builder $query, $term): void {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('rows.student_name', 'like', "%{$term}%")
                        ->orWhere('rows.student_code', 'like', "%{$term}%");
                });
            })
            ->select([
                'rows.bucket',
                'rows.severity',
                'rows.action_id',
                'rows.action_type',
                'rows.student_pk',
                'rows.student_code',
                'rows.student_name',
                'rows.student_status',
                'rows.previous_status',
                'rows.campus_id',
                'c.name as campus_name',
                'c.code as campus_code',
                'rows.anchor_semester_id',
                'rows.anchor_semester_code',
                'rows.anchor_semester_name',
                'rows.anchor_start_date',
                'rows.anchor_end_date',
                'rows.days_elapsed',
            ]);
    }

    /**
     * UNION of the defer leg (bucketed overdue/upcoming by return semester
     * start_date) and the waiting leg (always bucket=waiting, anchored on the
     * from_semester since it has no return semester by design).
     *
     * The `semesters` join uses `DB::table` (not Eloquent), so a soft-deleted
     * anchor semester still joins. Deliberate: the student stays visible on
     * the watchlist instead of silently vanishing because the semester row
     * was trashed.
     */
    private function unionSub(): Builder
    {
        $deferStatus = StudentActionType::ACADEMIC_DEFER->targetStatus();
        $waitingStatus = StudentActionType::WAITING_COURSE_OPENING->targetStatus();

        $defer = DB::table('student_action_logs as sal')
            ->join('students as s', 's.id', '=', 'sal.student_id')
            ->leftJoin('semesters as sem', 'sem.id', '=', 'sal.return_semester_id')
            ->where('sal.action_type', StudentActionType::ACADEMIC_DEFER->value)
            ->where('s.status', $deferStatus)
            ->whereRaw('sal.id = (select max(id) from student_action_logs where student_id = sal.student_id and action_type = ?)', [StudentActionType::ACADEMIC_DEFER->value])
            ->select([
                DB::raw("case when sem.start_date is null then 'upcoming' when sem.start_date <= curdate() then 'overdue' else 'upcoming' end as bucket"),
                DB::raw("case when sem.start_date is null or sem.start_date > curdate() then null when sem.end_date is null then null when sem.end_date >= curdate() then 'in_semester' else 'semester_ended' end as severity"),
                'sal.id as action_id',
                'sal.action_type as action_type',
                's.id as student_pk',
                's.student_id as student_code',
                's.full_name as student_name',
                's.status as student_status',
                'sal.previous_status as previous_status',
                's.campus_id as campus_id',
                'sem.id as anchor_semester_id',
                'sem.code as anchor_semester_code',
                'sem.name as anchor_semester_name',
                // Repo date convention is d/m/Y (see StudentActionLog::getFormattedSignedAtAttribute
                // et al.) — formatted here so the frontend and the export both just
                // display the string, no reformatting on either side.
                DB::raw("date_format(sem.start_date, '%d/%m/%Y') as anchor_start_date"),
                DB::raw("date_format(sem.end_date, '%d/%m/%Y') as anchor_end_date"),
                DB::raw('datediff(curdate(), sem.start_date) as days_elapsed'),
            ]);

        $waiting = DB::table('student_action_logs as sal')
            ->join('students as s', 's.id', '=', 'sal.student_id')
            ->leftJoin('semesters as sem', 'sem.id', '=', 'sal.from_semester_id')
            ->where('sal.action_type', StudentActionType::WAITING_COURSE_OPENING->value)
            ->where('s.status', $waitingStatus)
            ->whereRaw('sal.id = (select max(id) from student_action_logs where student_id = sal.student_id and action_type = ?)', [StudentActionType::WAITING_COURSE_OPENING->value])
            ->select([
                DB::raw("'waiting' as bucket"),
                DB::raw('cast(null as char(20)) as severity'),
                'sal.id as action_id',
                'sal.action_type as action_type',
                's.id as student_pk',
                's.student_id as student_code',
                's.full_name as student_name',
                's.status as student_status',
                'sal.previous_status as previous_status',
                's.campus_id as campus_id',
                'sem.id as anchor_semester_id',
                'sem.code as anchor_semester_code',
                'sem.name as anchor_semester_name',
                // Repo date convention is d/m/Y (see StudentActionLog::getFormattedSignedAtAttribute
                // et al.) — formatted here so the frontend and the export both just
                // display the string, no reformatting on either side.
                DB::raw("date_format(sem.start_date, '%d/%m/%Y') as anchor_start_date"),
                DB::raw("date_format(sem.end_date, '%d/%m/%Y') as anchor_end_date"),
                DB::raw('datediff(curdate(), sem.start_date) as days_elapsed'),
            ]);

        return $defer->unionAll($waiting);
    }

    /**
     * @return array{
     *     bucket: string,
     *     severity: string|null,
     *     action_id: int,
     *     action_type: string,
     *     student_pk: int,
     *     student_code: string,
     *     student_name: string,
     *     student_status: string,
     *     previous_status: string|null,
     *     campus: array{id: int, name: string|null, code: string|null}|null,
     *     anchor_semester: array{id: int, code: string|null, name: string|null}|null,
     *     anchor_start_date: string|null,
     *     anchor_end_date: string|null,
     *     days_elapsed: int|null
     * }
     *
     * anchor_start_date / anchor_end_date arrive already formatted d/m/Y
     * (see unionSub()) — pass through as-is, no reformatting here or on the frontend.
     */
    private function mapRow(object $row): array
    {
        return [
            'bucket' => $row->bucket,
            'severity' => $row->severity,
            'action_id' => (int) $row->action_id,
            'action_type' => $row->action_type,
            'student_pk' => (int) $row->student_pk,
            'student_code' => (string) $row->student_code,
            // Mirror Student::getFullNameAttribute (uppercase, Vietnamese-safe)
            // so this report displays names the same way every other surface does.
            'student_name' => mb_strtoupper((string) $row->student_name, 'UTF-8'),
            'student_status' => (string) $row->student_status,
            // Status the student held right before this defer/waiting action
            // was recorded (student_action_logs.previous_status, set at
            // action-record time by RecordStudentActionAction).
            'previous_status' => $row->previous_status === null ? null : (string) $row->previous_status,
            'campus' => $row->campus_id === null ? null : [
                'id' => (int) $row->campus_id,
                'name' => $row->campus_name,
                'code' => $row->campus_code,
            ],
            'anchor_semester' => $row->anchor_semester_id === null ? null : [
                'id' => (int) $row->anchor_semester_id,
                'code' => $row->anchor_semester_code,
                'name' => $row->anchor_semester_name,
            ],
            'anchor_start_date' => $row->anchor_start_date,
            'anchor_end_date' => $row->anchor_end_date,
            'days_elapsed' => $row->days_elapsed === null ? null : (int) $row->days_elapsed,
        ];
    }
}
