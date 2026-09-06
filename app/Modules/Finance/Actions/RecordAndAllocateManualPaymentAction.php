<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinancePaymentVoucher;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecordAndAllocateManualPaymentAction
{
    private const VOUCHER_NUMBER_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
        private readonly StudentReferenceReader $studentReferences,
        private readonly SettlementService $settlement,
    ) {}

    /**
     * @param  array{
     *     student_id: int,
     *     amount: float,
     *     method: string,
     *     paid_at?: mixed,
     *     external_ref?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string,
     *     allocations: array<int, array{charge_id: int, amount: float}>
     * }  $data
     * @return array{payment: Payment, voucher: FinancePaymentVoucher, skipped: list<array{charge_id: int, reason: string}>}
     */
    public function run(array $data, int $actorId): array
    {
        $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing instanceof Payment) {
            if ((int) $existing->student_id !== (int) $data['student_id']) {
                throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key already belongs to another student.']);
            }

            $voucher = FinancePaymentVoucher::query()->where('payment_id', $existing->id)->firstOrFail();

            return [
                'payment' => $existing,
                'voucher' => $voucher,
                'skipped' => $voucher->skipped_snapshot ?? [],
            ];
        }

        $studentId = (int) $data['student_id'];
        $student = $this->studentReferences->find($studentId);
        $campusId = (int) ($student?->campusId ?? 0);
        if ($campusId <= 0) {
            throw ValidationException::withMessages(['student_id' => 'Student campus is required to issue a payment voucher.']);
        }

        $billingAccountId = (int) $this->billingAccountProvisioner->forStudent($studentId)->id;
        $allocations = [];
        foreach ($data['allocations'] as $row) {
            $chargeId = (int) $row['charge_id'];
            $allocations[$chargeId] = ($allocations[$chargeId] ?? 0) + (float) $row['amount'];
        }

        $result = $this->settlementMutationGuard->handle($billingAccountId, function () use ($data, $actorId, $studentId, $campusId, $allocations): array {
            $payment = $this->payments->recordPayment([
                'student_id' => $studentId,
                'amount' => (float) $data['amount'],
                'method' => $data['method'],
                'source' => 'manual',
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'received_by_user_id' => $actorId,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $report = $this->payments->allocatePaymentWithReport($payment->id, $allocations, $actorId);
            $unapplied = $this->settlement->getPaymentUnappliedAmount($payment->fresh());
            $voucher = $this->createVoucher(
                $payment,
                $campusId,
                $actorId,
                $report['applications'],
                $report['skipped'],
                $unapplied,
            );

            return [
                'payment' => $payment,
                'voucher' => $voucher,
                'skipped' => $report['skipped'],
            ];
        });

        try {
            app(RetakeRegistrationPaymentSyncer::class)->runForStudent($studentId);
        } catch (\Throwable $e) {
            Log::error('retake_sync_failed_after_manual_payment', [
                'student_id' => $studentId,
                'payment_id' => $result['payment']->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            app(ExamResitAttemptPaymentSyncer::class)->runForStudent($studentId);
        } catch (\Throwable $e) {
            Log::error('exam_resit_sync_failed_after_manual_payment', [
                'student_id' => $studentId,
                'payment_id' => $result['payment']->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @param  Collection<int, PaymentApplication>  $applications
     * @param  list<array{charge_id: int, reason: string}>  $skipped
     */
    private function createVoucher(
        Payment $payment,
        int $campusId,
        int $actorId,
        $applications,
        array $skipped,
        float $unapplied,
    ): FinancePaymentVoucher {
        $allocationSnapshot = $applications->map(static fn (PaymentApplication $application): array => [
            'payment_application_id' => (int) $application->id,
            'invoice_line_id' => (int) $application->invoice_line_id,
            'amount' => (float) $application->amount,
        ])->values()->all();

        for ($attempt = 1; ; $attempt++) {
            try {
                return FinancePaymentVoucher::query()->create([
                    'voucher_number' => $this->generateVoucherNumber((int) $payment->student_id),
                    'payment_id' => $payment->id,
                    'campus_id' => $campusId,
                    'issued_by_user_id' => $actorId,
                    'issued_at' => now(),
                    'allocations_snapshot' => $allocationSnapshot,
                    'skipped_snapshot' => $skipped,
                    'unapplied_amount' => $unapplied,
                ]);
            } catch (QueryException $e) {
                if (! $this->isVoucherNumberCollision($e) || $attempt >= self::VOUCHER_NUMBER_MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function generateVoucherNumber(int $studentId): string
    {
        $year = date('Y');
        $timestamp = now()->format('mdHis');
        $random = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        return "PV-{$year}-{$studentId}-{$timestamp}{$random}";
    }

    private function isVoucherNumberCollision(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'voucher_number');
    }
}
