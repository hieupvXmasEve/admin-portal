<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Services\SettlementService;

class GetStudentFinancePaymentHistoryQuery
{
    public function __construct(
        private readonly SettlementService $settlement,
    ) {}

    /** @return list<array<string,mixed>> */
    public function handle(int $studentId): array
    {
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        if ($payments->isEmpty()) {
            return [];
        }

        $dngByPaymentId = DngPaymentRequest::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('payment_id')
            ->keyBy('payment_id');

        $hasCurrentFeeObligations = $this->settlement
            ->getOutstandingLinesForStudent($studentId, AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
            ->isNotEmpty();

        return $payments
            ->map(fn (Payment $payment): array => $this->row(
                $payment,
                $dngByPaymentId->get($payment->id),
                $hasCurrentFeeObligations,
            ))
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function row(Payment $payment, ?DngPaymentRequest $dng, bool $hasCurrentFeeObligations): array
    {
        $amountPaid = $this->money((float) $payment->amount);
        $disposed = (float) PaymentSurplusDisposition::query()->where('payment_id', $payment->id)
            ->whereIn('type', [PaymentSurplusDisposition::TYPE_REFUND, PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT])
            ->sum('amount');
        $surplus = $this->money(max(0, $this->settlement->getPaymentUnappliedAmount($payment) - $disposed));
        $collected = $this->money($this->settlement->getPaymentAllocatedAmount($payment));
        $hasSurplus = $surplus > 0;

        return [
            'id' => (int) $payment->id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'source' => (string) ($payment->source ?? $payment->method),
            'source_label' => $this->sourceLabel($payment, $dng),
            'reference' => $this->reference($payment, $dng),
            'dng_request_id' => $dng?->id !== null ? (int) $dng->id : null,
            'dng_request_reference' => $dng !== null ? 'DNG #'.$this->dngReference($dng) : null,
            'amount_paid' => $amountPaid,
            'collected_amount' => $collected,
            'surplus_amount' => $surplus,
            'status' => $hasSurplus ? 'surplus' : 'settled',
            'status_label' => $hasSurplus ? 'Còn dư' : 'Đã thu hết',
            'action' => $this->action($hasSurplus, $hasCurrentFeeObligations),
        ];
    }

    /** @return array{can_allocate:bool,message:?string} */
    private function action(bool $hasSurplus, bool $hasCurrentFeeObligations): array
    {
        if (! $hasSurplus) {
            return ['can_allocate' => false, 'message' => null];
        }

        if (! $hasCurrentFeeObligations) {
            return [
                'can_allocate' => false,
                'message' => 'Chưa có học phí/khoản phí hiện tại để phân bổ.',
            ];
        }

        return ['can_allocate' => true, 'message' => null];
    }

    private function sourceLabel(Payment $payment, ?DngPaymentRequest $dng): string
    {
        if ($dng !== null || $payment->source === 'dng') {
            return 'DNG';
        }

        if ($payment->source === 'manual') {
            return 'Thủ công';
        }

        return match ($payment->method) {
            Payment::METHOD_CASH => 'Tiền mặt',
            Payment::METHOD_BANK_TRANSFER => 'Chuyển khoản',
            Payment::METHOD_GATEWAY => 'Cổng thanh toán',
            Payment::METHOD_WALLET => 'Ví điện tử',
            Payment::METHOD_IMPORT => 'Import',
            default => 'Khác',
        };
    }

    private function reference(Payment $payment, ?DngPaymentRequest $dng): string
    {
        if ($dng !== null) {
            return 'DNG #'.$this->dngReference($dng);
        }

        $reference = trim((string) ($payment->external_ref ?: $payment->source));

        return $reference !== '' ? $reference : 'Thanh toán #'.$payment->id;
    }

    private function dngReference(DngPaymentRequest $dng): string
    {
        $itemId = trim((string) $dng->item_id);

        return $itemId !== '' ? $itemId : (string) $dng->id;
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
