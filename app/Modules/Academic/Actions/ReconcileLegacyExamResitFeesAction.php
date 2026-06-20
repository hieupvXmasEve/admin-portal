<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Console\Commands\Academic\ReconcileLegacyExamResitFeesCommand;
use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ACAD-RET-001 Slice 6 — reconcile legacy `exam_resit_fee` charges into Academic
 * exam-resit sources.
 *
 * A "legacy" charge is an ACTIVE `exam_resit_fee` FinanceCharge that predates the
 * exam-resit source contract (slices 1–5), so it is NOT linked to any
 * ExamResitAttempt (its `source_type` is not ExamResitAttempt and no attempt
 * references it via `finance_charge_id`). Finance Reporting must not treat such a
 * charge as expected-fee completeness evidence until it has a queryable Academic
 * source (design.md "Finance Reporting Dependency").
 *
 * Each legacy charge is matched to exactly ONE exam-resit-eligible failed academic
 * record of the same student (grade-fail lane only; attendance/both fail route to
 * course retake and are ineligible — mirrors CreateExamResitAttemptAction). The
 * unit is resolved from a unit code named in the charge description, otherwise from
 * a single eligible candidate. A safe match creates a legacy-linked ExamResitAttempt
 * and repoints the charge to it; an unsafe one (no eligible record, or ambiguous
 * units) is reported as an exception instead of guessing an Academic source.
 *
 * Payment state is derived from canonical Finance evidence (FinanceCharge::is_fully_paid),
 * never asserted. Idempotent: once a charge is repointed it is no longer "legacy".
 *
 * @see ReconcileLegacyExamResitFeesCommand thin CLI wrapper.
 * @see CreateExamResitAttemptAction for the live (non-legacy) eligibility contract.
 */
class ReconcileLegacyExamResitFeesAction
{
    public const REASON_NO_ELIGIBLE_RECORD = 'no_eligible_failed_record';

    public const REASON_AMBIGUOUS_UNITS = 'ambiguous_multiple_units';

    /**
     * @return array{checked:int,reconciled:int,exceptions:int,details:array<int,array<string,mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $result = [
            'checked' => 0,
            'reconciled' => 0,
            'exceptions' => 0,
            'details' => [],
        ];

        foreach ($this->legacyCharges() as $charge) {
            $result['checked']++;

            $match = $this->matchEligibleRecord($charge);

            if ($match instanceof AcademicRecord) {
                $detail = $dryRun
                    ? $this->dryRunReconcileDetail($charge, $match)
                    : $this->reconcile($charge, $match);

                $result['reconciled']++;
                $result['details'][] = $detail;

                continue;
            }

            $result['exceptions']++;
            $result['details'][] = [
                'charge_id' => $charge->id,
                'student_id' => $charge->student_id,
                'status' => 'exception',
                'reason' => $match, // string reason code
                'amount' => (float) $charge->amount,
                'description' => $charge->description,
            ];
        }

        return $result;
    }

    /**
     * Active `exam_resit_fee` charges that are not yet linked to an Academic source.
     *
     * @return Collection<int, FinanceCharge>
     */
    private function legacyCharges(): Collection
    {
        $linkedChargeIds = ExamResitAttempt::query()
            ->whereNotNull('finance_charge_id')
            ->distinct()
            ->pluck('finance_charge_id')
            ->all();

        return FinanceCharge::query()
            ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where(function (Builder $query): void {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', ExamResitAttempt::class);
            })
            ->when($linkedChargeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $linkedChargeIds))
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the single eligible failed record for a charge, or an exception
     * reason code when it cannot be matched safely.
     */
    private function matchEligibleRecord(FinanceCharge $charge): AcademicRecord|string
    {
        $candidates = $this->eligibleRecords((int) $charge->student_id);

        if ($candidates->isEmpty()) {
            return self::REASON_NO_ELIGIBLE_RECORD;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $byDescription = $candidates->filter(
            fn (AcademicRecord $record) => $this->descriptionNamesUnit($charge->description, $record->unit?->code)
        );

        if ($byDescription->count() === 1) {
            return $byDescription->first();
        }

        return self::REASON_AMBIGUOUS_UNITS;
    }

    /**
     * Exam-resit-eligible failed records for a student: finalized, not passed, in the
     * grade-fail lane, with attendance evidence recorded.
     *
     * @return Collection<int, AcademicRecord>
     */
    private function eligibleRecords(int $studentId): Collection
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('grade_status', 'final')
            ->where('is_passed', false)
            ->where(function (Builder $query): void {
                $query->whereNull('override_pass')->orWhere('override_pass', false);
            })
            ->where('failure_reason', AcademicRecord::FAILURE_GRADE_FAILED)
            ->where(function (Builder $query): void {
                $query->whereNull('total_not_recorded')->orWhere('total_not_recorded', 0);
            })
            ->with('unit:id,code')
            ->orderBy('id')
            ->get();
    }

