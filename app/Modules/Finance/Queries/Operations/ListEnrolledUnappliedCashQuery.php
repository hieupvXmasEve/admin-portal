<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\FinancePaymentVoucher;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;

/**
 * Work type for leftover cash on students who are still studying.
 * Phase 8 surfaces this beside the leavers queue. No disposition is required.
 */
final class ListEnrolledUnappliedCashQuery
{
    public const WORK_TYPE = FinancePaymentVoucher::WORK_TYPE_ENROLLED_UNAPPLIED_CASH;

    public function __construct(private readonly SettlementService $settlement) {}

    /**
     * @return list<array{work_type: string, payment_id: int, student_id: int, unapplied: float, voucher_number: string|null}>
     */
    public function handle(?int $campusId = null): array
    {
        $payments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->when($campusId !== null, function ($query) use ($campusId): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
            })
            ->with(['vouchers' => fn ($query) => $query->latest('id')])
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($payments as $payment) {
            $unapplied = round($this->settlement->getPaymentUnappliedAmount($payment), 2);
            if ($unapplied <= 0) {
                continue;
            }

            $voucher = $payment->vouchers->first();
            $rows[] = [
                'work_type' => self::WORK_TYPE,
                'payment_id' => (int) $payment->id,
                'student_id' => (int) $payment->student_id,
                'unapplied' => $unapplied,
                'voucher_number' => $voucher?->voucher_number,
            ];
        }

        return $rows;
    }
}
