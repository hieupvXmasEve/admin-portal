<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Builder;

class GetStudentFinanceReviewSignalsQuery
{
    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    private const INSTALLMENT_WORK_STATUSES = [
        FinanceChargeInstallment::STATUS_PENDING,
        FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
    ];

    public function __construct(
        private readonly SettlementService $settlement,
    ) {}

    /** @return list<array<string,mixed>> */
    public function handle(int $studentId): array
    {
        return array_values(array_filter([
            $this->surplusSignal($studentId),
            $this->paidDngWithVoidedFeesSignal($studentId),
            $this->installmentMismatchSignal($studentId),
            $this->invoiceCacheDriftSignal($studentId),
        ]));
    }

    /** @return array<string,mixed>|null */
    private function surplusSignal(int $studentId): ?array
    {
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $surplus = $this->money((float) $payments->sum(
            fn (Payment $payment): float => $this->settlement->getPaymentUnappliedAmount($payment),
        ));

        if ($surplus <= 0) {
            return null;
        }

        return [
            'id' => 'surplus',
            'type' => 'surplus',
            'title' => 'Có tiền còn dư',
            'message' => sprintf(
                'Còn dư %s chưa khớp với học phí/khoản phí hiện tại.',
                $this->formatMoney($surplus),
            ),
            'target' => 'payment-history',
            'target_label' => 'Xem khoản đã nộp',
        ];
    }

    /** @return array<string,mixed>|null */
    private function paidDngWithVoidedFeesSignal(int $studentId): ?array
    {
        $requests = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', self::PAID_DNG_STATUSES)
                    ->orWhereNotNull('payment_id')
                    ->orWhereNotNull('paid_at');
            })
            ->with([
                'financeCharge',
                'chargeLinks.financeCharge',
                'payment.applications.invoiceLine.charge',
            ])
            ->latest('id')
            ->get()
            ->filter(fn (DngPaymentRequest $request): bool => $this->paidDngHasVoidedFee($request))
            ->values();

        if ($requests->isEmpty()) {
            return null;
        }

        $request = $requests->first();

        return [
            'id' => 'dng_paid_voided_fee',
            'type' => 'dng_paid_voided_fee',
            'title' => 'DNG đã thu nhưng phí đã hủy',
            'message' => $requests->count() > 1
                ? sprintf('Có %d DNG đã thu đang gắn với dòng phí đã hủy.', $requests->count())
                : sprintf('DNG #%s đã thu, nhưng dòng phí liên quan hiện đã hủy.', $this->dngReference($request)),
            'target' => 'ledger',
            'target_label' => 'Xem sổ cái',
        ];
    }

    private function paidDngHasVoidedFee(DngPaymentRequest $request): bool
    {
        if ($this->chargeIsVoided($request->financeCharge)) {
            return true;
        }

        if ($request->chargeLinks->contains(fn ($link): bool => $this->chargeIsVoided($link->financeCharge))) {
            return true;
        }

        return $request->payment?->applications
            ->contains(fn (PaymentApplication $application): bool => $this->lineIsVoided($application->invoiceLine)) ?? false;
    }

    /** @return array<string,mixed>|null */
    private function installmentMismatchSignal(int $studentId): ?array
    {
        $collectibleChargeIds = $this->settlement
            ->getOutstandingLinesForStudent($studentId, AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
            ->pluck('charge_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $count = FinanceChargeInstallment::query()
            ->whereIn('status', self::INSTALLMENT_WORK_STATUSES)
            ->whereHas('charge', fn (Builder $query): Builder => $query->where('student_id', $studentId))
            ->whereIn('finance_charge_id', function ($query): void {
                $query->select('finance_charge_id')
                    ->from('finance_charge_installments')
                    ->groupBy('finance_charge_id')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->whereNotIn('finance_charge_id', $collectibleChargeIds)
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'id' => 'installment_mismatch',
            'type' => 'installment_mismatch',
            'title' => 'Lịch trả góp không còn khớp',
            'message' => $count > 1
                ? sprintf('Có %d kỳ trả góp còn trạng thái chờ, nhưng phí liên quan không còn phải thu.', $count)
                : 'Có kỳ trả góp còn trạng thái chờ, nhưng phí liên quan không còn phải thu.',
            'target' => 'installments',
            'target_label' => 'Xem thẻ trả góp',
        ];
    }

    /** @return array<string,mixed>|null */
    private function invoiceCacheDriftSignal(int $studentId): ?array
    {
        $invoices = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->orderByDesc('id')
            ->get()
            ->filter(function (StudentInvoice $invoice): bool {
                $snapshot = $this->settlement->deriveInvoiceSnapshot($invoice);

                return $this->settlement->snapshotDriftsFromCache($invoice, $snapshot);
            })
            ->values();

        if ($invoices->isEmpty()) {
            return null;
        }

        $invoice = $invoices->first();

        return [
            'id' => 'invoice_cache_drift',
            'type' => 'invoice_cache_drift',
            'title' => 'Số lưu trên hóa đơn khác số tính lại',
            'message' => $invoices->count() > 1
                ? sprintf('Có %d hóa đơn có số cached khác số tính lại từ sổ cái.', $invoices->count())
                : sprintf('Hóa đơn %s có số cached khác số tính lại từ sổ cái.', $invoice->invoice_number),
            'target' => 'ledger',
            'target_label' => 'Xem sổ cái',
        ];
    }

    private function chargeIsVoided(?FinanceCharge $charge): bool
    {
        return $charge?->status === FinanceCharge::STATUS_VOID;
    }

    private function lineIsVoided(?InvoiceLine $line): bool
    {
        if ($line === null) {
            return false;
        }

        if (($line->status ?? 'active') !== 'active') {
            return true;
        }

        return $this->chargeIsVoided($line->charge);
    }

    private function dngReference(DngPaymentRequest $request): string
    {
        $itemId = trim((string) $request->item_id);

        return $itemId !== '' ? $itemId : (string) $request->id;
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
