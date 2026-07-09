<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

class GetStudentFinanceOverviewKpisQuery
{
    public function __construct(
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $studentId): array
    {
        $invoices = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->get();

        $snapshots = $invoices->map(fn (StudentInvoice $invoice): array => $this->settlement->deriveInvoiceSnapshot($invoice));
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $surplusPayments = $payments
            ->map(fn (Payment $payment): array => [
                'payment' => $payment,
                'amount' => $this->money($this->settlement->getPaymentUnappliedAmount($payment)),
            ])
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values();

        $surplus = $this->money((float) $surplusPayments->sum('amount'));

        return [
            'title' => 'Học phí sinh viên',
            'kpis' => [
                'collectible_due' => [
                    'label' => 'Còn phải thu',
                    'amount' => $this->money((float) $snapshots->sum('remaining')),
                    'primary' => true,
                ],
                'total_paid' => [
                    'label' => 'Tổng tiền đã nộp',
                    'amount' => $this->money((float) $payments->sum(fn (Payment $payment): float => (float) $payment->amount)),
                    'primary' => false,
                ],
                'collected' => [
                    'label' => 'Đã thu',
                    'amount' => $this->money((float) $snapshots->sum('paid')),
                    'primary' => false,
                ],
                'surplus' => [
                    'label' => 'Còn dư',
                    'amount' => $surplus,
                    'primary' => false,
                ],
            ],
            'surplus_message' => $surplus > 0 ? $this->surplusMessage($surplusPayments->first()) : null,
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

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
