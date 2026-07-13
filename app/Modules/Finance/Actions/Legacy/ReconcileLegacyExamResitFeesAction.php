<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Legacy;

use App\Console\Commands\Academic\ReconcileLegacyExamResitFeesCommand;
use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
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
 * Each legacy charge is matched to exactly ONE exam-resit-eligible academic record of
 * the same student in the grade-fail lane (attendance/both fail route to course retake
 * and are ineligible — mirrors CreateExamResitAttemptAction). Prefer a failed
 * TEC002/TEC001 record; when the student already passed the resit unit, fall back to
 * the passed record labelled `grade_failed` by backfill. Unit resolution uses resit
 * unit priority (TEC002 default when both were studied), then a unit code named in the
 * charge description. A safe match creates a legacy-linked ExamResitAttempt
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
     * Resit-eligible units in priority order (TEC002 default when both were studied).
     *
     * @var list<string>
     */
    private const RESIT_UNIT_CODES = ['TEC002', 'TEC001'];

    private const PTL = 'PTL';

    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

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
     * Resolve the single eligible record for a charge, or an exception reason code.
     */
    private function matchEligibleRecord(FinanceCharge $charge): AcademicRecord|string
    {
        $studentId = (int) $charge->student_id;

        $failed = $this->eligibleFailedResitRecords($studentId);
        $matched = $this->resolveResitUnitRecord($failed, $charge, $studentId);
        if ($matched instanceof AcademicRecord) {
            return $matched;
        }

        $passed = $this->eligiblePassedResitRecords($studentId);
        $matched = $this->resolveResitUnitRecord($passed, $charge, $studentId);
        if ($matched instanceof AcademicRecord) {
            return $matched;
        }

        return self::REASON_NO_ELIGIBLE_RECORD;
    }

    /**
     * Pick one resit-unit record from candidates using TEC002-first priority and the
     * charge description as a tie-breaker when several resit units remain.
     *
     * @param  Collection<int, AcademicRecord>  $candidates
     */
    private function resolveResitUnitRecord(Collection $candidates, FinanceCharge $charge, int $studentId): ?AcademicRecord
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $resitCandidates = $candidates->filter(
            fn (AcademicRecord $record) => in_array($record->unit?->code, self::RESIT_UNIT_CODES, true)
        );

        if ($resitCandidates->isEmpty()) {
            return null;
        }

        foreach (self::RESIT_UNIT_CODES as $code) {
            $forUnit = $resitCandidates->filter(fn (AcademicRecord $record) => $record->unit?->code === $code);
            if ($forUnit->count() === 1) {
                return $forUnit->first();
            }
        }

        $byDescription = $resitCandidates->filter(
            fn (AcademicRecord $record) => $this->descriptionNamesUnit($charge->description, $record->unit?->code)
        );
        if ($byDescription->count() === 1) {
            return $byDescription->first();
        }

        if ($this->studiedBothResitUnits($studentId)) {
            return $this->resitUnitRecord($studentId, self::RESIT_UNIT_CODES[0]);
        }

        if ($resitCandidates->count() === 1) {
            return $resitCandidates->first();
        }

        return null;
    }

    /**
     * @return Collection<int, AcademicRecord>
     */
    private function eligibleFailedResitRecords(int $studentId): Collection
    {
        return $this->gradeFailedResitRecordsQuery($studentId)
            ->where('is_passed', false)
            ->get();
    }

    /**
     * Passed resit units that were labelled grade_failed by backfill (student already
     * completed the resit).
     *
     * @return Collection<int, AcademicRecord>
     */
    private function eligiblePassedResitRecords(int $studentId): Collection
    {
        return $this->gradeFailedResitRecordsQuery($studentId)
            ->where('is_passed', true)
            ->get();
    }

    /**
     * @return Builder<AcademicRecord>
     */
    private function gradeFailedResitRecordsQuery(int $studentId): Builder
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('grade_status', 'final')
            ->where(fn (Builder $query) => $query->whereNull('override_pass')->orWhere('override_pass', false))
            ->where('failure_reason', AcademicRecord::FAILURE_GRADE_FAILED)
            ->where(fn (Builder $query) => $query->whereNull('total_not_recorded')->orWhere('total_not_recorded', 0))
            ->whereHas('unit', fn (Builder $query) => $query->whereIn('code', self::RESIT_UNIT_CODES))
            ->with('unit:id,code')
            ->orderBy('id');
    }

    private function studiedBothResitUnits(int $studentId): bool
    {
        $codes = AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('grade_status', 'final')
            ->whereHas('unit', fn (Builder $query) => $query->whereIn('code', self::RESIT_UNIT_CODES))
            ->with('unit:id,code')
            ->get()
            ->map(fn (AcademicRecord $record) => $record->unit?->code)
            ->filter()
            ->unique();

        return $codes->contains('TEC002') && $codes->contains('TEC001');
    }

    private function resitUnitRecord(int $studentId, string $unitCode): ?AcademicRecord
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('grade_status', 'final')
            ->where('failure_reason', AcademicRecord::FAILURE_GRADE_FAILED)
            ->whereHas('unit', fn (Builder $query) => $query->where('code', $unitCode))
            ->with('unit:id,code')
            ->orderByDesc('is_passed')
            ->first();
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
            $paid = $this->legacyChargeIsPaid($charge);

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
            // real Academic exam-resit source.
            $charge->update([
                'source_type' => ExamResitAttempt::class,
                'source_id' => $attempt->id,
            ]);

            $this->repointObligationToAttempt($charge, $attempt);

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

    private function repointObligationToAttempt(FinanceCharge $charge, ExamResitAttempt $attempt): void
    {
        if ($charge->finance_obligation_id === null) {
            return;
        }

        FinanceObligation::query()
            ->whereKey($charge->finance_obligation_id)
            ->update([
                'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
                'source_kind' => AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                'source_ref' => AcademicFinanceObligationSource::examResitAttemptRef($attempt),
            ]);
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
            'hq_fee_status' => $this->legacyChargeIsPaid($charge)
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
    private function legacyChargeIsPaid(FinanceCharge $charge): bool
    {
        if ($charge->is_fully_paid) {
            return true;
        }

        return DngPaymentRequest::query()
            ->where('finance_charge_id', $charge->id)
            ->where('fee_type', self::PTL)
            ->whereIn('status', self::PAID_DNG_STATUSES)
            ->exists();
    }

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
