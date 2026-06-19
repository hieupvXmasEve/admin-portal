<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Console\Command;
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
 *  - Học lại (retake): retake enrollments in `course_registrations.is_retake`
 *    (→ offering → unit → the student's failed record for that unit) plus the
 *    direct `course_retake_registrations.original_academic_record_id` link.
 *  - Thi lại (resit): the direct `exam_resit_attempts.academic_record_id` link,
 *    plus a heuristic over PAID resit fees (see below).
 *
 * Thi lại fee → record heuristic (owner decision): a paid PTL fee proves a
 * student sat a resit, but the DNG/charge layer carries NO structured unit link
 * (`item_id` encodes only student + fee type; charges have no `unit_id`; legacy
 * PTL has no Academic source). So for each student with a paid PTL fee, if they
 * have EXACTLY ONE outstanding eligible failed record (grade-final, no
 * `failure_reason`, not already covered by a retake/resit link) it is labelled;
 * if they have zero or several, they are reported for manual mapping rather than
 * guessed. (Failures are in the original term while the fee is paid in the
 * operation term, so the heuristic is student-scoped, not payment-term-scoped.)
 *
 * Idempotent: only rows with a null `failure_reason` are written.
 */
class BackfillFailureReasonCommand extends Command
{
    protected $signature = 'academic:backfill-failure-reason {--dry-run : Report counts without writing}';

    protected $description = 'Backfill grade_failed on failed records of students who already did retake/resit (ACAD-RET-001, non-flipping)';

    /** DNG fee type code for thi lại (exam resit). Học lại is HL. */
    private const PTL = 'PTL';

    /** DNG request statuses that mean the fee has actually been paid. */
    private const PAID_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $linkedIds = $this->retakeLinkedRecordIds()
            ->merge($this->resitLinkedRecordIds())
            ->unique()
            ->values();

        $ambiguousResit = 0;
        $resitHeuristicIds = $this->paidResitHeuristicRecordIds($linkedIds, $ambiguousResit);

        $recordIds = $linkedIds->merge($resitHeuristicIds)->unique()->values();

        $pending = AcademicRecord::query()
            ->whereIn('id', $recordIds)
            ->where('grade_status', 'final')
            ->where('is_passed', false)
            ->whereNull('failure_reason');

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

        if ($ambiguousResit > 0) {
            $this->warn(
                "Thi lại (PTL): {$ambiguousResit} student(s) paid a resit fee but have zero or several outstanding "
                .'failed records — left for manual mapping (no unambiguous record to label).'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Failed academic-record ids reachable from a course retake (`học lại`).
     *
     * @return Collection<int, int>
     */
    private function retakeLinkedRecordIds(): Collection
    {
        // (a) Direct link from the modern retake source.
        $direct = CourseRetakeRegistration::query()
            ->whereNotNull('original_academic_record_id')
            ->distinct()
            ->pluck('original_academic_record_id');

        // (b) Retake enrollments → (student, unit) of the original failure.
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
     * Heuristic mapping of paid resit (PTL) fees to a failed record: label only
     * when the paying student has exactly one outstanding eligible failed record
     * that is not already covered by a structured link. Ambiguous students (zero
     * or several) are counted in $ambiguous for manual follow-up.
     *
     * @param  Collection<int, int>  $excludeIds  Records already covered by a structured link.
     * @return Collection<int, int>
     */
    private function paidResitHeuristicRecordIds(Collection $excludeIds, int &$ambiguous): Collection
    {
        $studentIds = DngPaymentRequest::query()
            ->where('fee_type', self::PTL)
            ->whereIn('status', self::PAID_STATUSES)
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $byStudent = AcademicRecord::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('is_passed', false)
            ->where('grade_status', 'final')
            ->whereNull('failure_reason')
            ->when($excludeIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $excludeIds->all()))
            ->get(['id', 'student_id'])
            ->groupBy('student_id');

        $single = collect();
        foreach ($studentIds as $studentId) {
            $records = $byStudent->get($studentId) ?? collect();

            if ($records->count() === 1) {
                $single->push((int) $records->first()->id);
            } elseif ($records->count() > 1) {
                $ambiguous++;
            }
        }

        return $single->unique()->values();
    }
}
