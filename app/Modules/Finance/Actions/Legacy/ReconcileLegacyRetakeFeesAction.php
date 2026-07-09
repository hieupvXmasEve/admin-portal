<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Legacy;

use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile legacy `retake_fee` charges into CourseRetakeRegistration sources.
 *
 * Mirrors {@see ReconcileLegacyExamResitFeesAction} for the học lại lane.
 */
class ReconcileLegacyRetakeFeesAction
{
    public const REASON_NO_ELIGIBLE_RECORD = 'no_eligible_retake_record';

    public const REASON_DUPLICATE_CHARGE = 'duplicate_retake_charge';

    private const HL = 'HL';

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

            if ($this->studentHasOtherRetakeSource((int) $charge->student_id, (int) $charge->id)) {
                $result['exceptions']++;
                $result['details'][] = [
                    'charge_id' => $charge->id,
                    'student_id' => $charge->student_id,
                    'status' => 'exception',
                    'reason' => self::REASON_DUPLICATE_CHARGE,
                    'amount' => (float) $charge->amount,
                    'description' => $charge->description,
                ];

                continue;
            }

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
                'reason' => $match,
                'amount' => (float) $charge->amount,
                'description' => $charge->description,
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, FinanceCharge>
     */
    private function legacyCharges(): Collection
    {
        $linkedChargeIds = CourseRetakeRegistration::query()
            ->whereNotNull('finance_charge_id')
            ->distinct()
            ->pluck('finance_charge_id')
            ->all();

        return FinanceCharge::query()
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where(function (Builder $query): void {
                $query->whereNull('source_type')
                    ->orWhere('source_type', '!=', CourseRetakeRegistration::class);
            })
            ->when($linkedChargeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $linkedChargeIds))
            ->orderBy('id')
            ->get();
    }

    private function studentHasOtherRetakeSource(int $studentId, int $chargeId): bool
    {
        return CourseRetakeRegistration::query()
            ->where('student_id', $studentId)
            ->where(function (Builder $query) use ($chargeId): void {
                $query
                    ->where(fn (Builder $q) => $q
                        ->whereNotNull('finance_charge_id')
                        ->where('finance_charge_id', '!=', $chargeId))
                    ->orWhere(fn (Builder $q) => $q
                        ->whereNull('finance_charge_id')
                        ->whereIn('status', [
                            CourseRetakeRegistration::STATUS_ENROLLED,
                            CourseRetakeRegistration::STATUS_PAID,
                            CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
                        ]));
            })
            ->exists();
    }

    private function matchEligibleRecord(FinanceCharge $charge): AcademicRecord|string
    {
        $records = $this->eligibleRetakeRecords((int) $charge->student_id);

        if ($records->isEmpty()) {
            return self::REASON_NO_ELIGIBLE_RECORD;
        }

        if ($records->count() === 1) {
            return $records->first();
        }

        $byDescription = $records->filter(
            fn (AcademicRecord $record) => $this->descriptionNamesUnit($charge->description, $record->unit?->code)
        );
        if ($byDescription->count() === 1) {
            return $byDescription->first();
        }

        return self::REASON_NO_ELIGIBLE_RECORD;
    }

    /**
     * @return Collection<int, AcademicRecord>
     */
    private function eligibleRetakeRecords(int $studentId): Collection
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('is_passed', false)
            ->where('grade_status', 'final')
            ->where(fn (Builder $query) => $query->whereNull('override_pass')->orWhere('override_pass', false))
            ->where(fn (Builder $query) => $this->scopeToRetakeLane($query))
            ->with('unit:id,code')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Builder<AcademicRecord>  $query
     */
    private function scopeToRetakeLane(Builder $query): void
    {
        $query->where('failure_reason', '!=', AcademicRecord::FAILURE_GRADE_FAILED)
            ->orWhereNull('failure_reason');
    }

    private function descriptionNamesUnit(?string $description, ?string $unitCode): bool
    {
        if (blank($description) || blank($unitCode)) {
            return false;
        }

        return preg_match('/\b'.preg_quote($unitCode, '/').'\b/i', $description) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function reconcile(FinanceCharge $charge, AcademicRecord $record): array
    {
        return DB::transaction(function () use ($charge, $record): array {
            $charge = FinanceCharge::query()->lockForUpdate()->findOrFail($charge->id);
            $paid = $this->legacyChargeIsPaid($charge);
            $courseRegistration = $this->findRetakeCourseRegistration((int) $record->student_id, (int) $record->unit_id);

            $registration = CourseRetakeRegistration::create([
                'student_id' => $record->student_id,
                'unit_id' => $record->unit_id,
                'original_academic_record_id' => $record->id,
                'course_offering_id' => $courseRegistration?->course_offering_id ?? $record->course_offering_id,
                'semester_id' => $charge->semester_id,
                'campus_id' => $record->campus_id,
                'original_semester_id' => $record->semester_id,
                'operation_semester_id' => $charge->semester_id,
                'charge_semester_id' => $charge->semester_id,
                'status' => $courseRegistration
                    ? CourseRetakeRegistration::STATUS_ENROLLED
                    : ($paid
                        ? CourseRetakeRegistration::STATUS_PAID
                        : CourseRetakeRegistration::STATUS_PAYMENT_PENDING),
                'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
                'requested_at' => $charge->created_at,
                'reviewed_at' => $charge->created_at,
                'hq_fee_status' => $paid
                    ? CourseRetakeRegistration::HQ_FEE_PAID
                    : CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
                'attempt_number' => $this->nextAttemptNumber((int) $record->student_id, (int) $record->unit_id),
                'retake_fee' => $charge->amount,
                'finance_charge_id' => $charge->id,
                'charge_created_at' => $charge->created_at,
                'paid_at' => $paid ? now() : null,
                'course_registration_id' => $courseRegistration?->id,
                'enrolled_at' => $courseRegistration ? now() : null,
                'approved_at' => $charge->created_at,
                'policy_snapshot' => $this->legacyMarker($charge),
                'notes' => "Reconciled from legacy retake_fee charge #{$charge->id} (ACAD-RET-001).",
            ]);

            $charge->update([
                'source_type' => CourseRetakeRegistration::class,
                'source_id' => $registration->id,
            ]);

            return [
                'charge_id' => $charge->id,
                'student_id' => $charge->student_id,
                'status' => 'reconciled',
                'registration_id' => $registration->id,
                'academic_record_id' => $record->id,
                'unit_id' => $record->unit_id,
                'registration_status' => $registration->status,
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
            'registration_id' => null,
            'academic_record_id' => $record->id,
            'unit_id' => $record->unit_id,
        ];
    }

    private function findRetakeCourseRegistration(int $studentId, int $unitId): ?CourseRegistration
    {
        return CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('is_retake', true)
            ->whereHas('courseOffering', fn (Builder $query) => $query->where('unit_id', $unitId))
            ->orderByDesc('id')
            ->first();
    }

    private function nextAttemptNumber(int $studentId, int $unitId): int
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('unit_id', $unitId)
            ->count() + 1;
    }

    private function legacyChargeIsPaid(FinanceCharge $charge): bool
    {
        if ($charge->is_fully_paid) {
            return true;
        }

        return DngPaymentRequest::query()
            ->where('finance_charge_id', $charge->id)
            ->where('fee_type', self::HL)
            ->whereIn('status', self::PAID_DNG_STATUSES)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyMarker(FinanceCharge $charge): array
    {
        return [
            'legacy_backfill' => true,
            'source' => 'legacy_retake_fee_charge',
            'original_charge_id' => $charge->id,
            'original_charge_source_type' => $charge->getOriginal('source_type'),
            'original_charge_source_id' => $charge->getOriginal('source_id'),
            'charge_amount' => (float) $charge->amount,
            'paid_amount' => (float) $charge->paid_amount,
            'reconciled_at' => now()->format(DATE_ATOM),
        ];
    }
}