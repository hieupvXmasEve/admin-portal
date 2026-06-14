<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Facades\DB;

class VoidFinanceChargeAction
{
    public function __construct(
        protected AutoAllocatePaymentsAction $autoAllocatePaymentsAction,
        protected SettlementService $settlementService,
    ) {}

    /**
     * Void an existing charge, release allocations, and remove from invoice.
     *
     * Auto-reallocation of the freed cash is intentionally controllable via
     * $autoReallocate (FIN-12): callers that want a void to be inert (no hidden
     * spread of released payments onto other invoices) pass false. The result
     * always reports what was reallocated so the side-effect is never silent.
     *
     * @return array{charge: FinanceCharge, released_allocations: int, released_amount: float, affected_payments: array, reallocated_allocations: int, reallocated_amount: float, cancelled_installments: int}
     */
    public function handle(int $chargeId, string $reason, ?int $userId = null, bool $autoReallocate = true): array
    {
        return DB::transaction(function () use ($chargeId, $reason, $userId, $autoReallocate) {
            $charge = FinanceCharge::findOrFail($chargeId);
            $actorId = $userId ?? auth()->id();

            if ($charge->status === FinanceCharge::STATUS_VOID) {
                throw new \RuntimeException("Charge #{$chargeId} is already voided.");
            }

            // FIN-12: do not unilaterally cancel an installment that is awaiting a
            // LIVE DNG request — the provider still holds a collectible request.
            // Block and steer the operator to cancel the DNG first
            // (CancelDngPaymentRequestAction transitions the request to a terminal
            // state and then voids the charge safely, so it never deadlocks here).
            $this->assertNoLiveDngAwaitingInstallment($charge);

            $invoiceLines = InvoiceLine::query()
                ->where('charge_id', $charge->id)
                ->where('status', 'active')
                ->get();

            $releasedInfo = [
                'count' => 0,
                'amount' => 0.0,
                'payment_ids' => [],
            ];

            $affectedInvoiceIds = [];

            foreach ($invoiceLines as $line) {
                $lineRelease = $this->settlementService->releaseLinePayments(
                    $line,
                    $actorId,
                    self::class,
                    $charge->id,
                );

                $releasedInfo['count'] += $lineRelease['count'];
                $releasedInfo['amount'] += $lineRelease['amount'];
                $releasedInfo['payment_ids'] = array_values(array_unique(array_merge(
                    $releasedInfo['payment_ids'],
                    $lineRelease['payment_ids'],
                )));

                $releasedDiscountIds = $this->settlementService->releaseLineDiscounts(
                    $line,
                    self::class,
                    $charge->id,
                );

                $line->update([
                    'status' => 'void',
                    'voided_at' => now(),
                    'void_reason' => $reason,
                ]);

                foreach ($releasedDiscountIds as $discountId) {
                    $discount = InvoiceDiscount::query()->find($discountId);

                    if ($discount) {
                        $this->settlementService->synchronizeDiscountAllocations($discount);
                    }
                }

                $affectedInvoiceIds[] = (int) $line->invoice_id;

                $invoice = $line->invoice()->first();

                if ($invoice) {
                    $overpaymentRelease = $this->settlementService->releaseInvoiceOverpayments(
                        $invoice,
                        $actorId,
                        self::class,
                        $charge->id,
                    );

                    $releasedInfo['count'] += $overpaymentRelease['count'];
                    $releasedInfo['amount'] += $overpaymentRelease['amount'];
                    $releasedInfo['payment_ids'] = array_values(array_unique(array_merge(
                        $releasedInfo['payment_ids'],
                        $overpaymentRelease['payment_ids'],
                    )));
                }
            }

            $charge->update([
                'status' => FinanceCharge::STATUS_VOID,
                'voided_at' => now(),
                'voided_by_user_id' => $actorId,
                'void_reason' => $reason,
            ]);

            // FIN-12: a voided charge must not keep live installments. Cancel
            // non-settled rows so no next-installment DNG can still be pushed and
            // no invariant flags a live row on a dead charge.
            $cancelledInstallments = $this->cancelChargeInstallments($charge);

            // 4. Recalculate affected invoice statuses
            $this->recalculateAffectedInvoices($affectedInvoiceIds);

            // 5. Re-allocate newly released balances to the student's remaining unpaid invoices
            $reallocatedStats = $autoReallocate && $releasedInfo['amount'] > 0
                ? $this->autoAllocatePaymentsAction->runForStudents(
                    [$charge->student_id],
                    AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
                    $actorId
                )
                : [
                    'allocations_created' => 0,
                    'total_allocated_amount' => 0,
                ];

            return [
                'charge' => $charge->fresh(),
                'released_allocations' => $releasedInfo['count'],
                'released_amount' => $releasedInfo['amount'],
                'affected_payments' => $releasedInfo['payment_ids'],
                'reallocated_allocations' => $reallocatedStats['allocations_created'],
                'reallocated_amount' => (float) $reallocatedStats['total_allocated_amount'],
                'cancelled_installments' => $cancelledInstallments,
            ];
        });
    }

