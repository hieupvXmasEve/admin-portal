<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * ACAD-RET-001 — scoped, forward-only backfill of `failure_reason`.
 *
 * Historical finalizations were grade-only and their pass/fail results are
 * accepted as correct, so this command never re-decides anything and never
 * touches `is_passed`. It only fills `failure_reason = grade_failed` on the
 * failed academic records of students who ALREADY went through a remediation,
 * detected from the most accurate signals:
 *
 *  - Học lại (retake): `course_registrations.is_retake` (→ offering → unit → the
 *    student's failed record for that unit) plus the direct
 *    `course_retake_registrations.original_academic_record_id` link.
 *  - Thi lại (resit): the direct `exam_resit_attempts.academic_record_id` link,
 *    plus paid PTL fees mapped by resit unit (below).
 *
 * Paid PTL fees prove a student sat a resit but carry NO structured unit link
 * (`item_id` encodes student + fee type only; charges have no `unit_id`; legacy
 * PTL has no Academic source). Per the Academic owner, exam resit applies only to
 * the resit-eligible units (default TEC002, TEC001), and one paid fee maps to one
 * resit unit, so for each paying student the command labels the record of the
 * FIRST resit unit (in priority order — TEC002 by default) they failed, not every
 * resit failure. When they no longer have a failed resit-unit record (almost always
 * because they already passed the resit), the command falls back to: (1) a unit
 * named in the paid PTL description, else (2) TEC002 when the student studied both
 * resit units, else (3) the single resit unit they studied. Only students with no
 * resit-unit academic record at all are reported as anomalies. The unit set
 * and its priority are overridable via --resit-units; the owner fixes any wrong
 * default by hand afterwards.
 *
 * `--reset` clears only the labels this backfill wrote (snapshot.backfilled =
 * true), never the reasons written by course finalization, so the backfill can be
 * re-applied deterministically after a rule change.
 *
 * Idempotent: only rows with a null `failure_reason` are written.
 */
class BackfillFailureReasonCommand extends Command
{
    protected $signature = 'academic:backfill-failure-reason
        {--dry-run : Report counts without writing}
        {--reset : Clear only the labels this backfill previously wrote, then stop}
        {--resit-units= : Comma-separated resit-eligible unit codes in priority order (default TEC002,TEC001)}
        {--report-unmapped : List paid-PTL students with no failed resit-unit record (anomalies)}';

    protected $description = 'Backfill grade_failed on failed records of students who already did retake/resit (ACAD-RET-001, non-flipping)';

    /** DNG fee type code for thi lại (exam resit). Học lại is HL. */
    private const PTL = 'PTL';

    /**
     * Units that exam resit (thi lại) applies to, in priority order. TEC002 is the
     * default when a student failed several resit units (one fee → one unit).
     */
    private const RESIT_UNIT_CODES = ['TEC002', 'TEC001'];

    /** DNG request statuses that mean the fee has actually been paid. */
    private const PAID_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resitUnits = $this->resitUnits();

        if ((bool) $this->option('reset')) {
            return $this->resetBackfilled($dryRun);
        }

        $linkedIds = $this->retakeLinkedRecordIds()
            ->merge($this->resitLinkedRecordIds())
            ->unique()
            ->values();

        if ((bool) $this->option('report-unmapped')) {
            $this->reportUnmapped($resitUnits);

            return self::SUCCESS;
        }

        [$ptlRecordIds, $ptlPassedFallbackIds] = $this->paidResitUnitRecordIds($linkedIds, $resitUnits);

        $recordIds = $linkedIds
            ->merge($ptlRecordIds)
            ->unique()
            ->values();

        $pending = AcademicRecord::query()
            ->whereIn('id', $recordIds)
            ->where('grade_status', 'final')
            ->whereNull('failure_reason')
            ->where(function (Builder $query) use ($ptlPassedFallbackIds): void {
                $query->where('is_passed', false);

                if ($ptlPassedFallbackIds->isNotEmpty()) {
                    $query->orWhereIn('id', $ptlPassedFallbackIds->all());
                }
            });

        $total = (clone $pending)->count();

        if ($total === 0) {
            $this->info('No retake/resit-linked failed records need backfilling.');
        } elseif ($dryRun) {
            $this->warn("[dry-run] {$total} retake/resit-linked failed record(s) would be labelled grade_failed.");
        } else {
            $updated = 0;
            $pending->chunkById(500, function (Collection $records) use (&$updated): void {
                foreach ($records as $record) {
                    $record->update([
                        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
                        'failure_reason_snapshot' => [
                            'backfilled' => true,
                            'source' => 'legacy_grade_only',
                            'final_score' => $record->final_percentage !== null ? (float) $record->final_percentage : null,
                            'evaluated_at' => now()->format(DATE_ATOM),
                        ],
                    ]);
                    $updated++;
                }
            });
            $this->info("Labelled {$updated} retake/resit-linked failed record(s) as grade_failed.");
        }

        $anomalies = $this->resitAnomalyStudentIds($resitUnits)->count();
        if ($anomalies > 0) {
            $this->warn(
                "Thi lại (PTL): {$anomalies} student(s) paid a resit fee but have no failed "
                .implode('/', $resitUnits).' record (usually already passed the resit) — run --report-unmapped to inspect.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Clear only the labels written by a previous backfill run.
     */
    private function resetBackfilled(bool $dryRun): int
    {
        $query = AcademicRecord::query()
            ->whereNotNull('failure_reason')
            ->where('failure_reason_snapshot->backfilled', true);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No backfilled labels to reset.');
        } elseif ($dryRun) {
            $this->warn("[dry-run] {$count} backfilled label(s) would be reset to null.");
        } else {
            $query->update(['failure_reason' => null, 'failure_reason_snapshot' => null]);
            $this->info("Reset {$count} backfilled label(s) to null (finalization-written reasons untouched).");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resitUnits(): array
    {
        $option = (string) $this->option('resit-units');

        if ($option !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $option))));
        }

        return self::RESIT_UNIT_CODES;
    }

    /**
     * Failed academic-record ids reachable from a course retake (`học lại`).
     *
     * @return Collection<int, int>
     */
    private function retakeLinkedRecordIds(): Collection
    {
        $direct = CourseRetakeRegistration::query()
            ->whereNotNull('original_academic_record_id')
            ->distinct()
            ->pluck('original_academic_record_id');

        $pairs = CourseRegistration::query()
            ->where('course_registrations.is_retake', true)
            ->whereNotNull('course_registrations.course_offering_id')
            ->join('course_offerings', 'course_offerings.id', '=', 'course_registrations.course_offering_id')
            ->whereNotNull('course_offerings.unit_id')
            ->select('course_registrations.student_id', 'course_offerings.unit_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return $direct->map(fn ($id) => (int) $id);
        }

        $pairKeys = $pairs->map(fn ($p) => $p->student_id.'|'.$p->unit_id)->unique()->all();

        $pairIds = AcademicRecord::query()
            ->whereIn('student_id', $pairs->pluck('student_id')->unique()->all())
            ->whereIn('unit_id', $pairs->pluck('unit_id')->unique()->all())
            ->where('is_passed', false)
            ->where('grade_status', 'final')
            ->get(['id', 'student_id', 'unit_id'])
            ->filter(fn ($r) => in_array($r->student_id.'|'.$r->unit_id, $pairKeys, true))
            ->pluck('id');

        return $direct->merge($pairIds)->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Failed academic-record ids reachable from a recorded exam resit (`thi lại`).
     *
     * @return Collection<int, int>
     */
    private function resitLinkedRecordIds(): Collection
    {
        return ExamResitAttempt::query()
            ->whereNotNull('academic_record_id')
            ->distinct()
            ->pluck('academic_record_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * One resit unit per paid-PTL student. Prefer the failed record of the first
     * resit unit (priority order) the student failed; when none remain (usually
     * because the resit was passed), fall back to the fee description unit or
     * TEC002 when both resit units were studied.
     *
     * @param  Collection<int, int>  $excludeIds
     * @param  list<string>  $resitUnits
     * @return array{0: Collection<int, int>, 1: Collection<int, int>} All PTL-mapped ids, then passed-record fallback ids
     */
    private function paidResitUnitRecordIds(Collection $excludeIds, array $resitUnits): array
    {
        $studentIds = $this->paidResitStudentIds();

        if ($studentIds->isEmpty()) {
            return [collect(), collect()];
        }

        // Load ALL failed resit-unit records (labelled or not) so the priority unit
        // is determined from the full picture. Filtering to unlabelled here would let
        // an already-labelled TEC002 drop out and the priority fall through to TEC001
        // on a later run — breaking the one-unit-per-student rule (and idempotency).
        $failedByStudent = AcademicRecord::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('is_passed', false)
            ->where('grade_status', 'final')
            ->whereHas('unit', fn (Builder $q) => $q->whereIn('code', $resitUnits))
            ->with('unit:id,code')
            ->get(['id', 'student_id', 'unit_id', 'failure_reason', 'is_passed'])
            ->groupBy('student_id');

        $studiedByStudent = AcademicRecord::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('grade_status', 'final')
            ->whereHas('unit', fn (Builder $q) => $q->whereIn('code', $resitUnits))
            ->with('unit:id,code')
            ->get(['id', 'student_id', 'unit_id', 'failure_reason', 'is_passed'])
            ->groupBy('student_id');

        $ids = collect();
        $passedFallbackIds = collect();
        $mappedStudentIds = collect();

        foreach ($failedByStudent as $studentId => $records) {
            $mappedStudentIds->push((int) $studentId);

            foreach ($resitUnits as $code) {
                $matched = $records->filter(fn (AcademicRecord $r) => $r->unit?->code === $code);
                if ($matched->isEmpty()) {
                    continue;
                }

                // This unit is the student's resit unit. Only its still-unlabelled,
                // non-excluded records are written; we never fall through to a
                // lower-priority unit, even if this one is already labelled.
                $ids = $ids->merge(
                    $matched
                        ->filter(fn (AcademicRecord $r) => $r->failure_reason === null && ! $excludeIds->contains((int) $r->id))
                        ->pluck('id')
                );

                break; // one resit unit per student (priority order)
            }
        }

        $unmappedStudentIds = $studentIds->diff($mappedStudentIds->unique())->values();

        foreach ($unmappedStudentIds as $studentId) {
            $records = $studiedByStudent->get($studentId, collect());
            if ($records->isEmpty()) {
                continue;
            }

            $targetCode = $this->resolvePassedResitUnitCode((int) $studentId, $records, $resitUnits);
            if ($targetCode === null) {
                continue;
            }

            $target = $records->first(fn (AcademicRecord $r) => $r->unit?->code === $targetCode);
            if ($target === null
                || $target->failure_reason !== null
                || $excludeIds->contains((int) $target->id)) {
                continue;
            }

            $ids->push((int) $target->id);

            if ($target->is_passed) {
                $passedFallbackIds->push((int) $target->id);
            }
        }

        return [
            $ids->map(fn ($id) => (int) $id)->unique()->values(),
            $passedFallbackIds->map(fn ($id) => (int) $id)->unique()->values(),
        ];
    }

    /**
     * Pick the resit unit for a paid-PTL student who no longer has a failed
     * resit-unit record: fee description unit, else TEC002 when both were studied.
     *
     * @param  Collection<int, AcademicRecord>  $studiedRecords
     * @param  list<string>  $resitUnits
     */
    private function resolvePassedResitUnitCode(int $studentId, Collection $studiedRecords, array $resitUnits): ?string
    {
        $studiedCodes = $studiedRecords
            ->map(fn (AcademicRecord $r) => $r->unit?->code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($this->paidResitUnitCodesFromDescriptions($studentId, $resitUnits) as $code) {
            if (in_array($code, $studiedCodes, true)) {
                return $code;
            }
        }

        $studiedResitUnits = array_values(array_intersect($resitUnits, $studiedCodes));

        if (count($studiedResitUnits) >= 2) {
            return $resitUnits[0];
        }

        return $studiedResitUnits[0] ?? null;
    }

    /**
     * @param  list<string>  $resitUnits
     * @return list<string>
     */
    private function paidResitUnitCodesFromDescriptions(int $studentId, array $resitUnits): array
    {
        $descriptions = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->where('fee_type', self::PTL)
            ->whereIn('status', self::PAID_STATUSES)
            ->pluck('description')
            ->filter()
            ->unique();

        $codes = [];
        foreach ($descriptions as $description) {
            foreach ($resitUnits as $code) {
                if (preg_match('/\b'.preg_quote($code, '/').'\b/i', (string) $description) === 1) {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Paid-PTL students with no resit-unit academic record that the mapper can target.
     *
     * @param  list<string>  $resitUnits
     * @return Collection<int, int>
     */
    private function resitAnomalyStudentIds(array $resitUnits): Collection
    {
        $studentIds = $this->paidResitStudentIds();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $withStudiedResitUnit = AcademicRecord::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('grade_status', 'final')
            ->whereHas('unit', fn (Builder $q) => $q->whereIn('code', $resitUnits))
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id);

        return $studentIds->diff($withStudiedResitUnit)->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function paidResitStudentIds(): Collection
    {
        return DngPaymentRequest::query()
            ->where('fee_type', self::PTL)
            ->whereIn('status', self::PAID_STATUSES)
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Read-only diagnostic: list paid-PTL students with no failed resit-unit
     * record, with their other failures and fee description for context.
     *
     * @param  list<string>  $resitUnits
     */
    private function reportUnmapped(array $resitUnits): void
    {
        $anomalyIds = $this->resitAnomalyStudentIds($resitUnits);

        if ($anomalyIds->isEmpty()) {
            $this->info('All paid-PTL (thi lại) students have a failed '.implode('/', $resitUnits).' record to label.');

            return;
        }

        $students = Student::query()
            ->whereIn('id', $anomalyIds->all())
            ->get(['id', 'student_id'])
            ->keyBy('id');

        $resitRecords = AcademicRecord::query()
            ->whereIn('student_id', $anomalyIds->all())
            ->where('grade_status', 'final')
            ->whereHas('unit', fn (Builder $q) => $q->whereIn('code', $resitUnits))
            ->with('unit:id,code')
            ->get(['id', 'student_id', 'unit_id', 'is_passed', 'final_percentage'])
            ->groupBy('student_id');

        $rows = [];
        foreach ($anomalyIds as $studentId) {
            $blob = DngPaymentRequest::query()
                ->where('student_id', $studentId)
                ->where('fee_type', self::PTL)
                ->whereIn('status', self::PAID_STATUSES)
                ->pluck('description')
                ->filter()
                ->unique()
                ->implode(' | ');

            $rows[] = [
                $students->get($studentId)?->student_id ?? (string) $studentId,
                $blob !== '' ? $blob : '—',
                ($resitRecords->get($studentId) ?? collect())
                    ->map(fn ($r) => ($r->unit?->code ?? 'unit '.$r->unit_id)
                        .' ('.(float) $r->final_percentage.'%, '
                        .($r->is_passed ? 'passed' : 'failed').')')
                    ->implode('; ') ?: 'no resit-unit academic record',
            ];
        }

        $this->warn(count($rows).' paid-PTL student(s) have no '.implode('/', $resitUnits).' academic record to map:');
        $this->table(['Student', 'Resit fee description', 'Resit-unit records'], $rows);
    }
}