    private function descriptionNamesUnit(?string $description, ?string $unitCode): bool
    {
        if (blank($description) || blank($unitCode)) {
            return false;
        }

        return preg_match('/\b'.preg_quote($unitCode, '/').'\b/i', $description) === 1;
    }

    /**
     * Create a legacy-linked ExamResitAttempt and repoint the charge to it.
     *
     * @return array<string, mixed>
     */
    private function reconcile(FinanceCharge $charge, AcademicRecord $record): array
    {
        return DB::transaction(function () use ($charge, $record): array {
            $charge = FinanceCharge::query()->lockForUpdate()->findOrFail($charge->id);
            $paid = (bool) $charge->is_fully_paid;

            $attempt = ExamResitAttempt::create([
                'student_id' => $record->student_id,
                'academic_record_id' => $record->id,
                'original_course_offering_id' => $record->course_offering_id,
                'unit_id' => $record->unit_id,
                'campus_id' => $record->campus_id,
                'syllabus_template_id' => $this->resolveSyllabusId($record),
                'original_semester_id' => $record->semester_id,
                'operation_semester_id' => $charge->semester_id,
                'charge_semester_id' => $charge->semester_id,
                'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
                'requested_at' => $charge->created_at,
                'reviewed_at' => $charge->created_at,
                'status' => ExamResitAttempt::STATUS_APPROVED,
                'request_sequence' => $this->nextRequestSequence($record->id),
                'attempt_number' => null,
                'approved_at' => $charge->created_at,
                'hq_fee_status' => $paid ? ExamResitAttempt::HQ_FEE_PAID : ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
                'fee_amount' => $charge->amount,
                'exam_resit_fee_snapshot' => $charge->amount,
                'finance_charge_id' => $charge->id,
                'charge_created_at' => $charge->created_at,
                'paid_at' => $paid ? now() : null,
                'policy_snapshot' => $this->legacyMarker($charge),
                'notes' => "Reconciled from legacy exam_resit_fee charge #{$charge->id} (ACAD-RET-001 slice 6).",
            ]);

            // Repoint the charge so future idempotency + Fee Monitor inference see a
            // real Academic exam-resit source. active_source_key is recomputed on save.
            $charge->update([
                'source_type' => ExamResitAttempt::class,
                'source_id' => $attempt->id,
            ]);

            return [
                'charge_id' => $charge->id,
                'student_id' => $charge->student_id,
                'status' => 'reconciled',
                'attempt_id' => $attempt->id,
                'academic_record_id' => $record->id,
                'unit_id' => $record->unit_id,
                'hq_fee_status' => $attempt->hq_fee_status,
                'paid_amount' => (float) $charge->paid_amount,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function dryRunReconcileDetail(FinanceCharge $charge, AcademicRecord $record): array
    {
        return [
            'charge_id' => $charge->id,
            'student_id' => $charge->student_id,
            'status' => 'reconciled',
            'attempt_id' => null,
            'academic_record_id' => $record->id,
            'unit_id' => $record->unit_id,
            'hq_fee_status' => $charge->is_fully_paid
                ? ExamResitAttempt::HQ_FEE_PAID
                : ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
            'paid_amount' => (float) $charge->paid_amount,
        ];
    }

    private function resolveSyllabusId(AcademicRecord $record): ?int
    {
        $record->loadMissing('courseOffering.syllabusTemplate');

        return $record->courseOffering?->syllabusTemplate?->id;
    }

    private function nextRequestSequence(int $academicRecordId): int
    {
        return ExamResitAttempt::query()
            ->where('academic_record_id', $academicRecordId)
            ->count() + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyMarker(FinanceCharge $charge): array
    {
        return [
            'legacy_backfill' => true,
            'source' => 'legacy_exam_resit_fee_charge',
            'original_charge_id' => $charge->id,
            'original_charge_source_type' => $charge->getOriginal('source_type'),
            'original_charge_source_id' => $charge->getOriginal('source_id'),
            'charge_amount' => (float) $charge->amount,
            'paid_amount' => (float) $charge->paid_amount,
            'reconciled_at' => now()->format(DATE_ATOM),
        ];
    }
}
