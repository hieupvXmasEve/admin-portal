<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Events\InstallmentPushed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push the next pending installment of a charge to DNG.
 *
 * Triggered:
 * - Auto: by SettleInstallmentFromDngAction after a DNG payment is reconciled
 *   (post-commit dispatch via PushNextInstallmentJob).
 * - Manual: by admin clicking the "Retry push" button on an installment with
 *   last_push_error.
 *
 * Concurrency:
 * - SELECT ... FOR UPDATE on the parent charge serializes 2 webhooks for the
 *   same charge arriving concurrently. Only one will pass the "find next pending"
 *   gate.
 *
 * Returns the touched installment (now awaiting_payment) on success, or null
 * when there are no pending installments left (caller should fire
 * ChargeFullySettled in that case — wired in SettleInstallmentFromDngAction).
 *
 * Throws on DNG HTTP failure; PushNextInstallmentJob handles retry.
 */
class PushNextInstallmentAction
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $campusCodeResolver,
    ) {}

    public function handle(int $chargeId, ?int $explicitInstallmentId = null): ?FinanceChargeInstallment
    {
        // Lock the charge row to serialize concurrent pushes (webhook + manual).
        $charge = FinanceCharge::query()
            ->with('student')
            ->lockForUpdate()
            ->findOrFail($chargeId);

        $installment = $this->resolveTarget($charge, $explicitInstallmentId);

        if ($installment === null) {
            return null;
        }

        $student = $charge->student;

        if ($student === null) {
            throw new \RuntimeException(
                "FinanceCharge #{$charge->id} has no student attached. Cannot push installment."
            );
        }

        $dngFeeType = $this->mapFeeType($charge->charge_type);
        $campusCode = $this->campusCodeResolver->requireForStudent($student);

        $chargeData = [
            'campus_code' => $campusCode,
            'student_code' => $student->student_id,
            'fee_type' => $dngFeeType,
            'type' => $dngFeeType,
            'description' => $charge->description.' (Đợt '.$installment->installment_no.')',
            'semester_id' => $charge->semester_id,
            'due_date' => $installment->due_date->toDateString(),
            'item_id' => $this->buildItemId($student->student_id, $dngFeeType, $installment),
            'amount' => (float) $installment->amount,
            'student_name' => $student->full_name,
            'email' => $student->email ?? '',
            // DNG estimate_time format is MM/YY (e.g. "09/26"), NOT ISO date.
            // Matches CreateBatchDngFromChargesAction worklist input convention.
            'estimate_time' => $installment->due_date->format('m/y'),
            'student_address' => $student->current_address_line ?? $student->address ?? '',
            'cccd' => $student->national_id ?? null,
            'finance_charge_id' => $charge->id,
            'installment_id' => $installment->id,
        ];

        // createAndPush will update installment.dng_payment_request_id + status=awaiting_payment
        // atomically with the DNG record commit. On failure it records last_push_error and
        // increments push_attempt_count, then re-throws.
        $this->dngPaymentService->createAndPush($student, $chargeData);

        Log::info('Installment pushed to DNG', [
            'finance_charge_id' => $charge->id,
            'installment_id' => $installment->id,
            'installment_no' => $installment->installment_no,
            'amount' => $installment->amount,
        ]);

        $fresh = $installment->fresh();

        InstallmentPushed::dispatch($fresh);

        return $fresh;
    }

    /**
     * Resolve which installment to push:
     * - If explicit ID given (manual retry button): use it, validate it belongs to charge + is pending.
     * - Else: pick lowest-numbered pending installment.
     */
    private function resolveTarget(FinanceCharge $charge, ?int $explicitId): ?FinanceChargeInstallment
    {
        if ($explicitId !== null) {
            $installment = FinanceChargeInstallment::query()
                ->where('id', $explicitId)
                ->where('finance_charge_id', $charge->id)
                ->lockForUpdate()
                ->first();

            if ($installment === null) {
                throw new \RuntimeException(
                    "Installment #{$explicitId} not found or does not belong to charge #{$charge->id}."
                );
            }

            if ($installment->status !== FinanceChargeInstallment::STATUS_PENDING) {
                throw new \RuntimeException(
                    "Installment #{$explicitId} is not pending (status={$installment->status}). Cannot retry push."
                );
            }

            return $installment;
        }

        return FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->orderBy('installment_no')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Map FinanceCharge.charge_type to DNG fee_type. Conservative for Phase 1:
     * tuition_term -> HP. Other types use the charge_type uppercased; the DNG
     * provider has its own validation so unsupported types will fail at push.
     */
    private function mapFeeType(string $chargeType): string
    {
        return match ($chargeType) {
            FinanceCharge::TYPE_TUITION_TERM => 'HP',
            default => strtoupper($chargeType),
        };
    }

    /**
     * Deterministic item_id that distinguishes installments of the same charge.
     * DNG uniqueness is per (item_id, student_code), so installments need
     * different item_ids to coexist as separate awaiting rows (sequentially).
     */
    private function buildItemId(string $studentCode, string $dngFeeType, FinanceChargeInstallment $installment): string
    {
        return sprintf(
            '%s_%s_inst%d_%s',
            $studentCode,
            strtolower($dngFeeType),
            $installment->installment_no,
            now()->format('YmdHis'),
        );
    }
}
