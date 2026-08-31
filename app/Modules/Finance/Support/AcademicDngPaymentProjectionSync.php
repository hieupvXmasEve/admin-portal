<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Projects Finance settlement onto Academic retake/resit paid flags.
 *
 * Webhook callers pass $throwOnFailure = true. Batch reconcile/capture/bridge
 * swallow per-student failures so one projection error cannot abort other
 * students' already-committed ledger writes.
 */
final class AcademicDngPaymentProjectionSync
{
    public function __construct(
        private readonly RetakeRegistrationPaymentSyncer $retakeRegistrationPaymentSyncer,
        private readonly ExamResitAttemptPaymentSyncer $examResitAttemptPaymentSyncer,
    ) {}

    public function syncForRequest(DngPaymentRequest $request, bool $throwOnFailure = false): void
    {
        $chargeTypes = $request->chargeLinks()
            ->with('financeCharge:id,charge_type')
            ->get()
            ->pluck('financeCharge.charge_type')
            ->filter()
            ->all();

        $this->syncStudent(
            studentId: (int) $request->student_id,
            retake: $request->fee_type === 'HL' || in_array(FinanceCharge::TYPE_RETAKE_FEE, $chargeTypes, true),
            resit: $request->fee_type === 'PTL' || in_array(FinanceCharge::TYPE_EXAM_RESIT_FEE, $chargeTypes, true),
            throwOnFailure: $throwOnFailure,
        );
    }

    public function syncForCharge(FinanceCharge $charge, bool $throwOnFailure = false): void
    {
        $this->syncStudent(
            studentId: (int) $charge->student_id,
            retake: $charge->charge_type === FinanceCharge::TYPE_RETAKE_FEE,
            resit: $charge->charge_type === FinanceCharge::TYPE_EXAM_RESIT_FEE,
            throwOnFailure: $throwOnFailure,
        );
    }

    public function syncStudent(int $studentId, bool $retake, bool $resit, bool $throwOnFailure = false): void
    {
        if ($studentId <= 0 || (! $retake && ! $resit)) {
            return;
        }

        try {
            if ($retake) {
                $result = $this->retakeRegistrationPaymentSyncer->runForStudent($studentId);
                $this->assertNoFailure((int) ($result['failed'] ?? 0), 'Academic retake payment sync failed.', $throwOnFailure);
            }

            if ($resit) {
                $result = $this->examResitAttemptPaymentSyncer->runForStudent($studentId);
                $this->assertNoFailure((int) ($result['failed'] ?? 0), 'Academic exam resit payment sync failed.', $throwOnFailure);
            }
        } catch (\Throwable $exception) {
            Log::error('Academic DNG payment projection sync failed', [
                'student_id' => $studentId,
                'error' => $exception->getMessage(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }
    }

    private function assertNoFailure(int $failed, string $message, bool $throwOnFailure): void
    {
        if ($failed <= 0) {
            return;
        }

        if ($throwOnFailure) {
            throw new RuntimeException($message);
        }

        Log::error($message, ['failed' => $failed]);
    }
}
