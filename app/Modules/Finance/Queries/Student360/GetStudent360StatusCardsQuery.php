<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceChargeInstallment;
use App\Models\Payment;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;
use Illuminate\Support\Collection;

/**
 * Read-only aggregate for the Student 360 status cards. Every figure is
 * delegated to an existing service/query — this class only assembles.
 */
class GetStudent360StatusCardsQuery
{
    private const ACTIONABLE_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_FAILED,
    ];

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
            ->whereIn('status', self::ACTIONABLE_DNG_STATUSES)
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
        $chargeIds = $this->collectibleChargeIds($studentId);

        if ($chargeIds->isEmpty()) {
            return [
                'total' => 0,
                'paid' => 0,
                'next' => null,
            ];
        }

        $installments = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereIn('finance_charge_id', function ($query): void {
                $query->select('finance_charge_id')
                    ->from('finance_charge_installments')
                    ->groupBy('finance_charge_id')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->where('status', '!=', FinanceChargeInstallment::STATUS_CANCELLED)
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

    private function collectibleChargeIds(int $studentId): Collection
    {
        return $this->settlement
            ->getOutstandingLinesForStudent($studentId, AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
            ->pluck('charge_id')
            ->filter()
            ->unique()
            ->values();
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