    /**
     * Block the void when the charge has an installment awaiting a live DNG
     * request (FIN-12). A live request (pending / pushed_to_dng) is still
     * collectible at the provider; cancelling its installment locally without
     * cancelling the request would diverge local state from the provider and the
     * real payment. The operator must cancel the DNG request first.
     */
    protected function assertNoLiveDngAwaitingInstallment(FinanceCharge $charge): void
    {
        $liveInstallment = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->where('status', FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
            ->whereNotNull('dng_payment_request_id')
            ->whereHas('dngPaymentRequest', fn ($query) => $query->whereIn('status', [
                DngPaymentRequest::STATUS_PENDING,
                DngPaymentRequest::STATUS_PUSHED_TO_DNG,
            ]))
            ->first();

        if ($liveInstallment !== null) {
            throw new \RuntimeException(
                "Charge #{$charge->id} has installment #{$liveInstallment->id} awaiting a live DNG "
                ."request (#{$liveInstallment->dng_payment_request_id}). Cancel the DNG request first; "
                .'that flow voids the charge safely.'
            );
        }
    }

    /**
     * Cancel a voided charge's installments (FIN-12).
     *
     * - pending / awaiting_payment → cancelled (no longer collectible).
     * - paid → cancelled only when the charge's payments net to zero, i.e. the
     *   void already fully reversed them; otherwise the paid row is preserved as
     *   a settled fact.
     *
     * @return int number of installments cancelled
     */
    protected function cancelChargeInstallments(FinanceCharge $charge): int
    {
        $installments = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->get();

        if ($installments->isEmpty()) {
            return 0;
        }

        // Payments were released earlier in this transaction, so a fully reversed
        // charge now nets to zero.
        $chargeNetPaid = $this->settlementService->getChargePaidAmount($charge->id);

        $cancelled = 0;

        foreach ($installments as $installment) {
            $isNonSettled = in_array($installment->status, [
                FinanceChargeInstallment::STATUS_PENDING,
                FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
            ], true);

            $isReversedPaid = $installment->status === FinanceChargeInstallment::STATUS_PAID
                && $chargeNetPaid <= 0.0;

            if ($isNonSettled || $isReversedPaid) {
                $installment->update(['status' => FinanceChargeInstallment::STATUS_CANCELLED]);
                $cancelled++;
            }
        }

        return $cancelled;
    }

    /**
     * Recalculate statuses for invoices that had charges removed.
     * If an invoice has no remaining active lines, cancel it.
     */
    protected function recalculateAffectedInvoices(array $invoiceIds): void
    {
        if (empty($invoiceIds)) {
            return;
        }

        $invoices = StudentInvoice::whereIn('id', array_unique($invoiceIds))
            ->with('invoiceLines')
            ->get();

        foreach ($invoices as $invoice) {
            $hasActiveLines = $invoice->invoiceLines
                ->contains(fn (InvoiceLine $line) => ($line->status ?? 'active') === 'active');

            if (! $hasActiveLines && $invoice->status !== 'cancelled') {
                $invoice->update(['status' => 'cancelled']);
            } else {
                $this->settlementService->recalculateInvoiceSnapshot($invoice);
            }
        }
    }
}
