<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Models\Payment;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;

/**
 * Read-only aggregate for the Student 360 status cards. Every figure is
 * delegated to an existing service/query — this class only assembles.
 */
class GetStudent360StatusCardsQuery
{
    public function __construct(
        private GetStudentBalanceQuery $balanceQuery,
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $studentId): array
    {
        return [
            'balance' => $this->balanceCard($studentId),
            'dng' => $this->dngCard($studentId),
            'installments' => $this->installmentsCard($studentId),
            'exception' => $this->exceptionCard($studentId),
        ];
    }

    /** @return array<string,mixed> */
    private function balanceCard(int $studentId): array
    {
        $balance = $this->balanceQuery->handle($studentId);

        return [
            'balance' => $balance['balance'],
            'unapplied_credit' => $balance['unapplied_credit'],
            'has_unapplied' => $balance['unapplied_credit'] > 0,
            'unapplied_payment_id' => $this->resolveUnappliedPaymentId($studentId),
        ];
    }

    private function resolveUnappliedPaymentId(int $studentId): ?int
    {
        $payment = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->get()
            ->first(fn (Payment $candidate) => $this->settlement->getPaymentUnappliedAmount($candidate) > 0);

        return $payment !== null ? (int) $payment->id : null;
    }

    /** @return array<string,mixed> */
    private function dngCard(int $studentId): array
    {
        $latest = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return ['has_active' => false, 'request' => null];
        }

        return [
            'has_active' => true,
            'request' => [
                'id' => (int) $latest->id,
                'status' => $latest->status,
                'item_id' => $latest->item_id,
                'amount' => (float) $latest->amount,
                'error_message' => $latest->error_message,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function installmentsCard(int $studentId): array
    {
        $chargeIds = FinanceCharge::query()->where('student_id', $studentId)->pluck('id');

        $installments = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->orderBy('installment_no')
            ->get();

        $next = $installments->firstWhere('status', FinanceChargeInstallment::STATUS_PENDING);

        return [
            'total' => $installments->count(),
            'paid' => $installments->where('status', FinanceChargeInstallment::STATUS_PAID)->count(),
            'next' => $next === null ? null : [
                'id' => (int) $next->id,
                'charge_id' => (int) $next->finance_charge_id,
                'installment_no' => (int) $next->installment_no,
                'due_date' => $next->due_date?->toDateString(),
                'has_push_error' => $next->last_push_error !== null,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function exceptionCard(int $studentId): array
    {
        $candidate = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->whereIn('status', [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG])
            ->latest('id')
            ->first();

        if ($candidate === null) {
            return ['needs_review' => false, 'dng_request_id' => null, 'blocking_reasons' => []];
        }

        $blocking = LifecycleDueExceptionRowMapper::blockingReasons($candidate);

        return [
            'needs_review' => $blocking === [],
            'dng_request_id' => (int) $candidate->id,
            'blocking_reasons' => $blocking,
        ];
    }
}
