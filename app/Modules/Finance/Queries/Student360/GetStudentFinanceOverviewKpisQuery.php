<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;

class GetStudentFinanceOverviewKpisQuery
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $studentId): array
    {
        $position = $this->positionReader->current($studentId);
        $valid = (bool) $position['valid'];
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $surplusPayments = $payments
            ->map(fn (Payment $payment): array => [
                'payment' => $payment,
                'amount' => $this->paymentSurplus($payment),
            ])
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values();

        $surplus = $valid ? (float) ($position['unapplied_cash'] ?? 0) : null;

        return [
            'title' => 'Học phí sinh viên',
            'settlement' => [
                'valid' => $valid,
                'state' => $position['settlement_state'],
                'message' => $position['student_message'],
                'issues' => $position['issues'],
            ],
            'kpis' => [
                'collectible_due' => $this->kpi(
                    label: 'Còn phải thu',
                    amount: $position['remaining_collectible'],
                    primary: true,
                    valid: $valid,
                ),
                'total_paid' => $this->kpi(
                    label: 'Tổng tiền đã nộp',
                    amount: $position['total_cash_received'],
                    primary: false,
                    valid: $valid,
                ),
                'collected' => $this->kpi(
                    label: 'Đã thu',
                    amount: $position['cash_applied'],
                    primary: false,
                    valid: $valid,
                ),
                'credit_applied' => $this->kpi(
                    label: 'Đã áp dụng credit',
                    amount: $position['credit_applied'],
                    primary: false,
                    valid: $valid,
                ),
                'surplus' => $this->kpi(
                    label: 'Còn dư',
                    amount: $surplus,
                    primary: false,
                    valid: $valid,
                ),
            ],
            'surplus_message' => $surplus !== null && $surplus > 0
                ? $this->surplusMessage($surplusPayments->first())
                : null,
        ];
    }

    /** @return array{label: string, amount: float|null, primary: bool} */
    private function kpi(string $label, ?float $amount, bool $primary, bool $valid): array
    {
        return [
            'label' => $label,
            'amount' => $valid ? $amount : null,
            'primary' => $primary,
        ];
    }

    /** @param  array{payment: Payment, amount: float}|null  $surplusPayment */
    private function surplusMessage(?array $surplusPayment): ?string
    {
        if ($surplusPayment === null) {
            return null;
        }

        $payment = $surplusPayment['payment'];
        $dng = DngPaymentRequest::query()
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->first();

        $source = $dng !== null
            ? 'DNG #'.$this->dngReference($dng)
            : $this->paymentReference($payment);
        $paidAt = ($dng?->paid_at ?? $payment->paid_at)?->format('d/m/Y') ?? 'không rõ ngày';

        return sprintf(
            'Còn dư %s từ %s, đã nộp ngày %s',
            $this->formatMoney($surplusPayment['amount']),
            $source,
            $paidAt,
        );
    }

    private function dngReference(DngPaymentRequest $dng): string
    {
        $itemId = trim((string) $dng->item_id);

        return $itemId !== '' ? $itemId : (string) $dng->id;
    }

    private function paymentReference(Payment $payment): string
    {
        $reference = trim((string) ($payment->external_ref ?: $payment->source));

        return $reference !== '' ? $reference : 'thanh toán #'.$payment->id;
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function paymentSurplus(Payment $payment): float
    {
        $applied = (float) $payment->applications()->sum('amount');
        $disposed = (float) PaymentSurplusDisposition::query()
            ->where('payment_id', $payment->id)
            ->whereIn('type', [
                PaymentSurplusDisposition::TYPE_REFUND,
                PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT,
            ])
            ->sum('amount');

        return round(max(0, (float) $payment->amount - $applied - $disposed), 2);
    }
}
