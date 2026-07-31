<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Finance\Exceptions\InstallmentReconciliationException;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\ScholarshipAdjustmentTimingGuard;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentResult;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finance intake for an approved per-semester scholarship adjustment.
 *
 * Trust boundary: the caller (Academic) is NOT trusted — maker/checker,
 * campus-scoped checker permission, award fingerprint, and value bounds are
 * all re-verified here. The adjustment row is committed BEFORE any ledger
 * work so a reconciliation refusal can never destroy the approval.
 */
class ApplyScholarshipSemesterAdjustmentAction implements ScholarshipAdjustmentContract
{
    public function __construct(
        private readonly CampusPermissionReader $permissions,
        private readonly ScholarshipDiscountResolver $resolver,
        private readonly ScholarshipAdjustmentTimingGuard $timingGuard,
        private readonly InvoiceGenerationService $invoiceService,
    ) {}

    public function apply(ScholarshipAdjustmentData $data): ScholarshipAdjustmentResult
    {
        $student = Student::query()->find($data->student_id);

        if ($student === null) {
            return ScholarshipAdjustmentResult::rejected('student_not_found', 'Student does not exist.');
        }

        if ($data->source_semester_id === $data->target_semester_id) {
            return ScholarshipAdjustmentResult::rejected('semesters_identical', 'Source and target semester must differ.');
        }

        $semesterCount = Semester::query()
            ->whereIn('id', [$data->source_semester_id, $data->target_semester_id])
            ->count();

        if ($semesterCount !== 2) {
            return ScholarshipAdjustmentResult::rejected('semester_not_found', 'Source or target semester does not exist.');
        }

        $campusId = (int) $student->campus_id;

        $maker = User::query()->find($data->maker_user_id);
        $checker = User::query()->find($data->checker_user_id);

        if ($maker === null || $maker->status !== User::STATUS_ACTIVE) {
            return ScholarshipAdjustmentResult::rejected('maker_inactive', 'Maker user missing or inactive.');
        }

        if ($checker === null || $checker->status !== User::STATUS_ACTIVE) {
            return ScholarshipAdjustmentResult::rejected('checker_inactive', 'Checker user missing or inactive.');
        }

        if ($maker->id === $checker->id) {
            return ScholarshipAdjustmentResult::rejected('maker_is_checker', 'Maker and checker must differ.');
        }

        // Campus MUST be non-null here: a null campus returns the all-campus
        // union and would accept a checker with the permission anywhere.
        $checkerCodes = $this->permissions->permissionCodesForUserId((int) $checker->id, $campusId);

        if (! in_array('approve_scholarship_adjustment', $checkerCodes, true)) {
            return ScholarshipAdjustmentResult::rejected(
                'checker_not_authorized',
                "Checker lacks approve_scholarship_adjustment at campus {$campusId}.",
            );
        }

        $award = StudentScholarshipAward::query()
            ->where('student_id', $student->id)
            ->with('scholarshipDefinition')
            ->first();

        if ($award === null || $award->scholarshipDefinition === null) {
            return ScholarshipAdjustmentResult::rejected('no_scholarship', 'Student has no scholarship award.');
        }

        $definition = $award->scholarshipDefinition;

        $currentFingerprint = ScholarshipAdjustmentData::fingerprint(
            (string) $award->scholarship_code,
            (string) $definition->type,
            (string) $definition->amount,
        );

        if (! hash_equals($currentFingerprint, $data->award_fingerprint)) {
            return ScholarshipAdjustmentResult::rejected(
                'award_fingerprint_mismatch',
                'Scholarship award changed since the decision was approved.',
            );
        }

        $originalAmount = (float) $definition->amount;

        if ($data->adjusted_amount < 0 || $data->adjusted_amount > $originalAmount) {
            return ScholarshipAdjustmentResult::rejected(
                'adjusted_out_of_bounds',
                "Adjusted value must be within [0, {$originalAmount}].",
            );
        }

        // Transaction 1: invariant lock + persist the approval. Committed
        // before any ledger mutation is attempted.
        try {
            $adjustment = DB::transaction(function () use ($data, $student, $award, $definition, $campusId, $currentFingerprint) {
                $existing = ScholarshipSemesterAdjustment::query()
                    ->where('student_id', $student->id)
                    ->where('target_semester_id', $data->target_semester_id)
                    ->whereIn('status', ScholarshipSemesterAdjustment::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->exists();

                if ($existing) {
                    return null;
                }

                return ScholarshipSemesterAdjustment::query()->create([
                    'student_id' => $student->id,
                    'campus_id' => $campusId,
                    'student_scholarship_award_id' => $award->id,
                    'scholarship_code' => $award->scholarship_code,
                    'source_semester_id' => $data->source_semester_id,
                    'target_semester_id' => $data->target_semester_id,
                    'original_type' => $definition->type,
                    'original_amount' => $definition->amount,
                    'award_fingerprint' => $currentFingerprint,
                    'adjusted_amount' => $data->adjusted_amount,
                    'status' => ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY,
                    'reason' => $data->reason,
                    'academic_dossier_id' => $data->academic_dossier_id,
                    'created_by_user_id' => $data->maker_user_id,
                    'approved_by_user_id' => $data->checker_user_id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Scholarship adjustment persist failed', [
                'student_id' => $data->student_id,
                'target_semester_id' => $data->target_semester_id,
                'error' => $e->getMessage(),
            ]);

            return ScholarshipAdjustmentResult::rejected('persist_failed', 'Could not persist the adjustment — see server logs.');
        }

        if ($adjustment === null) {
            return ScholarshipAdjustmentResult::rejected(
                'duplicate_active_adjustment',
                'An active adjustment already exists for this student and target semester.',
            );
        }

        // Transaction 2 (separate): ledger work. The approval above survives
        // any refusal here.
        return $this->applyToLedger($adjustment);
    }

    /**
     * Evaluate timing guards and, when safe, refresh the ledger discount.
     * Also used by the reversal path (recomputes from the then-active state).
     */
    public function applyToLedger(ScholarshipSemesterAdjustment $adjustment): ScholarshipAdjustmentResult
    {
        try {
            // Guard read + ledger write + status flip share one transaction so
            // a payment landing between the read and the write cannot slip in.
            $outcome = DB::transaction(function () use ($adjustment): string {
                $guard = $this->timingGuard->evaluate((int) $adjustment->student_id, (int) $adjustment->target_semester_id);

                if ($guard['outcome'] === ScholarshipAdjustmentTimingGuard::OUTCOME_NO_INVOICE) {
                    // Nothing to touch yet — generation paths resolve through
                    // the adjustment when the invoice is created.
                    return ScholarshipAdjustmentTimingGuard::OUTCOME_NO_INVOICE;
                }

                if ($guard['outcome'] === ScholarshipAdjustmentTimingGuard::OUTCOME_REVIEW) {
                    return ScholarshipAdjustmentTimingGuard::OUTCOME_REVIEW;
                }

                /** @var StudentInvoice $invoice */
                $invoice = $guard['invoice'];
                $this->refreshScholarshipDiscount($invoice, $adjustment);

                $adjustment->update([
                    'status' => ScholarshipSemesterAdjustment::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

                return ScholarshipAdjustmentTimingGuard::OUTCOME_APPLY;
            });
        } catch (InstallmentReconciliationException $e) {
            // Ledger refusal — approval stays, routed to review in its own
            // committed write.
            $adjustment->update(['status' => ScholarshipSemesterAdjustment::STATUS_FINANCE_REVIEW_REQUIRED]);

            return new ScholarshipAdjustmentResult(
                true,
                ScholarshipSemesterAdjustment::STATUS_FINANCE_REVIEW_REQUIRED,
                (int) $adjustment->id,
                "Installment reconciliation refused the change: {$e->getMessage()}",
            );
        }

        if ($outcome === ScholarshipAdjustmentTimingGuard::OUTCOME_REVIEW) {
            $adjustment->update(['status' => ScholarshipSemesterAdjustment::STATUS_FINANCE_REVIEW_REQUIRED]);

            return new ScholarshipAdjustmentResult(
                true,
                ScholarshipSemesterAdjustment::STATUS_FINANCE_REVIEW_REQUIRED,
                (int) $adjustment->id,
                'Invoice has payments or active DNG activity — manual finance review required.',
            );
        }

        if ($outcome === ScholarshipAdjustmentTimingGuard::OUTCOME_NO_INVOICE) {
            return new ScholarshipAdjustmentResult(true, (string) $adjustment->status, (int) $adjustment->id);
        }

        return new ScholarshipAdjustmentResult(true, ScholarshipSemesterAdjustment::STATUS_APPLIED, (int) $adjustment->id);
    }

    /**
     * Recompute the scholarship discount for the whole invoice using the
     * adjustment (null = original terms, used by reversal). Base is the TOTAL
     * of active tuition_term charges — per-charge writes would overwrite each
     * other in createOrRefreshInvoiceDiscount and keep only the last charge's
     * discount (multi-charge invoices).
     */
    public function refreshScholarshipDiscount(StudentInvoice $invoice, ?ScholarshipSemesterAdjustment $adjustment): void
    {
        $award = StudentScholarshipAward::query()
            ->where('student_id', $invoice->student_id)
            ->with('scholarshipDefinition')
            ->first();

        if ($award === null || $award->scholarshipDefinition === null) {
            return;
        }

        $discountAmount = $this->resolver->resolveAdjusted(
            $award->scholarshipDefinition,
            $this->resolver->invoiceTuitionBase($invoice),
            $adjustment,
        );

        // Zero is a real write here: full suspension must shrink the existing
        // discount row (and release its allocations) so the invoice total moves.
        $this->invoiceService->applyInvoiceDiscount(
            $invoice,
            'scholarship',
            $discountAmount,
            StudentScholarshipAward::class,
            "Scholarship: {$award->scholarshipDefinition->name}",
            (int) $award->id,
            $adjustment?->approved_by_user_id,
        );
    }
}
